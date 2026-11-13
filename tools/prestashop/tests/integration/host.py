import os
import subprocess
import time
from pathlib import Path

from playwright.sync_api import Page

from integration.adapter import StoreAdapter
from integration.browser import prepare_context
from integration.settings import choose_switch


class PrestaShopAdapter(StoreAdapter):
    name = "PrestaShop"
    base_url = os.environ.get("PRESTASHOP_SITE_URL", "http://127.0.0.1:8088").rstrip("/")
    prestashop_tag = os.environ.get("PRESTASHOP_TAG", "8.2-apache")
    documentation_url = "https://appnuno.com/docs"
    version_key = "prestashop"
    sidebar_selector = ".nav-bar"
    content_selector = "#main-div"
    bootstrap_selector = 'script[src*="controller=bootstrap"], script[src*="module/appnuno/bootstrap"]'
    special_path = "/prices-drop"
    admin_email = "admin@example.test"
    admin_password = "appnuno-local"
    limited_email = "limited@example.test"

    def state_url(self) -> str:
        return self.base_url + "/index.php?fc=module&module=appnuno&controller=state"

    def bootstrap_url(self) -> str:
        return self.base_url + "/index.php?fc=module&module=appnuno&controller=bootstrap"

    def accepts_state_url(self, url: str) -> bool:
        return "controller=state" in url or url.rstrip("/").endswith("/state")

    def login(self, page: Page, email: str) -> None:
        page.goto(self.base_url + "/admin-dev/", wait_until="domcontentloaded", timeout=90000)
        page.locator("#email, input[name='email']").first.fill(email)
        page.locator("#passwd, input[name='passwd']").first.fill(self.admin_password)
        page.locator("#submit_login, button[name='submitLogin']").first.click()
        page.locator(".nav-bar li.link-levelone.has_submenu > a").filter(has_text="Modules").first.wait_for(state="visible", timeout=90000)

    def _prestashop_container(self) -> str:
        container = os.environ.get("PRESTASHOP_CONTAINER", "")
        if container:
            return container
        listed = subprocess.run(["docker", "ps", "--format", "{{.Names}}"], capture_output=True, text=True, check=True)
        names = [name for name in listed.stdout.splitlines() if name.endswith("prestashop-1")]
        if len(names) != 1:
            raise AssertionError(f"Expected one running PrestaShop container: {names}.")
        return names[0]

    def login_admin(self, page: Page) -> None:
        self.login(page, self.admin_email)

    def open_settings(self, page: Page) -> None:
        page.goto(self.base_url + "/admin-dev/", wait_until="domcontentloaded", timeout=90000)
        link = page.locator('a[href*="AdminAppnuno"]').first
        link.wait_for(state="attached", timeout=90000)
        href = link.get_attribute("href")
        if not href:
            raise AssertionError("PrestaShop did not generate an AppNuno admin URL.")
        page.goto(href, wait_until="domcontentloaded", timeout=90000)
        page.locator(".appnuno-settings").wait_for()

    def switch_admin_language(self, page: Page, code: str) -> None:
        iso = "lt" if code == self.lithuanian_code else "en"
        self.set_employee_language(iso)
        page.context.clear_cookies()
        self.login_admin(page)

    def set_employee_language(self, iso: str) -> None:
        container = self._prestashop_container()
        script = Path(__file__).resolve().parents[1] / "docker" / "set-employee-language.php"
        subprocess.run(["docker", "cp", str(script), f"{container}:/tmp/set-employee-language.php"], check=True)
        completed = subprocess.run(["docker", "exec", container, "php", "/tmp/set-employee-language.php", iso], capture_output=True, text=True)
        if completed.returncode != 0:
            raise AssertionError("PrestaShop did not change the employee language: " + completed.stderr)

    def storefront_url(self, code: str, path: str) -> str:
        if code != self.lithuanian_code and self.prestashop_tag == "9.0-apache":
            return self.absolute(path)
        locale = "lt" if code == self.lithuanian_code else "en"
        localized_path = path if path.startswith("/") else "/" + path
        for prefix in ("en", "lt"):
            if localized_path == "/" + prefix:
                localized_path = "/"
                break
            if localized_path.startswith("/" + prefix + "/"):
                localized_path = localized_path[len(prefix) + 1:]
                break
        if localized_path == "/":
            return self.base_url + "/" + locale + "/"
        return self.base_url + "/" + locale + localized_path

    def switch_storefront_language(self, page: Page, code: str) -> None:
        return None

    def add_two_products(self, page: Page) -> None:
        container = self._prestashop_container()
        script = Path(__file__).resolve().parents[1] / "docker" / "get-product-url.php"
        subprocess.run(["docker", "cp", str(script), f"{container}:/tmp/get-product-url.php"], check=True)
        completed = subprocess.run(
            ["docker", "exec", container, "php", "/tmp/get-product-url.php"],
            capture_output=True,
            text=True,
        )
        if completed.returncode:
            raise AssertionError("PrestaShop did not generate the sample product URL: " + completed.stderr)
        product_url = completed.stdout.strip()
        if not product_url.startswith(self.base_url):
            raise AssertionError(f"PrestaShop generated an unexpected sample product URL: {product_url}.")
        page.goto(product_url)
        page.locator("#add-to-cart-or-refresh").wait_for()
        result = page.evaluate(
            """async stateUrl => {
                const form = document.querySelector('#add-to-cart-or-refresh');
                const body = new URLSearchParams(new FormData(form));
                body.set('qty', '2');
                body.set('add', '1');
                body.set('action', 'update');
                body.set('ajax', '1');
                const response = await fetch(form.action, { method: 'POST', body, credentials: 'same-origin', signal: AbortSignal.timeout(15000) });
                const posted = await response.text();
                const state = await fetch(stateUrl, { credentials: 'same-origin', cache: 'no-store', signal: AbortSignal.timeout(15000) });
                return { status: response.status, posted: posted.slice(0, 400), cart: await state.text() };
            }""",
            self.state_url(),
        )
        if '"cartCount":2' not in result["cart"] and '"cartCount": 2' not in result["cart"]:
            raise AssertionError(f"The sample product was not added twice: {result}.")

    def register_customer(self, page: Page) -> None:
        registration_path = "/login?create_account=1" if self.prestashop_tag.startswith("1.7.") else "/registration"
        page.goto(self.storefront_url(self.english_code, registration_path))
        gender = page.locator("input[name='id_gender']")
        if gender.count():
            gender.first.check()
        page.locator("input[name='firstname']").fill("AppNuno")
        page.locator("input[name='lastname']").fill("CI Customer")
        page.locator("form#customer-form input[name='email']").fill(f"appnuno-ci-{int(time.time())}@example.test")
        page.locator("input[name='password']").fill("Appnuno-CI-1")
        for name in ("customer_privacy", "psgdpr"):
            checkbox = page.locator(f"input[name='{name}']")
            if checkbox.count():
                checkbox.first.check()
        page.locator("button[data-link-action='save-customer']").click()
        page.locator("a[href*='my-account'], a[href*='mylogout'], .alert-danger, .invalid-feedback").first.wait_for()
        if page.locator(".alert-danger, .invalid-feedback").count() and "my-account" not in page.url:
            raise AssertionError("PrestaShop did not accept the customer registration: " + " ".join(page.locator(".alert-danger, .invalid-feedback").all_inner_texts()))

    def logout_customer(self, page: Page) -> None:
        page.goto(self.base_url + "/?mylogout=")
        page.locator("body").wait_for()

    def cart_page_count(self, page: Page) -> int:
        page.goto(self.absolute(self.cart_path))
        page.locator("body").wait_for()
        values = page.locator(".cart-overview input.js-cart-line-product-quantity, .cart-items input.js-cart-line-product-quantity, input.js-cart-line-product-quantity").evaluate_all(
            "inputs => inputs.map(input => Number(input.value) || 0)"
        )
        return sum(values)

    def deny_limited_save(self, page: Page) -> None:
        context = page.context.browser.new_context(viewport={"width": 1440, "height": 1000})
        prepare_context(context, self)
        limited = context.new_page()
        try:
            self.login(limited, self.limited_email)
            limited.goto(self.base_url + "/admin-dev/")
            link = limited.locator('a[href*="AdminAppnuno"]')
            if link.count():
                href = link.first.get_attribute("href")
                if href:
                    limited.goto(href, wait_until="domcontentloaded")
                limited.locator("body").wait_for()
            if limited.locator("#form-appnuno").count():
                choose_switch(limited, "input-status", "0")
                limited.locator('button[form="form-appnuno"]').click()
                limited.locator(".appnuno-alert, .alert-danger, .nav-bar").first.wait_for()
            text = (limited.locator("body").inner_text() or "").lower()
            if "permission" not in text and "access denied" not in text and "not allowed" not in text:
                raise AssertionError("A user without modify permission was not rejected: " + text[:500])
            if limited.locator(".appnuno-alert-success").count():
                raise AssertionError("A user without modify permission saved the module settings.")
        finally:
            context.close()

    def run_module_action(self, action: str) -> None:
        container = self._prestashop_container()
        script = Path(__file__).resolve().parents[1] / "docker" / "module-action.php"
        subprocess.run(["docker", "cp", str(script), f"{container}:/tmp/module-action.php"], check=True)
        completed = subprocess.run(["docker", "exec", container, "php", "/tmp/module-action.php", action], capture_output=True, text=True)
        if completed.returncode != 0:
            raise AssertionError(f"PrestaShop did not {action} the module: " + completed.stderr)

    def uninstall(self, page: Page) -> None:
        self.run_module_action("uninstall")

    def install(self, page: Page) -> None:
        self.run_module_action("install")
