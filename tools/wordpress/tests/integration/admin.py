import os
from playwright.sync_api import expect, sync_playwright


def main() -> None:
    base = os.environ.get("WORDPRESS_SITE_URL", "http://127.0.0.1:8080").rstrip("/")
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch()
        admin = browser.new_context()
        page = admin.new_page()
        disabled_bootstrap = page.request.get(base + "/?appnuno=bootstrap")
        disabled_state = page.request.get(base + "/?appnuno=state")
        if disabled_bootstrap.status != 404 or disabled_bootstrap.text() != "":
            raise AssertionError("A disabled module must answer the bootstrap request with an empty 404.")
        if disabled_state.status != 404 or disabled_state.text() != "{}":
            raise AssertionError("A disabled module must answer the state request with an empty object and 404.")
        page.goto(base + "/wp-login.php")
        page.locator("#user_login").fill("admin")
        page.locator("#user_pass").fill("appnuno-local")
        page.locator("#wp-submit").click()
        page.goto(base + "/wp-admin/admin.php?page=appnuno")
        page.locator('[data-status-switch="input-status"] button[data-value="1"]').click()
        page.locator('button[data-add-tab="default-tabs"]').click()
        tab = page.locator("#default-tabs .appnuno-tab").last
        tab.locator('[data-field="id"]').fill("home")
        tab.locator('[data-language-label]').first.fill("Home")
        tab.locator('[data-url-select]').select_option("/")
        page.locator('button[type="submit"][form="form-appnuno"]').click()
        page.wait_for_url("**/wp-admin/admin.php?page=appnuno**")
        expect(page.locator(".appnuno-alert-success")).to_be_visible()
        languages = page.locator("#appnuno-languages").inner_text()
        if "en-us" not in languages and "en-gb" not in languages:
            raise AssertionError("The admin language list did not include English.")
        page.reload()
        expect(page.locator("#default-tabs .appnuno-tab")).to_have_count(1)
        expect(page.locator("#input-status")).to_have_value("1")
        expect(page.locator("#default-tabs .appnuno-tab [data-field='id']")).to_have_value("home")
        store = browser.new_context()
        front = store.new_page()
        front.goto(base + "/")
        scripts = front.locator('script[src*="appnuno=bootstrap"]')
        expect(scripts).to_have_count(1)
        bootstrap = front.request.get(scripts.first.get_attribute("src"))
        if not bootstrap.ok:
            raise AssertionError("The storefront bootstrap request failed.")
        body = bootstrap.text()
        if "window.AppNunoIntegration" not in body or "https://appnuno.com/sdk/v1/appnuno.js" not in body:
            raise AssertionError("The bootstrap script did not publish the integration or the AppNuno SDK.")
        state = front.request.get(base + "/?appnuno=state")
        if not state.ok:
            raise AssertionError("The state request failed while the module was enabled.")
        payload = state.json()
        if payload.get("cartCount") != 0 or payload.get("identity") is not None:
            raise AssertionError("A new visitor should have an empty cart and no identity.")
        front.goto(base + "/product/appnuno-sample/")
        add_to_cart = front.locator("button.single_add_to_cart_button, button[name='add-to-cart']")
        if add_to_cart.count():
            add_to_cart.first.click()
            front.wait_for_load_state("networkidle")
        else:
            product_id = front.locator("[name='add-to-cart'], [data-product_id]").first.get_attribute("value")
            if not product_id:
                product_id = front.locator("[data-product_id]").first.get_attribute("data-product_id")
            if not product_id:
                raise AssertionError("The sample product could not be added to the cart.")
            front.goto(base + "/?add-to-cart=" + product_id)
        cart_state = front.request.get(base + "/?appnuno=state").json()
        if cart_state.get("cartCount", 0) < 1:
            raise AssertionError("The cart count did not include the sample product.")
        front.goto(base + "/my-account/")
        front.locator("#username").fill("customer")
        front.locator("#password").fill("appnuno-local")
        front.locator("button[name='login']").click()
        front.wait_for_load_state("networkidle")
        logged_in = front.request.get(base + "/?appnuno=state").json()
        identity = logged_in.get("identity") or {}
        if identity.get("guard") != "customer" or identity.get("is_logged_in") is not True or not str(identity.get("identifier", "")).isdigit():
            raise AssertionError("A signed-in customer did not receive a customer identity.")
        identifier = identity["identifier"]
        front.locator("a[href*='customer-logout']").click()
        front.wait_for_load_state("networkidle")
        logged_out = front.request.get(base + "/?appnuno=state").json()
        remembered = logged_out.get("identity") or {}
        if remembered.get("identifier") != identifier or remembered.get("is_logged_in") is not False or remembered.get("guard") != "customer":
            raise AssertionError("Logging out should keep the customer id and mark the visitor as logged out.")
        browser.close()
    print("WooCommerce admin and storefront checks passed.")


if __name__ == "__main__":
    main()
