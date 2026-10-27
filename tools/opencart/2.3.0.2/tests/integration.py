import os
import shutil
import subprocess
import tempfile
import threading
import time
import urllib.request
import zipfile
from urllib.parse import parse_qs, urlparse
from pathlib import Path

import pymysql
from playwright.sync_api import Page, Response, sync_playwright
from pyftpdlib.authorizers import DummyAuthorizer
from pyftpdlib.handlers import FTPHandler
from pyftpdlib.servers import FTPServer


REPOSITORY = Path(__file__).resolve().parents[4]
PACKAGE = REPOSITORY / "downloads" / "opencart" / "2.x" / "appnuno-opencart-2.x.ocmod.zip"
OPENCART_VERSION = os.environ.get("OPENCART_VERSION", "2.3.0.2")
SUPPORTED_OPENCART_VERSIONS = {"2.3.0.1", "2.3.0.2"}
if OPENCART_VERSION not in SUPPORTED_OPENCART_VERSIONS:
    raise ValueError(f"Unsupported OpenCart test version: {OPENCART_VERSION}")
OPENCART_ARCHIVE_URL = f"https://github.com/opencart/opencart/releases/download/{OPENCART_VERSION}/{OPENCART_VERSION}-compiled.zip"
BASE_URL = "http://127.0.0.1:8080"
DB_HOST = os.environ.get("OPENCART_DB_HOST", "127.0.0.1")
DB_USER = os.environ.get("OPENCART_DB_USER", "root")
DB_PASSWORD = os.environ.get("OPENCART_DB_PASSWORD", "root")
DB_NAME = os.environ.get("OPENCART_DB_NAME", "opencart")
ADMIN_USER = "admin"
ADMIN_PASSWORD = "opencart-ci-password"
FTP_USER = "opencart-ci"
FTP_PASSWORD = "opencart-ci-password"
FTP_PORT = 2121


def capture_screenshot(page: Page, artifact_dir: Path, filename: str) -> None:
    page.screenshot(path=str(artifact_dir / filename), full_page=True, animations="disabled")


def prepare_store(workspace: Path) -> Path:
    archive_path = workspace / "opencart.zip"
    urllib.request.urlretrieve(OPENCART_ARCHIVE_URL, archive_path)
    extracted = workspace / "upstream"
    extracted.mkdir()
    with zipfile.ZipFile(archive_path) as archive:
        archive.extractall(extracted)
    candidates = list(extracted.rglob("config-dist.php"))
    web_source = next(path.parent for path in candidates if (path.parent / "admin" / "config-dist.php").is_file() and (path.parent / "install").is_dir())
    web_root = workspace / "store"
    shutil.copytree(web_source, web_root, ignore=shutil.ignore_patterns(".git"))
    shutil.copyfile(web_root / "config-dist.php", web_root / "config.php")
    shutil.copyfile(web_root / "admin" / "config-dist.php", web_root / "admin" / "config.php")
    return web_root


def wait_for_store(process: subprocess.Popen, log_path: Path) -> None:
    deadline = time.monotonic() + 60
    while time.monotonic() < deadline:
        if process.poll() is not None:
            raise RuntimeError("OpenCart PHP server stopped before becoming ready.\n" + log_path.read_text(errors="replace"))
        try:
            with urllib.request.urlopen(BASE_URL, timeout=2):
                return
        except Exception:
            time.sleep(1)
    raise RuntimeError("OpenCart PHP server did not become ready.\n" + log_path.read_text(errors="replace"))


def configure_ftp() -> None:
    connection = pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASSWORD, database=DB_NAME, autocommit=True)
    try:
        with connection.cursor() as cursor:
            values = {
                "config_ftp_status": "1",
                "config_ftp_hostname": "127.0.0.1",
                "config_ftp_port": str(FTP_PORT),
                "config_ftp_username": FTP_USER,
                "config_ftp_password": FTP_PASSWORD,
                "config_ftp_root": ""
            }
            for key, value in values.items():
                cursor.execute("UPDATE oc_setting SET value = %s WHERE store_id = 0 AND code = 'config' AND `key` = %s", (value, key))
    finally:
        connection.close()


def configure_secondary_language() -> None:
    connection = pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASSWORD, database=DB_NAME, autocommit=True)
    with connection.cursor() as cursor:
        cursor.execute(
            "INSERT INTO oc_language (name, code, locale, image, directory, sort_order, status) VALUES (%s, %s, %s, %s, %s, %s, %s)",
            ("Lithuanian", "lt-lt", "lt-LT,lt_LT.UTF-8,lt_LT,lt-lt,lithuanian", "lt-lt.png", "lt-lt", 2, 1),
        )
    connection.close()


def start_ftp_server(web_root: Path) -> FTPServer:
    authorizer = DummyAuthorizer()
    authorizer.add_user(FTP_USER, FTP_PASSWORD, str(web_root), perm="elradfmwMT")
    handler = FTPHandler
    handler.authorizer = authorizer
    server = FTPServer(("127.0.0.1", FTP_PORT), handler)
    thread = threading.Thread(target=server.serve_forever, kwargs={"timeout": 0.2, "blocking": True, "handle_exit": False}, daemon=True)
    thread.start()
    return server


def install_store(page: Page) -> None:
    page.goto(BASE_URL)
    page.locator('input[type="submit"]').click()
    page.wait_for_url("**route=install/step_2**")
    if page.locator(".alert-danger").count():
        raise AssertionError("OpenCart pre-installation checks reported an error: " + page.locator(".alert-danger").inner_text())
    page.locator('input[type="submit"]').click()
    page.wait_for_url("**route=install/step_3**")
    page.locator('select[name="db_driver"]').select_option("mysqli")
    page.locator('input[name="db_hostname"]').fill(DB_HOST)
    page.locator('input[name="db_username"]').fill(DB_USER)
    page.locator('input[name="db_password"]').fill(DB_PASSWORD)
    page.locator('input[name="db_database"]').fill(DB_NAME)
    page.locator('input[name="db_port"]').fill("3306")
    page.locator('input[name="db_prefix"]').fill("oc_")
    page.locator('input[name="username"]').fill(ADMIN_USER)
    page.locator('input[name="password"]').fill(ADMIN_PASSWORD)
    page.locator('input[name="email"]').fill("opencart-ci@example.test")
    page.locator('input[type="submit"]').click()
    page.wait_for_url("**route=install/step_4**", timeout=30000)


def login_admin(page: Page, web_root: Path) -> None:
    shutil.rmtree(web_root / "install")
    page.goto(BASE_URL + "/admin/")
    page.locator('input[name="username"]').fill(ADMIN_USER)
    page.locator('input[name="password"]').fill(ADMIN_PASSWORD)
    page.locator('button[type="submit"], input[type="submit"]').first.click()
    page.locator("#column-left").wait_for()


def admin_token(page: Page) -> str:
    from urllib.parse import parse_qs, urlparse

    token = parse_qs(urlparse(page.url).query).get("token", [])
    if not token:
        raise AssertionError("OpenCart admin token was not present after login.")
    return token[0]


def upload_extension(page: Page, token: str) -> None:
    page.goto(BASE_URL + "/admin/index.php?route=extension/installer&token=" + token)
    with page.expect_file_chooser() as chooser:
        page.locator("#button-upload").click()
    chooser.value.set_files(str(PACKAGE))
    page.wait_for_function("document.querySelector('#progress-bar').style.width === '100%' && document.querySelector('#progress-bar').classList.contains('progress-bar-success')", timeout=90000)
    if page.locator("#progress-text .text-danger").count():
        raise AssertionError("OpenCart extension uploader failed: " + page.locator("#progress-text").inner_text())


def refresh_modifications(page: Page, token: str) -> None:
    page.goto(BASE_URL + "/admin/index.php?route=extension/modification&token=" + token)
    with page.expect_navigation():
        page.locator(".page-header .btn-info").click()
    page.get_by_text("AppNuno OpenCart 2.x", exact=True).wait_for()


def install_module(page: Page, token: str) -> None:
    page.goto(BASE_URL + "/admin/index.php?route=extension/extension&token=" + token)
    module_filter = page.locator('select[name="type"]')
    module_option = module_filter.locator("option").filter(has_text="Modules").first
    module_filter.select_option(module_option.get_attribute("value"))
    module_row = page.locator("#extension tr").filter(has_text="AppNuno Native Navigation").first
    module_row.wait_for()
    module_row.locator("a.btn-success").click()
    module_row.locator("a.btn-danger").wait_for()
    page.goto(BASE_URL + "/admin/index.php?route=extension/module/appnuno&token=" + token)
    page.locator("#default-tabs .appnuno-tab").first.wait_for()


def save_and_check_tab(page: Page, artifact_dir: Path) -> None:
    default_panel = page.locator("#default-tabs").locator("xpath=../..")
    if not default_panel.locator("#cart-tab-id").count():
        raise AssertionError("Cart badge selection is not inside the Default tabs panel.")
    for index, labels in enumerate((("Home", "Pradžia"), ("Cart", "Krepšelis"))):
        tab = page.locator("#default-tabs .appnuno-tab").nth(index)
        tab.locator('[data-language-label="en-gb"]').fill(labels[0])
        tab.locator('[data-language-label="lt-lt"]').fill(labels[1])
    page.locator('button[data-add-tab="default-tabs"]').click()
    tab = page.locator("#default-tabs .appnuno-tab").last
    tab.locator('[data-field="id"]').fill("ci-added-tab")
    tab.locator('[data-language-label="en-gb"]').fill("CI Added Tab")
    tab.locator('[data-language-label="lt-lt"]').fill("CI pridėtas skirtukas")
    tab.locator('[data-url-select]').select_option("__custom__")
    tab.locator('[data-language-url="en-gb"]').fill("/en/contact")
    tab.locator('[data-language-url="lt-lt"]').fill("/lt/kontaktai")
    tab.locator('[data-field="icon"]').fill("home-outline")
    tab.locator('[data-field="activeIcon"]').fill("home")
    page.locator("#cart-tab-id").select_option("ci-added-tab")
    page.locator("#input-status").select_option("1")
    page.locator("#back-enabled").uncheck()
    page.locator("#back-placement").select_option("bar")
    page.locator("#glass-enabled").check()
    page.locator("#glass-color-scheme").select_option("dark")
    page.locator("#glass-style").select_option("clear")
    page.locator("#glass-animate").uncheck()
    page.locator("#glass-duration").fill("0.7")
    page.locator("#glass-interactive").uncheck()
    page.locator("#glass-tint-color").fill("#123456")
    page.locator("#glass-tint-alpha").fill("0.4")
    page.locator("#light-background").fill("#112233")
    page.locator("#light-content").fill("#223344")
    page.locator("#dark-background").fill("#334455")
    page.locator("#dark-content").fill("#445566")
    page.locator("#add-route").click()
    route = page.locator(".appnuno-route").last
    route.locator("select").select_option("checkout/cart")
    route.locator('button:has-text("Add tab to this route")').click()
    route_tab = route.locator(".appnuno-tab").last
    route_tab.locator('[data-field="id"]').fill("ci-cart-route-tab")
    route_tab.locator('[data-language-label="en-gb"]').fill("Cart route tab")
    route_tab.locator('[data-language-label="lt-lt"]').fill("Krepšelio skirtukas")
    route_tab.locator('[data-url-select]').select_option("__custom__")
    route_tab.locator('[data-language-url="en-gb"]').fill("/en/cart")
    route_tab.locator('[data-language-url="lt-lt"]').fill("/lt/krepselis")
    route_tab.locator('[data-field="icon"]').fill("cart-outline")
    route_tab.locator('[data-field="activeIcon"]').fill("cart")
    capture_screenshot(page, artifact_dir, "02-admin-settings-before-save.png")
    page.locator('button[form="form-appnuno"]').click()
    page.locator(".alert-success").filter(has_text="modified AppNuno Native Navigation").wait_for(timeout=15000)
    page.reload()
    saved_tab = page.locator("#default-tabs .appnuno-tab").nth(2)
    saved_tab.wait_for()
    if saved_tab.locator('[data-field="id"]').input_value() != "ci-added-tab":
        raise AssertionError("The newly added default tab ID did not persist after reloading the settings page.")
    if saved_tab.locator('[data-language-label="en-gb"]').input_value() != "CI Added Tab":
        raise AssertionError("The newly added English tab label did not persist after reloading the settings page.")
    if saved_tab.locator('[data-language-label="lt-lt"]').input_value() != "CI pridėtas skirtukas":
        raise AssertionError("The newly added Lithuanian tab label did not persist after reloading the settings page.")
    if saved_tab.locator('[data-language-url="lt-lt"]').input_value() != "/lt/kontaktai":
        raise AssertionError("The newly added Lithuanian tab URL did not persist after reloading the settings page.")
    if page.locator("#cart-tab-id").input_value() != "ci-added-tab":
        raise AssertionError("The cart badge tab selection did not persist after reloading the settings page.")
    route = page.locator(".appnuno-route").first
    if route.locator('[data-field="route"]').input_value() != "checkout/cart":
        raise AssertionError("The route-specific tab set did not persist after reloading the settings page.")
    saved_route_tab = route.locator(".appnuno-tab").first
    if saved_route_tab.locator('[data-language-label="lt-lt"]').input_value() != "Krepšelio skirtukas":
        raise AssertionError("The localized route tab label did not persist after reloading the settings page.")
    if saved_route_tab.locator('[data-language-url="lt-lt"]').input_value() != "/lt/krepselis":
        raise AssertionError("The localized route tab URL did not persist after reloading the settings page.")
    capture_screenshot(page, artifact_dir, "03-admin-settings-after-save.png")


def get_bootstrap_payload(page: Page, responses: list[Response], expected_route: str) -> dict:
    page.wait_for_function("window.AppNunoIntegration && window.AppNunoIntegration.config && window.AppNunoIntegration.navigation")
    bootstrap_scripts = page.locator('script[src*="appnuno"][src*="bootstrap"]')
    if bootstrap_scripts.count() != 1:
        raise AssertionError("The storefront must include exactly one AppNuno bootstrap script.")
    bootstrap_url = bootstrap_scripts.first.evaluate("script => script.src")
    bootstrap_responses = [
        response
        for response in responses
        if parse_qs(urlparse(response.url).query).get("route", []) == ["extension/module/appnuno/bootstrap"]
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
    if payload["route"] != expected_route:
        raise AssertionError(f"Expected bootstrap route {expected_route}, got {payload['route']}.")
    if parse_qs(urlparse(payload["stateUrl"]).query).get("route", []) != ["extension/module/appnuno/state"]:
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


def check_default_bootstrap(payload: dict, language_code: str, expected_label: str, expected_url: str) -> None:
    configuration = payload["config"]
    if configuration["cartTabId"] != "ci-added-tab":
        raise AssertionError("The bootstrap configuration did not publish the selected cart badge tab ID.")
    if len(configuration["defaultTabs"]) != 3:
        raise AssertionError(f"The bootstrap configuration has an unexpected default tab set: {configuration['defaultTabs']}.")
    for tab_id, labels in (("home", {"en-gb": "Home", "lt-lt": "Pradžia"}), ("cart", {"en-gb": "Cart", "lt-lt": "Krepšelis"})):
        configured_tab = next(tab for tab in configuration["defaultTabs"] if tab["id"] == tab_id)
        if configured_tab.get("labels") != labels:
            raise AssertionError(f"The bootstrap configuration did not retain translations for {tab_id}: {configured_tab}.")
    configured_tab = next(tab for tab in configuration["defaultTabs"] if tab["id"] == "ci-added-tab")
    if configured_tab.get("labels") != {"en-gb": "CI Added Tab", "lt-lt": "CI pridėtas skirtukas"}:
        raise AssertionError(f"The bootstrap configuration did not retain all default tab translations: {configured_tab}.")
    if configured_tab.get("urls") != {"en-gb": "/en/contact", "lt-lt": "/lt/kontaktai"}:
        raise AssertionError(f"The bootstrap configuration did not retain all default tab URLs: {configured_tab}.")
    expected_default_tabs = {
        "home": ("Home", "Pradžia", "/", "home-outline", "home"),
        "cart": ("Cart", "Krepšelis", "/index.php?route=checkout/cart", "cart-outline", "cart"),
        "ci-added-tab": ("CI Added Tab", "CI pridėtas skirtukas", "/en/contact", "home-outline", "home"),
    }
    if len(payload["navigation"]["tabs"]) != len(expected_default_tabs):
        raise AssertionError(f"The resolved bootstrap tab set is incomplete: {payload['navigation']['tabs']}.")
    for navigation_tab in payload["navigation"]["tabs"]:
        english_label, lithuanian_label, english_url, icon, active_icon = expected_default_tabs[navigation_tab["id"]]
        if navigation_tab.get("label") != (english_label if language_code == "en-gb" else lithuanian_label):
            raise AssertionError(f"A default tab label is wrong for {language_code}: {navigation_tab}.")
        if navigation_tab.get("url") != (english_url if navigation_tab["id"] != "ci-added-tab" or language_code == "en-gb" else "/lt/kontaktai"):
            raise AssertionError(f"A default tab URL is wrong for {language_code}: {navigation_tab}.")
        if navigation_tab.get("icon") != icon or navigation_tab.get("activeIcon") != active_icon:
            raise AssertionError(f"A default tab icon is wrong: {navigation_tab}.")
    route_tabs = configuration["routes"].get("checkout/cart")
    if not route_tabs or route_tabs[0].get("id") != "ci-cart-route-tab":
        raise AssertionError("The bootstrap configuration did not include the saved checkout/cart tab set.")
    tab = next(tab for tab in payload["navigation"]["tabs"] if tab["id"] == "ci-added-tab")
    if tab.get("label") != expected_label or tab.get("url") != expected_url:
        raise AssertionError(f"The {language_code} default tab was not localized as expected: {tab}.")
    if "labels" in tab or "urls" in tab:
        raise AssertionError("The resolved navigation tab still contains untranslated labels or URLs.")
    if tab.get("icon") != "home-outline" or tab.get("activeIcon") != "home":
        raise AssertionError(f"The default tab icons were not preserved in the bootstrap payload: {tab}.")
    if tab.get("badge") != payload["state"]["cartCount"]:
        raise AssertionError("The selected cart badge tab did not receive the server cart count.")
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


def check_route_bootstrap(payload: dict, language_code: str, expected_label: str, expected_url: str) -> None:
    if payload["route"] != "checkout/cart":
        raise AssertionError(f"Expected the checkout/cart bootstrap route, got {payload['route']}.")
    configured_tabs = payload["config"]["routes"]["checkout/cart"]
    if len(configured_tabs) != 1 or configured_tabs[0].get("labels") != {"en-gb": "Cart route tab", "lt-lt": "Krepšelio skirtukas"}:
        raise AssertionError(f"The saved checkout/cart route translations are incorrect: {configured_tabs}.")
    if configured_tabs[0].get("urls") != {"en-gb": "/en/cart", "lt-lt": "/lt/krepselis"}:
        raise AssertionError(f"The saved checkout/cart localized URLs are incorrect: {configured_tabs}.")
    tabs = payload["navigation"]["tabs"]
    if len(tabs) != 1:
        raise AssertionError(f"The route-specific tab set did not replace the defaults: {tabs}.")
    tab = tabs[0]
    if tab.get("id") != "ci-cart-route-tab" or tab.get("label") != expected_label or tab.get("url") != expected_url:
        raise AssertionError(f"The {language_code} checkout/cart tab was not resolved correctly: {tab}.")
    if tab.get("icon") != "cart-outline" or tab.get("activeIcon") != "cart":
        raise AssertionError(f"The route-specific tab icons were not preserved: {tab}.")
    if "labels" in tab or "urls" in tab:
        raise AssertionError("The resolved route tab still contains untranslated labels or URLs.")


def switch_storefront_language(page: Page, language_code: str) -> None:
    page.locator("#form-language .dropdown-toggle").click()
    with page.expect_navigation():
        page.locator(f'#form-language .language-select[name="{language_code}"]').click()


def check_storefront(page: Page, artifact_dir: Path) -> None:
    responses: list[Response] = []
    page.on("response", lambda response: responses.append(response))
    page.goto(BASE_URL + "/")
    english_payload = get_bootstrap_payload(page, responses, "common/home")
    check_default_bootstrap(english_payload, "en-gb", "CI Added Tab", "/en/contact")
    capture_screenshot(page, artifact_dir, "04-storefront-desktop.png")
    page.set_viewport_size({"width": 390, "height": 844})
    page.reload()
    mobile_payload = get_bootstrap_payload(page, responses, "common/home")
    check_default_bootstrap(mobile_payload, "en-gb", "CI Added Tab", "/en/contact")
    capture_screenshot(page, artifact_dir, "05-storefront-mobile.png")
    switch_storefront_language(page, "lt-lt")
    lithuanian_payload = get_bootstrap_payload(page, responses, "common/home")
    check_default_bootstrap(lithuanian_payload, "lt-lt", "CI pridėtas skirtukas", "/lt/kontaktai")
    capture_screenshot(page, artifact_dir, "06-storefront-lithuanian.png")
    page.goto(BASE_URL + "/index.php?route=checkout/cart")
    lithuanian_route_payload = get_bootstrap_payload(page, responses, "checkout/cart")
    check_route_bootstrap(lithuanian_route_payload, "lt-lt", "Krepšelio skirtukas", "/lt/krepselis")
    capture_screenshot(page, artifact_dir, "07-storefront-lithuanian-cart-route.png")
    switch_storefront_language(page, "en-gb")
    english_route_payload = get_bootstrap_payload(page, responses, "checkout/cart")
    check_route_bootstrap(english_route_payload, "en-gb", "Cart route tab", "/en/cart")


def main() -> None:
    if not PACKAGE.is_file():
        raise FileNotFoundError("Build the OCMOD package before running the integration test: " + str(PACKAGE))
    artifact_dir = Path(os.environ.get("OPENCART_ARTIFACT_DIR", str(REPOSITORY / "artifacts" / "opencart" / OPENCART_VERSION)))
    if artifact_dir.exists():
        shutil.rmtree(artifact_dir)
    (artifact_dir / "video").mkdir(parents=True)
    with tempfile.TemporaryDirectory(prefix="appnuno-opencart-") as temporary:
        workspace = Path(temporary)
        web_root = prepare_store(workspace)
        log_path = artifact_dir / "php-server.log"
        with log_path.open("w", encoding="utf-8") as server_log:
            process = subprocess.Popen(["php", "-S", "127.0.0.1:8080", "-t", str(web_root)], stdout=server_log, stderr=subprocess.STDOUT)
            ftp_server = None
            try:
                wait_for_store(process, log_path)
                with sync_playwright() as playwright:
                    browser = playwright.chromium.launch()
                    context = browser.new_context(
                        viewport={"width": 1440, "height": 1000},
                        record_video_dir=str(artifact_dir / "video"),
                        record_video_size={"width": 1440, "height": 1000},
                    )
                    tracing_started = False
                    try:
                        context.tracing.start(screenshots=True, snapshots=True, sources=True)
                        tracing_started = True
                        page = context.new_page()
                        install_store(page)
                        login_admin(page, web_root)
                        configure_secondary_language()
                        configure_ftp()
                        ftp_server = start_ftp_server(web_root)
                        token = admin_token(page)
                        upload_extension(page, token)
                        refresh_modifications(page, admin_token(page))
                        install_module(page, admin_token(page))
                        capture_screenshot(page, artifact_dir, "01-admin-settings-initial.png")
                        save_and_check_tab(page, artifact_dir)
                        check_storefront(page, artifact_dir)
                    finally:
                        if tracing_started:
                            context.tracing.stop(path=str(artifact_dir / "playwright-trace.zip"))
                        context.close()
                        browser.close()
            finally:
                if ftp_server:
                    ftp_server.close_all()
                process.terminate()
                process.wait(timeout=10)
        print(f"OpenCart {OPENCART_VERSION} installation, OCMOD upload, module settings persistence and storefront bootstrap tests passed.")
        print(f"Screenshots, video, Playwright trace and PHP log saved to {artifact_dir}.")


if __name__ == "__main__":
    main()
