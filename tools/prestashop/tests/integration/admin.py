import os
from playwright.sync_api import expect, sync_playwright


def main() -> None:
    base = os.environ.get("PRESTASHOP_SITE_URL", "http://127.0.0.1:8088").rstrip("/")
    bootstrap_url = base + "/index.php?fc=module&module=appnuno&controller=bootstrap"
    state_url = base + "/index.php?fc=module&module=appnuno&controller=state"
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch()
        admin = browser.new_context()
        page = admin.new_page()
        disabled_bootstrap = page.request.get(bootstrap_url)
        disabled_state = page.request.get(state_url)
        if disabled_bootstrap.status != 404 or disabled_bootstrap.text() != "":
            raise AssertionError("A disabled module must answer the bootstrap request with an empty 404.")
        if disabled_state.status != 404 or disabled_state.text() != "{}":
            raise AssertionError("A disabled module must answer the state request with an empty object and 404.")
        page.goto(base + "/admin-dev/")
        page.locator("#email, input[name='email']").first.fill("admin@example.test")
        page.locator("#passwd, input[name='passwd']").first.fill("appnuno-local")
        page.locator("#submit_login, button[name='submitLogin']").first.click()
        page.wait_for_load_state("networkidle")
        page.locator('a[href*="AdminAppnuno"]').first.click()
        page.wait_for_load_state("networkidle")
        page.locator('[data-status-switch="input-status"] button[data-value="1"]').click()
        page.locator('button[data-add-tab="default-tabs"]').click()
        tab = page.locator("#default-tabs .appnuno-tab").last
        tab.locator('[data-field="id"]').fill("home")
        tab.locator('[data-language-label]').first.fill("Home")
        tab.locator('[data-url-select]').select_option(label="Home")
        page.locator("#form-appnuno").evaluate("form => form.requestSubmit()")
        page.wait_for_url("**appnuno_saved=1**")
        expect(page.locator(".appnuno-alert-success")).to_be_visible()
        languages = page.locator("#appnuno-languages").text_content() or ""
        if "en-us" not in languages and "en-gb" not in languages:
            raise AssertionError("The admin language list did not include English.")
        page.reload()
        expect(page.locator("#default-tabs .appnuno-tab")).to_have_count(1)
        expect(page.locator("#input-status")).to_have_value("1")
        expect(page.locator("#default-tabs .appnuno-tab [data-field='id']")).to_have_value("home")
        store = browser.new_context()
        front = store.new_page()
        front.goto(base + "/")
        scripts = front.locator('script[src*="module/appnuno/bootstrap"], script[src*="controller=bootstrap"]')
        expect(scripts).to_have_count(1)
        bootstrap = front.request.get(scripts.first.get_attribute("src"))
        if not bootstrap.ok:
            raise AssertionError("The storefront bootstrap request failed.")
        body = bootstrap.text()
        if "window.AppNunoIntegration" not in body or "https://appnuno.com/sdk/v1/appnuno.js" not in body:
            raise AssertionError("The bootstrap script did not publish the integration or the AppNuno SDK.")
        state = front.request.get(state_url)
        if not state.ok:
            raise AssertionError("The state request failed while the module was enabled.")
        payload = state.json()
        if payload.get("cartCount") != 0 or payload.get("identity") is not None:
            raise AssertionError("A new visitor should have an empty cart and no identity.")
        product = front.locator("a.product-thumbnail[href*='appnuno-sample'], h3.product-title a[href*='appnuno-sample']")
        expect(product.first).to_be_visible()
        product.first.click()
        front.wait_for_load_state("networkidle")
        add_to_cart = front.locator("button[data-button-action='add-to-cart'], button.add-to-cart")
        expect(add_to_cart.first).to_be_visible()
        add_to_cart.first.click()
        front.wait_for_load_state("networkidle")
        cart_state = front.request.get(state_url).json()
        if cart_state.get("cartCount", 0) < 1:
            raise AssertionError("The cart count did not include the sample product.")
        front.goto(base + "/index.php?controller=authentication")
        front.locator("input[name='email']").first.fill("customer@example.test")
        front.locator("input[name='password']").first.fill("appnuno-local")
        front.locator("#submit-login, button[name='submitLogin']").first.click()
        front.wait_for_load_state("networkidle")
        logged_in = front.request.get(state_url).json()
        identity = logged_in.get("identity") or {}
        if identity.get("guard") != "customer" or identity.get("is_logged_in") is not True or not str(identity.get("identifier", "")).isdigit():
            raise AssertionError("A signed-in customer did not receive a customer identity.")
        identifier = identity["identifier"]
        front.goto(base + "/index.php?controller=my-account")
        front.locator("a[href*='mylogout']").first.click()
        front.wait_for_load_state("networkidle")
        logged_out = front.request.get(state_url).json()
        remembered = logged_out.get("identity") or {}
        if remembered.get("identifier") != identifier or remembered.get("is_logged_in") is not False or remembered.get("guard") != "customer":
            raise AssertionError("Logging out should keep the customer id and mark the visitor as logged out.")
        browser.close()
    print("PrestaShop admin and storefront checks passed.")


if __name__ == "__main__":
    main()
