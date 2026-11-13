from integration.adapter import StoreAdapter
from integration.browser import artifact_dir, capture_screenshot, open_session
from integration.settings import (
    ENGLISH_PHRASES,
    LITHUANIAN_PHRASES,
    assert_editor,
    assert_responsive_layout,
    assert_saved_module,
    assert_settings_focus,
    expect_admin_language,
    reject_invalid_configuration,
    save_settings,
    save_status,
)
from integration.storefront import assert_disabled_endpoints, assert_enabled, assert_not_published, check_storefront


def browser_state(page, adapter: StoreAdapter) -> dict:
    response = page.request.get(adapter.state_url(), timeout=15000)
    if response.status != 200:
        raise AssertionError(f"The state endpoint returned HTTP {response.status}.")
    return response.json()


def check_cart_and_customer(page, adapter: StoreAdapter) -> None:
    print("Checking the cart.", flush=True)
    page.goto(adapter.absolute(adapter.home_path), wait_until="domcontentloaded", timeout=20000)
    print("Reading the visitor cart.", flush=True)
    visitor = browser_state(page, adapter)
    if visitor.get("cartCount") != 0 or visitor.get("badgeCounts") != {"cart": 0} or visitor.get("identity") is not None:
        raise AssertionError(f"A new visitor should have an empty cart and no identity: {visitor}.")
    print("Adding two products.", flush=True)
    adapter.add_two_products(page)
    cart = browser_state(page, adapter)
    if cart.get("cartCount") != 2 or cart.get("badgeCounts") != {"cart": 2} or cart.get("identity") is not None:
        raise AssertionError(f"The storefront state did not report two cart items: {cart}.")
    print("Registering a customer.", flush=True)
    adapter.register_customer(page)
    logged_in = browser_state(page, adapter)
    identity = logged_in.get("identity") or {}
    if logged_in.get("cartCount") != 2 or logged_in.get("badgeCounts") != {"cart": 2} or identity.get("guard") != "customer" or identity.get("is_logged_in") is not True or not str(identity.get("identifier", "")).isdigit():
        raise AssertionError(f"A registered customer did not keep the cart and receive a customer identity: {logged_in}.")
    identifier = identity["identifier"]
    print("Logging the customer out.", flush=True)
    adapter.logout_customer(page)
    logged_out = browser_state(page, adapter)
    remembered = logged_out.get("identity") or {}
    if remembered.get("identifier") != identifier or remembered.get("is_logged_in") is not False or remembered.get("guard") != "customer":
        raise AssertionError(f"Logging out should keep the customer id and mark the visitor as logged out: {logged_out}.")
    visible = adapter.cart_page_count(page)
    if logged_out.get("cartCount") != visible or logged_out.get("badgeCounts") != {"cart": visible}:
        raise AssertionError(f"The logged-out cart count {logged_out.get('cartCount')} does not match the store cart {visible}.")


def assert_reinstalled_disabled(page, adapter: StoreAdapter) -> None:
    adapter.open_settings(page)
    if page.locator("#input-status").input_value() != "0":
        raise AssertionError("Reinstalling the module should restore it disabled.")


def run(adapter: StoreAdapter) -> None:
    directory = artifact_dir(adapter)
    session = open_session(adapter, directory)
    page = session.page
    try:
        assert_disabled_endpoints(page, adapter)
        print("Disabled endpoints passed.", flush=True)
        adapter.login_admin(page)
        adapter.open_settings(page)
        assert_editor(page, adapter)
        assert_responsive_layout(page, adapter)
        assert_settings_focus(page)
        print("Admin editor passed.", flush=True)
        capture_screenshot(page, directory, "01-admin-settings-initial.png")
        page.set_viewport_size({"width": 820, "height": 1000})
        capture_screenshot(page, directory, "admin-settings-tablet.png")
        page.set_viewport_size({"width": 390, "height": 844})
        capture_screenshot(page, directory, "admin-settings-mobile.png")
        page.set_viewport_size({"width": 1440, "height": 1000})
        save_settings(page, adapter, directory)
        print("Settings save passed.", flush=True)
        adapter.switch_admin_language(page, adapter.lithuanian_code)
        adapter.open_settings(page)
        expect_admin_language(page, LITHUANIAN_PHRASES)
        adapter.switch_admin_language(page, adapter.english_code)
        adapter.open_settings(page)
        expect_admin_language(page, ENGLISH_PHRASES)
        print("Admin languages passed.", flush=True)
        store = session.store_page()
        check_storefront(store, adapter, directory)
        print("Storefront passed.", flush=True)
        check_cart_and_customer(store, adapter)
        print("Cart and customer passed.", flush=True)
        reject_invalid_configuration(page, adapter)
        print("Invalid settings were rejected.", flush=True)
        adapter.deny_limited_save(page)
        print("Permission check passed.", flush=True)
        adapter.open_settings(page)
        assert_saved_module(page)
        save_status(page, False)
        print("Module disabled.", flush=True)
        assert_not_published(session.store_page(), adapter)
        adapter.open_settings(page)
        save_status(page, True)
        print("Module enabled.", flush=True)
        assert_enabled(session.store_page(), adapter)
        adapter.uninstall(page)
        print("Module uninstalled.", flush=True)
        assert_not_published(session.store_page(), adapter, False)
        adapter.install(page)
        print("Module reinstalled.", flush=True)
        assert_reinstalled_disabled(page, adapter)
        save_status(page, True)
        assert_enabled(session.store_page(), adapter)
    except Exception:
        try:
            capture_screenshot(page, directory, "failure.png")
        except Exception as screenshot_error:
            print(f"Failure screenshot was not saved: {screenshot_error}", flush=True)
        raise
    finally:
        session.close()
    print(f"{adapter.name} admin, storefront, cart, customer and module lifecycle checks passed.")
    print(f"Screenshots and the Playwright trace were saved to {directory}.")
