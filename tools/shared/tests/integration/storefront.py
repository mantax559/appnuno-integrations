from typing import Any
from urllib.parse import parse_qs, urlparse

from playwright.sync_api import Page, Response

from integration.adapter import StoreAdapter


def fetch_state(page: Page, adapter: StoreAdapter) -> tuple[int, str]:
    response = page.request.get(adapter.state_url())
    return response.status, response.text()


def assert_disabled_endpoints(page: Page, adapter: StoreAdapter) -> None:
    disabled_bootstrap = page.request.get(adapter.bootstrap_url())
    status, body = fetch_state(page, adapter)
    if disabled_bootstrap.status != 404 or disabled_bootstrap.text() != "":
        raise AssertionError("A disabled module must answer the bootstrap request with an empty 404.")
    if status != 404 or body != "{}":
        raise AssertionError("A disabled module must answer the state request with an empty object and 404.")


def read_integration(page: Page, adapter: StoreAdapter, responses: list[Response], expected_route: str) -> dict[str, Any]:
    page.wait_for_function(
        "window.AppNunoIntegration && window.AppNunoIntegration.config && window.AppNunoIntegration.navigation && window.AppNuno && window.AppNuno.ready === true && window.__appnunoReadyEvents && window.__appnunoReadyEvents.length",
        timeout=30000,
    )
    scripts = page.locator(adapter.bootstrap_selector)
    if scripts.count() != 1:
        raise AssertionError("The storefront must include exactly one AppNuno bootstrap script.")
    matched = [
        response
        for response in responses
        if response.status == 200 and parse_qs(urlparse(response.url).query).get("page_route", [None])[-1] == expected_route
    ]
    if not matched:
        related = [(response.url, response.status) for response in responses if "appnuno" in response.url or "bootstrap" in response.url]
        raise AssertionError(f"The storefront did not request the bootstrap endpoint for {expected_route}. Related responses: {related}")
    page.locator('script[src="https://appnuno.com/sdk/v1/appnuno.js"]').wait_for(state="attached")
    payload = page.evaluate("window.AppNunoIntegration")
    signal = page.evaluate("window.__appnunoReadyEvents[0]")
    if signal.get("type") != "appnuno:ready" or not signal.get("navigation", {}).get("tabs"):
        raise AssertionError(f"The storefront SDK did not publish its initialization event: {signal}.")
    if payload.get("route") != expected_route:
        raise AssertionError(f"Expected bootstrap route {expected_route}, got {payload.get('route')}.")
    if not adapter.accepts_state_url(str(payload.get("stateUrl", ""))):
        raise AssertionError("The bootstrap state URL does not target the AppNuno state endpoint.")
    if payload.get("state") != {"cartCount": 0, "identity": None}:
        raise AssertionError(f"Unexpected unauthenticated storefront state: {payload.get('state')}.")
    state_status, state_body = fetch_state(page, adapter)
    if state_status != 200:
        raise AssertionError(f"The state endpoint returned HTTP {state_status} while the module was enabled.")
    fetched = page.evaluate("text => JSON.parse(text)", state_body)
    if fetched != payload["state"]:
        raise AssertionError(f"The state endpoint and bootstrap state differ: {fetched} != {payload['state']}.")
    return payload


def assert_catalog_icon(tab: dict[str, Any], expected_id: str) -> None:
    icon = tab.get("icon")
    if not isinstance(icon, dict) or icon.get("id") != expected_id or "activeIcon" in tab:
        raise AssertionError(f"The tab should publish only its selected catalog icon {expected_id}: {tab}.")


def assert_configured_tabs(configuration: dict[str, Any], adapter: StoreAdapter) -> None:
    if configuration.get("cartTabId") != "ci-added-tab":
        raise AssertionError("The bootstrap configuration did not publish the selected cart badge tab ID.")
    tabs = configuration.get("defaultTabs")
    if not isinstance(tabs, list) or len(tabs) != 4:
        raise AssertionError(f"The bootstrap configuration has an unexpected default tab set: {tabs}.")
    if any(tab.get("id") == "ci-hidden-tab" for tab in tabs):
        raise AssertionError("The externally hidden tab leaked into the public configuration.")
    expected = {
        "home": ({adapter.english_code: "Home", adapter.lithuanian_code: "Pradžia"}, None),
        "cart": ({adapter.english_code: "Cart", adapter.lithuanian_code: "Krepšelis"}, None),
        "ci-added-tab": ({adapter.english_code: "CI Added Tab", adapter.lithuanian_code: "CI pridėtas skirtukas"}, {adapter.english_code: "/en/contact", adapter.lithuanian_code: "/lt/kontaktai"}),
    }
    for tab in tabs:
        if tab.get("id") == "ci-cart-route-tab":
            continue
        labels, urls = expected[tab["id"]]
        if tab.get("labels") != labels:
            raise AssertionError(f"The bootstrap configuration did not retain translations for {tab['id']}: {tab}.")
        if urls is not None and tab.get("urls") != urls:
            raise AssertionError(f"The bootstrap configuration did not retain all default tab URLs: {tab}.")
    page_tab = next(tab for tab in tabs if tab.get("id") == "ci-cart-route-tab")
    if page_tab.get("allPages") is not False or page_tab.get("pages") != ["common/home", "checkout/cart"]:
        raise AssertionError(f"The page visibility selection was not retained: {page_tab}.")
    if page_tab.get("labels") != {adapter.english_code: "Cart route tab", adapter.lithuanian_code: "Krepšelio skirtukas"}:
        raise AssertionError(f"The cart page tab translations were not retained: {page_tab}.")
    if page_tab.get("urls") != {adapter.english_code: "/en/cart", adapter.lithuanian_code: "/lt/krepselis"}:
        raise AssertionError(f"The cart page tab URLs were not retained: {page_tab}.")
    if configuration.get("routes"):
        raise AssertionError(f"Page visibility replaced route-specific tab sets: {configuration.get('routes')}.")
    home = next(tab for tab in tabs if tab.get("id") == "home")
    cart = next(tab for tab in tabs if tab.get("id") == "cart")
    if home.get("allPages") is not True:
        raise AssertionError(f"The shared tab did not retain its all-pages setting: {home}.")
    if home.get("url") != adapter.home_path or cart.get("url") != adapter.cart_path:
        raise AssertionError(f"Built-in destinations were not retained: {home.get('url')} {cart.get('url')}.")


def assert_resolved_tabs(payload: dict[str, Any], adapter: StoreAdapter, include_page_tab: bool) -> None:
    tabs = payload["navigation"]["tabs"]
    if any(tab.get("id") == "ci-hidden-tab" for tab in tabs):
        raise AssertionError("The externally hidden tab appeared in storefront navigation.")
    expected = [
        ("home", "Home", adapter.home_path, "duo:dashboard"),
        ("cart", "Cart", adapter.cart_path, "duo:shopping-bag"),
        ("ci-added-tab", "CI Added Tab", "/en/contact", "duo:user"),
    ]
    if include_page_tab:
        expected.append(("ci-cart-route-tab", "Cart route tab", "/en/cart", "duo:shopping-bag"))
    published = [tab.get("id") for tab in tabs]
    if published != [item[0] for item in expected]:
        raise AssertionError(f"The resolved bootstrap tab set is incomplete: {tabs}.")
    for tab, (tab_id, label, url, icon_id) in zip(tabs, expected):
        if tab.get("label") != label or tab.get("url") != url:
            raise AssertionError(f"A resolved tab is wrong for {tab_id}: {tab}.")
        assert_catalog_icon(tab, icon_id)
        if any(key in tab for key in ("labels", "urls", "pages", "allPages")):
            raise AssertionError(f"The resolved tab still contains stored translations or page visibility: {tab}.")
    contact = next(tab for tab in tabs if tab.get("id") == "ci-added-tab")
    if contact.get("badge") != payload["state"]["cartCount"]:
        raise AssertionError("The selected cart badge tab did not receive the server cart count.")


def assert_visual_configuration(payload: dict[str, Any]) -> None:
    navigation = payload["navigation"]
    configuration = payload["config"]
    if navigation.get("back") != configuration.get("back") or navigation.get("glass") != configuration.get("glass") or navigation.get("colors") != configuration.get("colors"):
        raise AssertionError("The bootstrap navigation visual settings differ from the saved configuration.")
    if configuration.get("back") != {"enabled": False, "placement": "bar"}:
        raise AssertionError(f"Unexpected bootstrap back-button configuration: {configuration.get('back')}.")
    if configuration.get("glass") != {
        "enabled": True,
        "colorScheme": "dark",
        "style": "clear",
        "animate": False,
        "animationDuration": 0.7,
        "isInteractive": False,
        "tintColor": "rgba(18, 52, 86, 0.4)",
    }:
        raise AssertionError(f"Unexpected bootstrap glass configuration: {configuration.get('glass')}.")
    if configuration.get("colors") != {
        "light": {"backgroundColor": "#112233", "contentColor": "#223344"},
        "dark": {"backgroundColor": "#334455", "contentColor": "#445566"},
    }:
        raise AssertionError(f"Unexpected bootstrap colors: {configuration.get('colors')}.")


def assert_active(payload: dict[str, Any], expected_id: str | None) -> None:
    active = [tab.get("id") for tab in payload["navigation"]["tabs"] if tab.get("active") is True]
    expected = [] if expected_id is None else [expected_id]
    if active != expected:
        raise AssertionError(f"Active tabs on {payload.get('route')} were {active}, expected {expected}.")


def assert_english_bootstrap(payload: dict[str, Any], adapter: StoreAdapter, include_page_tab: bool, active_id: str | None) -> None:
    if payload.get("locale") != adapter.english_code:
        raise AssertionError(f"Expected bootstrap locale {adapter.english_code}, got {payload.get('locale')}.")
    assert_configured_tabs(payload["config"], adapter)
    assert_resolved_tabs(payload, adapter, include_page_tab)
    assert_visual_configuration(payload)
    assert_active(payload, active_id)


def expect_language(page: Page, adapter: StoreAdapter, responses: list[Response], expected_locale: str, labels: dict[str, tuple[str, str]]) -> None:
    payload = read_integration(page, adapter, responses, "common/home")
    if payload.get("locale") != expected_locale:
        raise AssertionError(f"Expected bootstrap locale {expected_locale}, got {payload.get('locale')}.")
    published = {tab["id"]: (tab.get("label"), tab.get("url")) for tab in payload["navigation"]["tabs"]}
    comparable = {tab_id: published.get(tab_id) for tab_id in labels}
    if comparable != labels:
        raise AssertionError(f"The storefront published the wrong language labels: {published}.")


def check_storefront(page: Page, adapter: StoreAdapter, directory) -> None:
    from integration.browser import capture_screenshot

    responses: list[Response] = []
    page.on("response", lambda response: responses.append(response))
    page.goto(adapter.absolute(adapter.home_path))
    assert_english_bootstrap(read_integration(page, adapter, responses, "common/home"), adapter, True, "home")
    capture_screenshot(page, directory, "04-storefront-desktop.png")
    page.set_viewport_size({"width": 390, "height": 844})
    page.reload()
    assert_english_bootstrap(read_integration(page, adapter, responses, "common/home"), adapter, True, "home")
    capture_screenshot(page, directory, "05-storefront-mobile.png")
    page.set_viewport_size({"width": 1440, "height": 1000})
    page.goto(adapter.storefront_url(adapter.english_code, adapter.special_path))
    assert_english_bootstrap(read_integration(page, adapter, responses, "product/special"), adapter, False, None)
    page.goto(adapter.absolute(adapter.cart_path))
    cart_payload = read_integration(page, adapter, responses, "checkout/cart")
    assert_english_bootstrap(cart_payload, adapter, True, "cart")
    capture_screenshot(page, directory, "06-storefront-cart-route.png")
    adapter.switch_storefront_language(page, adapter.lithuanian_code)
    page.goto(adapter.storefront_url(adapter.lithuanian_code, adapter.home_path))
    expect_language(page, adapter, responses, adapter.lithuanian_code, {
        "home": ("Pradžia", adapter.home_path),
        "cart": ("Krepšelis", adapter.cart_path),
        "ci-added-tab": ("CI pridėtas skirtukas", "/lt/kontaktai"),
        "ci-cart-route-tab": ("Krepšelio skirtukas", "/lt/krepselis"),
    })
    page.goto(adapter.storefront_url(adapter.lithuanian_code, adapter.cart_path))
    lithuanian_cart = read_integration(page, adapter, responses, "checkout/cart")
    route_tab = next((tab for tab in lithuanian_cart["navigation"]["tabs"] if tab.get("id") == "ci-cart-route-tab"), None)
    if not route_tab or route_tab.get("label") != "Krepšelio skirtukas" or route_tab.get("url") != "/lt/krepselis":
        raise AssertionError(f"The storefront published the wrong cart-page language: {route_tab}.")
    home = next(tab for tab in lithuanian_cart["navigation"]["tabs"] if tab.get("id") == "home")
    if home.get("label") != "Pradžia":
        raise AssertionError(f"The shared home tab was not published in Lithuanian on the cart page: {home}.")
    adapter.switch_storefront_language(page, adapter.english_code)
    page.goto(adapter.storefront_url(adapter.english_code, adapter.home_path))
    expect_language(page, adapter, responses, adapter.english_code, {
        "home": ("Home", adapter.home_path),
        "cart": ("Cart", adapter.cart_path),
        "ci-added-tab": ("CI Added Tab", "/en/contact"),
        "ci-cart-route-tab": ("Cart route tab", "/en/cart"),
    })


def assert_not_published(page: Page, adapter: StoreAdapter, installed: bool = True) -> None:
    page.goto(adapter.absolute(adapter.home_path))
    if page.locator(adapter.bootstrap_selector).count():
        raise AssertionError("The module still injected its bootstrap script.")
    status, body = fetch_state(page, adapter)
    if installed and (status != 404 or body != "{}"):
        raise AssertionError(f"The disabled module state endpoint returned HTTP {status} with body {body!r}.")
    if not installed and "cartCount" in body:
        raise AssertionError("An uninstalled module still answered the state request.")


def assert_enabled(page: Page, adapter: StoreAdapter) -> None:
    page.goto(adapter.absolute(adapter.home_path))
    page.wait_for_function(
        "window.AppNunoIntegration && window.AppNunoIntegration.navigation && window.AppNuno && window.AppNuno.ready === true",
        timeout=30000,
    )
    if page.locator(adapter.bootstrap_selector).count() != 1:
        raise AssertionError("The enabled module did not restore exactly one bootstrap script.")
    status, _body = fetch_state(page, adapter)
    if status != 200:
        raise AssertionError(f"The enabled module state endpoint returned HTTP {status}.")
