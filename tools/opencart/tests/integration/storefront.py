from pathlib import Path
from typing import Any
from urllib.parse import parse_qs, urlparse

from playwright.sync_api import Page, Response

from integration.compatibility import catalog_method_route, catalog_method_url, opencart4, opencart_version_parts
from integration.config import Runtime
from integration.database import connect, create_cart_product, repair_prerelease_customer_ip
from integration.environment import capture_screenshot


def get_bootstrap_payload(runtime: Runtime, page: Page, responses: list[Response], expected_route: str) -> dict[str, Any]:
    page.wait_for_function("window.AppNunoIntegration && window.AppNunoIntegration.config && window.AppNunoIntegration.navigation && window.AppNuno && window.AppNuno.ready === true && window.__appnunoReadyEvents && window.__appnunoReadyEvents.length")
    bootstrap_scripts = page.locator('script[src*="appnuno"][src*="bootstrap"]')
    if bootstrap_scripts.count() != 1:
        raise AssertionError("The storefront must include exactly one AppNuno bootstrap script.")
    bootstrap_url = bootstrap_scripts.first.evaluate("script => script.src")
    bootstrap_responses = [
        response
        for response in responses
        if parse_qs(urlparse(response.url).query).get("route", []) == [catalog_method_route(runtime, "bootstrap")]
        and parse_qs(urlparse(response.url).query).get("page_route", []) == [expected_route]
    ]
    if not bootstrap_responses or bootstrap_responses[-1].status != 200:
        related_responses = [(response.url, response.status) for response in responses if "appnuno" in response.url or "bootstrap" in response.url]
        raise AssertionError(
            "The storefront did not successfully request the AppNuno bootstrap endpoint for "
            f"{expected_route}. Script URL: {bootstrap_url}. Related responses: {related_responses}"
        )
    page.locator('script[src="https://appnuno.com/sdk/v1/appnuno.js"]').wait_for(state="attached")
    payload = page.evaluate("window.AppNunoIntegration")
    sdk_signal = page.evaluate("window.__appnunoReadyEvents[0]")
    if sdk_signal.get("type") != "appnuno:ready" or not sdk_signal.get("navigation", {}).get("tabs"):
        raise AssertionError(f"The storefront SDK did not publish its initialization event: {sdk_signal}.")
    if payload["route"] != expected_route:
        raise AssertionError(f"Expected bootstrap route {expected_route}, got {payload['route']}.")
    if parse_qs(urlparse(payload["stateUrl"]).query).get("route", []) != [catalog_method_route(runtime, "state")]:
        raise AssertionError("The bootstrap state URL does not target the AppNuno state endpoint.")
    if payload["state"] != {"cartCount": 0, "identity": None}:
        raise AssertionError(f"Unexpected unauthenticated storefront state: {payload['state']}.")
    state_response = page.evaluate(
        "async stateUrl => { const response = await fetch(stateUrl); if (!response.ok) throw new Error(`State request failed: ${response.status}`); return await response.json(); }",
        payload["stateUrl"],
    )
    if state_response != payload["state"]:
        raise AssertionError(f"The state endpoint and bootstrap state differ: {state_response} != {payload['state']}.")
    return payload


def assert_active_navigation(payload: dict[str, Any]) -> None:
    active_ids = [tab["id"] for tab in payload["navigation"]["tabs"] if tab.get("active") is True]
    if payload["route"] == "common/home" and active_ids != ["home"]:
        raise AssertionError(f"The home page did not keep the Home tab active: {payload['navigation']['tabs']}.")
    if payload["route"] != "common/home" and active_ids:
        raise AssertionError(f"A tab stayed active on {payload['route']} without a matching destination: {payload['navigation']['tabs']}.")


def assert_cart_only_tab(payload: dict[str, Any], configuration: dict[str, Any]) -> None:
    page_tab = next(tab for tab in configuration["defaultTabs"] if tab["id"] == "ci-cart-route-tab")
    if page_tab.get("allPages") is not False or page_tab.get("pages") != ["checkout/cart"]:
        raise AssertionError(f"The cart-only tab was not limited to the cart page: {page_tab}.")
    default_tab = next(tab for tab in configuration["defaultTabs"] if tab["id"] == "home")
    if default_tab.get("allPages") is not True:
        raise AssertionError(f"The shared tab did not retain its all-pages setting: {default_tab}.")
    if page_tab.get("labels") != {"en-gb": "Cart route tab", "lt-lt": "Krepšelio skirtukas"}:
        raise AssertionError(f"The cart-only tab translations were not retained: {page_tab}.")
    if configuration.get("routes"):
        raise AssertionError(f"Page visibility replaced route-specific tab sets: {configuration['routes']}.")
    if any(tab["id"] == "ci-cart-route-tab" for tab in payload["navigation"]["tabs"]):
        raise AssertionError(f"A tab limited to the cart page was published on {payload['route']}: {payload['navigation']['tabs']}.")


def assert_configured_tabs(configuration: dict[str, Any]) -> None:
    if configuration["cartTabId"] != "ci-added-tab":
        raise AssertionError("The bootstrap configuration did not publish the selected cart badge tab ID.")
    if len(configuration["defaultTabs"]) != 4:
        raise AssertionError(f"The bootstrap configuration has an unexpected default tab set: {configuration['defaultTabs']}.")
    if any(tab["id"] == "ci-hidden-tab" for tab in configuration["defaultTabs"]):
        raise AssertionError("The externally hidden tab leaked into the public configuration.")
    for tab_id, labels in (("home", {"en-gb": "Home", "lt-lt": "Pradžia"}), ("cart", {"en-gb": "Cart", "lt-lt": "Krepšelis"})):
        configured_tab = next(tab for tab in configuration["defaultTabs"] if tab["id"] == tab_id)
        if configured_tab.get("labels") != labels:
            raise AssertionError(f"The bootstrap configuration did not retain translations for {tab_id}: {configured_tab}.")
    configured_tab = next(tab for tab in configuration["defaultTabs"] if tab["id"] == "ci-added-tab")
    if configured_tab.get("labels") != {"en-gb": "CI Added Tab", "lt-lt": "CI pridėtas skirtukas"}:
        raise AssertionError(f"The bootstrap configuration did not retain all default tab translations: {configured_tab}.")
    if configured_tab.get("urls") != {"en-gb": "/en/contact", "lt-lt": "/lt/kontaktai"}:
        raise AssertionError(f"The bootstrap configuration did not retain all default tab URLs: {configured_tab}.")


def assert_resolved_contact_tab(payload: dict[str, Any]) -> None:
    tab = next(tab for tab in payload["navigation"]["tabs"] if tab["id"] == "ci-added-tab")
    if "labels" in tab or "urls" in tab:
        raise AssertionError("The resolved navigation tab still contains untranslated labels or URLs.")
    if tab.get("icon") != "home-outline" or tab.get("activeIcon") != "home":
        raise AssertionError(f"The default tab icons were not preserved in the bootstrap payload: {tab}.")
    if tab.get("badge") != payload["state"]["cartCount"]:
        raise AssertionError("The selected cart badge tab did not receive the server cart count.")


def assert_resolved_default_tabs(payload: dict[str, Any]) -> None:
    if any(tab.get("id") == "ci-hidden-tab" for tab in payload["navigation"]["tabs"]):
        raise AssertionError("The externally hidden tab appeared in storefront navigation.")
    expected_default_tabs = {
        "home": ("Home", "/", "home-outline", "home"),
        "cart": ("Cart", "/index.php?route=checkout/cart", "cart-outline", "cart"),
        "ci-added-tab": ("CI Added Tab", "/en/contact", "home-outline", "home"),
    }
    if len(payload["navigation"]["tabs"]) != len(expected_default_tabs):
        raise AssertionError(f"The resolved bootstrap tab set is incomplete: {payload['navigation']['tabs']}.")
    for navigation_tab in payload["navigation"]["tabs"]:
        label, url, icon, active_icon = expected_default_tabs[navigation_tab["id"]]
        if navigation_tab.get("label") != label:
            raise AssertionError(f"A default tab label is wrong: {navigation_tab}.")
        if navigation_tab.get("url") != url:
            raise AssertionError(f"A default tab URL is wrong: {navigation_tab}.")
        if navigation_tab.get("icon") != icon or navigation_tab.get("activeIcon") != active_icon:
            raise AssertionError(f"A default tab icon is wrong: {navigation_tab}.")


def assert_visual_configuration(payload: dict[str, Any], configuration: dict[str, Any]) -> None:
    navigation = payload["navigation"]
    if navigation.get("back") != configuration["back"]:
        raise AssertionError("The bootstrap navigation back-button settings differ from the saved configuration.")
    if navigation.get("glass") != configuration["glass"]:
        raise AssertionError("The bootstrap navigation glass settings differ from the saved configuration.")
    if navigation.get("colors") != configuration["colors"]:
        raise AssertionError("The bootstrap navigation color settings differ from the saved configuration.")
    if configuration["back"] != {"enabled": False, "placement": "bar"}:
        raise AssertionError(f"Unexpected bootstrap back-button configuration: {configuration['back']}.")
    if configuration["glass"] != {
        "enabled": True,
        "colorScheme": "dark",
        "style": "clear",
        "animate": False,
        "animationDuration": 0.7,
        "isInteractive": False,
        "tintColor": "rgba(18, 52, 86, 0.4)",
    }:
        raise AssertionError(f"Unexpected bootstrap glass configuration: {configuration['glass']}.")
    if configuration["colors"] != {
        "light": {"backgroundColor": "#112233", "contentColor": "#223344"},
        "dark": {"backgroundColor": "#334455", "contentColor": "#445566"},
    }:
        raise AssertionError(f"Unexpected bootstrap colors: {configuration['colors']}.")


def check_default_bootstrap(payload: dict[str, Any]) -> None:
    configuration = payload["config"]
    assert_configured_tabs(configuration)
    assert_resolved_default_tabs(payload)
    assert_cart_only_tab(payload, configuration)
    assert_active_navigation(payload)
    assert_resolved_contact_tab(payload)
    assert_visual_configuration(payload, configuration)


def check_route_bootstrap(payload: dict[str, Any]) -> None:
    if payload["route"] != "checkout/cart":
        raise AssertionError(f"Expected the checkout/cart bootstrap route, got {payload['route']}.")
    configured_tab = next(tab for tab in payload["config"]["defaultTabs"] if tab["id"] == "ci-cart-route-tab")
    if configured_tab.get("labels") != {"en-gb": "Cart route tab", "lt-lt": "Krepšelio skirtukas"}:
        raise AssertionError(f"The saved cart page tab translations are incorrect: {configured_tab}.")
    if configured_tab.get("urls") != {"en-gb": "/en/cart", "lt-lt": "/lt/krepselis"}:
        raise AssertionError(f"The saved cart page tab URLs are incorrect: {configured_tab}.")
    tabs = payload["navigation"]["tabs"]
    published_ids = [tab.get("id") for tab in tabs]
    if published_ids != ["home", "cart", "ci-added-tab", "ci-cart-route-tab"]:
        raise AssertionError(f"The cart page did not show the shared tabs together with the cart-only tab: {tabs}.")
    tab = tabs[-1]
    if tab.get("label") != "Cart route tab" or tab.get("url") != "/en/cart":
        raise AssertionError(f"The checkout/cart tab was not resolved correctly: {tab}.")
    if tab.get("icon") != "cart-outline" or tab.get("activeIcon") != "cart":
        raise AssertionError(f"The cart page tab icons were not preserved: {tab}.")
    if "labels" in tab or "urls" in tab or "pages" in tab or "allPages" in tab:
        raise AssertionError("The resolved cart page tab still contains stored translations or page visibility settings.")
    active_ids = [tab["id"] for tab in tabs if tab.get("active") is True]
    if active_ids != ["cart"]:
        raise AssertionError(f"The cart page did not keep the Cart tab active: {tabs}.")


def fetch_store_state(page: Page, url: str) -> dict[str, Any]:
    return page.evaluate("async url => (await fetch(url, { credentials: 'same-origin', cache: 'no-store' })).json()", url)


def assert_cart_quantity(runtime: Runtime, page: Page, product_id: int) -> None:
    page.goto(runtime.base_url + "/index.php?route=product/product&product_id=" + str(product_id))
    page.locator("#button-cart").wait_for()
    page.locator("#button-cart").click()
    page.wait_for_timeout(500)
    page.locator("#button-cart").click()
    page.wait_for_timeout(500)
    cart_state = fetch_store_state(page, catalog_method_url(runtime, "state"))
    if cart_state.get("cartCount") != 2 or cart_state.get("identity") is not None:
        raise AssertionError(f"The storefront state did not report the real cart quantity: {cart_state}.")


def register_store_customer(runtime: Runtime, page: Page) -> dict[str, Any]:
    page.goto(runtime.base_url + "/index.php?route=account/register")
    page.locator('input[name="firstname"]').fill("AppNuno")
    page.locator('input[name="lastname"]').fill("CI Customer")
    page.locator('input[name="email"]').fill("appnuno-ci-customer@example.test")
    telephone = page.locator('input[name="telephone"]')
    if telephone.count():
        telephone.fill("5550100")
    page.locator('input[name="password"]').fill("ci-customer-password")
    confirm = page.locator('input[name="confirm"]')
    if confirm.count():
        confirm.fill("ci-customer-password")
    # OpenCart 3 collects customer addresses during checkout rather than registration.
    address = page.locator('input[name="address_1"]')
    if address.count():
        address.fill("1 CI Street")
        page.locator('input[name="city"]').fill("Vilnius")
        page.locator('input[name="postcode"]').fill("01100")
        country = page.locator('select[name="country_id"]')
        if not country.input_value():
            country.select_option(index=1)
        zone = page.locator('select[name="zone_id"]')
        zone.locator('option[value]:not([value=""])').first.wait_for(state="attached")
        zone.select_option(zone.locator('option[value]:not([value=""])').first.get_attribute("value"))
    agreement = page.locator('input[name="agree"]')
    if agreement.count():
        agreement.check()
    submit = page.locator('#content form button[type="submit"], #content form input[type="submit"]').first
    if opencart4(runtime):
        submit.click()
        page.wait_for_function("() => location.href.includes('route=account/success') || location.href.includes('route=account/account') || !!document.querySelector('.alert-danger, .invalid-feedback.d-block')")
    else:
        with page.expect_navigation():
            submit.click()
    if "route=account/success" not in page.url and "route=account/account" not in page.url:
        errors = page.locator(".alert-danger, .text-danger, .invalid-feedback.d-block").all_inner_texts()
        raise AssertionError(f"OpenCart did not accept the customer registration at {page.url}: {errors}.")
    customer_state = fetch_store_state(page, catalog_method_url(runtime, "state"))
    identity = customer_state.get("identity")
    if customer_state.get("cartCount") != 2 or not isinstance(identity, dict) or identity.get("guard") != "customer" or identity.get("is_logged_in") is not True or not identity.get("identifier"):
        raise AssertionError(f"The customer registration did not publish the authenticated storefront identity: {customer_state}.")
    return identity


def assert_logged_out_customer(runtime: Runtime, page: Page, identity: dict[str, Any]) -> None:
    page.goto(runtime.base_url + "/index.php?route=account/logout")
    logged_out = fetch_store_state(page, catalog_method_url(runtime, "state"))
    if logged_out.get("identity") != {"identifier": identity["identifier"], "guard": "customer", "is_logged_in": False}:
        raise AssertionError(f"The storefront did not preserve an explicit customer logout identity: {logged_out}.")
    if logged_out.get("cartCount") != 0:
        raise AssertionError(f"Customer logout left the guest cart with the account items: {logged_out}.")


def check_cart_and_customer_state(runtime: Runtime, page: Page) -> None:
    repair_prerelease_customer_ip(runtime)
    assert_cart_quantity(runtime, page, create_cart_product(runtime))
    identity = register_store_customer(runtime, page)
    assert_logged_out_customer(runtime, page, identity)


def set_opencart4_storefront_language(runtime: Runtime, page: Page, code: str) -> None:
    # OpenCart 4.0.0.0 prefers browser language; later versions use store-language settings.
    keys = ["config_language"]
    if opencart_version_parts(runtime) >= (4, 1, 0, 0):
        keys.append("config_language_catalog")
    with connect(runtime, autocommit=True) as connection:
        with connection.cursor() as cursor:
            for key in keys:
                cursor.execute("UPDATE oc_setting SET value = %s WHERE store_id = 0 AND `code` = 'config' AND `key` = %s", (code, key))
    page.set_extra_http_headers({"Accept-Language": code})


def set_posted_storefront_language(page: Page, code: str) -> None:
    status = page.evaluate(
        """async code => {
            const body = new URLSearchParams();
            body.set("code", code);
            body.set("redirect", location.origin + "/");
            const response = await fetch("index.php?route=common/language/language", {
                method: "POST",
                credentials: "same-origin",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body
            });
            return response.status;
        }""",
        code,
    )
    if status != 200:
        raise AssertionError(f"OpenCart did not accept storefront language {code}: HTTP {status}.")


def set_storefront_language(runtime: Runtime, page: Page, code: str) -> None:
    if opencart4(runtime):
        set_opencart4_storefront_language(runtime, page, code)
        return
    set_posted_storefront_language(page, code)


def expect_resolved_storefront_language(runtime: Runtime, page: Page, responses: list[Response], home_label: str, cart_label: str, added_label: str, added_url: str) -> None:
    page.goto(runtime.base_url + "/")
    payload = get_bootstrap_payload(runtime, page, responses, "common/home")
    labels = {tab["id"]: (tab.get("label"), tab.get("url")) for tab in payload["navigation"]["tabs"]}
    expected = {
        "home": (home_label, "/"),
        "cart": (cart_label, "/index.php?route=checkout/cart"),
        "ci-added-tab": (added_label, added_url),
    }
    if labels != expected:
        raise AssertionError(f"The storefront published the wrong language labels: {labels}.")


def check_storefront_label_languages(runtime: Runtime, page: Page, responses: list[Response]) -> None:
    set_storefront_language(runtime, page, "lt-lt")
    expect_resolved_storefront_language(runtime, page, responses, "Pradžia", "Krepšelis", "CI pridėtas skirtukas", "/lt/kontaktai")
    page.goto(runtime.base_url + "/index.php?route=checkout/cart")
    published = {tab["id"]: tab for tab in get_bootstrap_payload(runtime, page, responses, "checkout/cart")["navigation"]["tabs"]}
    route_tab = published.get("ci-cart-route-tab")
    if not route_tab or route_tab.get("label") != "Krepšelio skirtukas" or route_tab.get("url") != "/lt/krepselis":
        raise AssertionError(f"The storefront published the wrong checkout/cart language: {route_tab}.")
    if published.get("home", {}).get("label") != "Pradžia":
        raise AssertionError(f"The shared home tab was not published in Lithuanian on the cart page: {published.get('home')}.")
    set_storefront_language(runtime, page, "en-gb")
    expect_resolved_storefront_language(runtime, page, responses, "Home", "Cart", "CI Added Tab", "/en/contact")


def check_storefront(runtime: Runtime, page: Page, artifact_dir: Path) -> None:
    responses: list[Response] = []

    def capture_response(response: Response) -> None:
        responses.append(response)

    page.on("response", capture_response)
    page.goto(runtime.base_url + "/")
    check_default_bootstrap(get_bootstrap_payload(runtime, page, responses, "common/home"))
    capture_screenshot(page, artifact_dir, "04-storefront-desktop.png")
    page.set_viewport_size({"width": 390, "height": 844})
    page.reload()
    check_default_bootstrap(get_bootstrap_payload(runtime, page, responses, "common/home"))
    capture_screenshot(page, artifact_dir, "05-storefront-mobile.png")
    page.set_viewport_size({"width": 1440, "height": 1000})
    page.goto(runtime.base_url + "/index.php?route=product/special")
    check_default_bootstrap(get_bootstrap_payload(runtime, page, responses, "product/special"))
    page.goto(runtime.base_url + "/index.php?route=checkout/cart")
    check_route_bootstrap(get_bootstrap_payload(runtime, page, responses, "checkout/cart"))
    capture_screenshot(page, artifact_dir, "06-storefront-cart-route.png")
    check_storefront_label_languages(runtime, page, responses)
