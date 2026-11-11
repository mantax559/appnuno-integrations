import os
import time
from pathlib import Path

from playwright.sync_api import Page

from integration.adapter import REPOSITORY, StoreAdapter
from integration.browser import prepare_context
from integration.settings import choose_switch


class WordPressAdapter(StoreAdapter):
    name = "WordPress"
    base_url = os.environ.get("WORDPRESS_SITE_URL", "http://127.0.0.1:8080").rstrip("/")
    documentation_url = "https://appnuno.com/wordpress"
    version_key = "wordpress"
    sidebar_selector = "#adminmenuwrap"
    content_selector = "#wpbody-content"
    bootstrap_selector = 'script[src*="appnuno=bootstrap"]'
    special_path = "/shop/?on_sale=1"
    admin_user = "admin"
    admin_password = "appnuno-local"
    package = REPOSITORY / "downloads" / "woocommerce" / "11.x" / "appnuno-woocommerce-11.x.zip"

    def state_url(self) -> str:
        return self.base_url + "/?appnuno=state"

    def bootstrap_url(self) -> str:
        return self.base_url + "/?appnuno=bootstrap"

    def accepts_state_url(self, url: str) -> bool:
        return "appnuno=state" in url

    def login(self, page: Page, username: str) -> None:
        page.goto(self.base_url + "/wp-login.php")
        page.locator("#user_login").fill(username)
        page.locator("#user_pass").fill(self.admin_password)
        page.locator("#wp-submit").click()
        page.locator("#wpadminbar, #adminmenu").first.wait_for()

    def login_admin(self, page: Page) -> None:
        self.login(page, self.admin_user)

    def open_settings(self, page: Page) -> None:
        page.goto(self.base_url + "/wp-admin/admin.php?page=appnuno")
        page.locator(".appnuno-settings").wait_for()

    def switch_admin_language(self, page: Page, code: str) -> None:
        page.goto(self.base_url + "/wp-admin/profile.php")
        select = page.locator("#locale")
        if code == self.lithuanian_code:
            select.select_option("lt_LT")
        else:
            values = select.locator("option").evaluate_all("options => options.map(option => option.value)")
            chosen = next((value for value in ("en_US", "en", "") if value in values), None)
            if chosen is None:
                raise AssertionError(f"The profile has no English locale: {values}.")
            select.select_option(chosen)
        page.locator("#submit").click()
        page.locator("#wpadminbar").wait_for()

    def switch_storefront_language(self, page: Page, code: str) -> None:
        locale = "lt_LT" if code == self.lithuanian_code else "en_GB"
        page.context.add_cookies([{"name": "appnuno_test_locale", "value": locale, "url": self.base_url}])

    def add_two_products(self, page: Page) -> None:
        page.goto(self.base_url + "/product/appnuno-sample/")
        product_id = page.locator("button[name='add-to-cart']").get_attribute("value")
        if not product_id:
            raise AssertionError("The sample product could not be added to the cart.")
        added = page.evaluate(
            """async productId => {
                const response = await fetch('/?add-to-cart=' + productId + '&quantity=2', { credentials: 'same-origin', signal: AbortSignal.timeout(15000) });
                return response.status;
            }""",
            product_id,
        )
        if added != 200:
            raise AssertionError(f"WooCommerce did not accept the cart request: HTTP {added}.")

    def register_customer(self, page: Page) -> None:
        page.goto(self.base_url + "/my-account/")
        page.locator("#reg_email").fill(f"appnuno-ci-{int(time.time())}@example.test")
        password = page.locator("#reg_password")
        if password.count():
            password.click()
            password.press_sequentially("R7!vQ3#nL9@xT5$wP2", delay=5)
            page.locator(".woocommerce-password-strength.strong").wait_for()
        page.locator("button[name='register']").click()
        page.locator(".woocommerce-error, a[href*='customer-logout'], .woocommerce-MyAccount-navigation").first.wait_for()
        if page.locator(".woocommerce-error").count() or "stronger password" in (page.locator("body").inner_text() or ""):
            raise AssertionError("WooCommerce did not accept the customer registration: " + page.locator("body").inner_text()[:500])

    def logout_customer(self, page: Page) -> None:
        logout = page.locator("a[href*='customer-logout']")
        if not logout.count():
            page.goto(self.base_url + "/my-account/")
            logout = page.locator("a[href*='customer-logout']")
        logout.first.click()
        page.locator("#username, .woocommerce-message").first.wait_for()

    def cart_page_count(self, page: Page) -> int:
        page.goto(self.absolute(self.cart_path))
        page.locator("body").wait_for()
        values = page.locator("form.woocommerce-cart-form input.qty").evaluate_all("inputs => inputs.map(input => Number(input.value) || 0)")
        return sum(values)

    def deny_limited_save(self, page: Page) -> None:
        context = page.context.browser.new_context(viewport={"width": 1440, "height": 1000})
        prepare_context(context, self)
        limited = context.new_page()
        try:
            limited.goto(self.base_url + "/wp-login.php")
            limited.locator("#user_login").fill("limited")
            limited.locator("#user_pass").fill(self.admin_password)
            limited.locator("#wp-submit").click()
            limited.goto(self.base_url + "/wp-admin/admin.php?page=appnuno")
            limited.locator("body").wait_for()
            if limited.locator("#form-appnuno").count():
                choose_switch(limited, "input-status", "0")
                limited.locator('button[form="form-appnuno"]').click()
                limited.locator("body").wait_for()
            text = (limited.locator("body").inner_text() or "").lower()
            if "permission" not in text and "not allowed" not in text and "access denied" not in text:
                raise AssertionError("A user without modify permission was not rejected at " + limited.url + ": " + text[:500])
            if limited.locator(".appnuno-alert-success").count():
                raise AssertionError("A user without modify permission saved the module settings.")
        finally:
            context.close()

    def uninstall(self, page: Page) -> None:
        page.goto(self.base_url + "/wp-admin/plugins.php")
        row = page.locator("tr[data-slug='appnuno'], tr[data-plugin='appnuno/appnuno.php']").first
        row.wait_for()
        deactivate = row.locator(".deactivate a")
        if deactivate.count():
            deactivate.click()
            page.locator("#adminmenu").wait_for()
            row = page.locator("tr[data-slug='appnuno'], tr[data-plugin='appnuno/appnuno.php']").first
        page.once("dialog", lambda dialog: dialog.accept())
        row.locator(".delete a").click()
        confirmation = page.locator('input[type="submit"][name="submit"][value="Yes, delete these files and data"]')
        page.wait_for_function(
            """() => document.querySelector('input[type="submit"][name="submit"][value="Yes, delete these files and data"]')
                || document.body.innerText.includes("AppNuno was successfully deleted.")"""
        )
        if confirmation.count():
            confirmation.wait_for()
            with page.expect_response(
                lambda response: response.request.method == "POST" and "/wp-admin/plugins.php" in response.url,
                timeout=30000,
            ) as deleted:
                confirmation.click()
            if deleted.value.status >= 400:
                raise AssertionError(f"WordPress did not delete the AppNuno plugin: HTTP {deleted.value.status}.")
        else:
            page.get_by_text("AppNuno was successfully deleted.", exact=True).wait_for()
        page.locator("tr[data-slug='appnuno'], tr[data-plugin='appnuno/appnuno.php']").wait_for(state="detached")

    def install(self, page: Page) -> None:
        if not Path(self.package).is_file():
            raise FileNotFoundError("Build the WooCommerce package before reinstalling the plugin: " + str(self.package))
        page.goto(self.base_url + "/wp-admin/plugin-install.php?tab=upload")
        page.locator("#pluginzip").set_input_files(str(self.package))
        page.locator("#install-plugin-submit").click()
        activate = page.locator("a[href*='action=activate'][href*='appnuno']")
        try:
            activate.first.wait_for()
        except Exception:
            raise AssertionError("WordPress did not offer to activate the uploaded plugin: " + (page.locator("#wpbody-content").inner_text() or "")[:500])
        activate.first.click()
        page.locator("#adminmenu, .wrap").first.wait_for()
        if page.locator("tr[data-slug='appnuno'].active, tr[data-plugin='appnuno/appnuno.php'].active").count() == 0 and "Plugin activated" not in page.locator("body").inner_text():
            page.goto(self.base_url + "/wp-admin/plugins.php")
            if not page.locator("tr[data-plugin='appnuno/appnuno.php'].active, tr[data-slug='appnuno'].active").count():
                raise AssertionError("WordPress did not activate the reinstalled AppNuno plugin.")
