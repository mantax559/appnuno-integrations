import json
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
SDK_PATH = Path(os.environ.get("APPNUNO_SDK_SOURCE", str(REPOSITORY.parent / "appnuno-web" / "public" / "sdk" / "v1" / "appnuno.js")))
SDK_SOURCE = SDK_PATH.read_text(encoding="utf-8")
OPENCART_VERSION = os.environ.get("OPENCART_VERSION", "2.3.0.2")
SUPPORTED_OPENCART_VERSIONS = {"2.1.0.0", "2.1.0.1", "2.1.0.2", "2.2.0.0", "2.3.0.0", "2.3.0.1", "2.3.0.2"}
if OPENCART_VERSION not in SUPPORTED_OPENCART_VERSIONS:
    raise ValueError(f"Unsupported OpenCart test version: {OPENCART_VERSION}")
OPENCART_LINE = OPENCART_VERSION[:3]
PACKAGE = REPOSITORY / "downloads" / "opencart" / OPENCART_LINE / f"appnuno-opencart-{OPENCART_LINE}.ocmod.zip"
if OPENCART_LINE == "2.1":
    OPENCART_ARCHIVE_URL = f"https://github.com/opencart/opencart/archive/refs/tags/{OPENCART_VERSION}.zip"
else:
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


def legacy_module_layout() -> bool:
    return OPENCART_LINE != "2.3"


def admin_module_route() -> str:
    if legacy_module_layout():
        return "module/appnuno"
    return "extension/module/appnuno"


def open_module_editor(page: Page, token: str) -> None:
    page.goto(BASE_URL + "/admin/index.php?route=" + admin_module_route() + "&token=" + token)


def module_list_row(page: Page, token: str):
    if legacy_module_layout():
        page.goto(BASE_URL + "/admin/index.php?route=extension/module&token=" + token)
        return page.locator("#content table tr").filter(has_text="AppNuno Native Navigation").first
    page.goto(BASE_URL + "/admin/index.php?route=extension/extension&token=" + token)
    module_filter = page.locator('select[name="type"]')
    module_filter.select_option(module_filter.locator("option").filter(has_text="Modules").first.get_attribute("value"))
    return page.locator("#extension tr").filter(has_text="AppNuno Native Navigation").filter(has=page.locator('a[href*="extension=appnuno"]')).first


def add_store_language(page: Page, name: str, code: str, locale: str) -> None:
    connection = pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASSWORD, database=DB_NAME, autocommit=True)
    with connection.cursor() as cursor:
        cursor.execute("SET GLOBAL sql_mode = 'NO_ENGINE_SUBSTITUTION'")
    connection.close()
    page.goto(BASE_URL + "/admin/index.php?route=localisation/language/add&token=" + admin_token(page))
    page.locator('input[name="name"]').fill(name)
    if OPENCART_LINE == "2.1":
        page.locator('input[name="code"]').fill(code)
        page.locator('input[name="locale"]').fill(locale)
        page.locator('input[name="image"]').fill(code + ".png")
        page.locator('input[name="directory"]').fill(code)
    else:
        page.locator('select[name="code"]').select_option(code)
        if OPENCART_LINE != "2.2":
            page.locator('input[name="locale"]').fill(locale)
    page.locator('select[name="status"]').select_option("1")
    with page.expect_navigation():
        page.locator('button[form="form-language"]').click()
    page.locator(".alert-success").wait_for()
    if code not in page.locator("#content").inner_text():
        raise AssertionError(f"OpenCart did not list the added {name} language.")


def setting_value(key: str) -> str | None:
    connection = pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASSWORD, database=DB_NAME)
    with connection.cursor() as cursor:
        cursor.execute("SELECT value FROM oc_setting WHERE store_id = 0 AND code = 'module_appnuno' AND `key` = %s", (key,))
        row = cursor.fetchone()
    connection.close()
    return row[0] if row else None


def appnuno_event_count() -> int:
    connection = pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASSWORD, database=DB_NAME)
    with connection.cursor() as cursor:
        cursor.execute("SELECT COUNT(*) FROM oc_event WHERE code = 'appnuno'")
        count = cursor.fetchone()[0]
    connection.close()
    return count


def create_cart_product() -> int:
    connection = pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASSWORD, database=DB_NAME, autocommit=True)
    with connection.cursor() as cursor:
        cursor.execute("SELECT stock_status_id FROM oc_stock_status ORDER BY stock_status_id LIMIT 1")
        stock_status_id = cursor.fetchone()[0]
        cursor.execute(
            "INSERT INTO oc_product (model, sku, upc, ean, jan, isbn, mpn, location, quantity, stock_status_id, image, manufacturer_id, shipping, price, points, tax_class_id, date_available, weight, weight_class_id, length, width, height, length_class_id, subtract, minimum, sort_order, status, viewed, date_added, date_modified) VALUES (%s, '', '', '', '', '', '', '', 50, %s, '', 0, 1, 1.00, 0, 0, CURDATE(), 0, 1, 0, 0, 0, 1, 1, 1, 0, 1, 0, NOW(), NOW())",
            ("APPNUNO-CI-PRODUCT", stock_status_id),
        )
        product_id = cursor.lastrowid
        cursor.execute("SELECT l.language_id FROM oc_language l INNER JOIN oc_setting s ON s.store_id = 0 AND s.`key` = 'config_language' AND s.value = l.code LIMIT 1")
        language_row = cursor.fetchone()
        if not language_row or not language_row[0]:
            raise AssertionError("OpenCart has no store language, so the cart product cannot be displayed.")
        cursor.execute(
            "INSERT INTO oc_product_description (product_id, language_id, name, description, tag, meta_title, meta_description, meta_keyword) VALUES (%s, %s, 'AppNuno CI Product', 'Cart integration fixture', '', 'AppNuno CI Product', '', '')",
            (product_id, language_row[0]),
        )
        cursor.execute("INSERT INTO oc_product_to_store (product_id, store_id) VALUES (%s, 0)", (product_id,))
    connection.close()
    return product_id


def start_ftp_server(web_root: Path) -> FTPServer:
    authorizer = DummyAuthorizer()
    authorizer.add_user(FTP_USER, FTP_PASSWORD, str(web_root), perm="elradfmwMT")
    handler = FTPHandler
    handler.authorizer = authorizer
    server = FTPServer(("127.0.0.1", FTP_PORT), handler)
    thread = threading.Thread(target=server.serve_forever, kwargs={"timeout": 0.2, "blocking": True, "handle_exit": False}, daemon=True)
    thread.start()
    return server


def install_route(route: str) -> str:
    if OPENCART_LINE == "2.1":
        return "**route=" + route + "**"
    return "**route=install/" + route + "**"


def install_store(page: Page) -> None:
    page.goto(BASE_URL)
    page.locator('input[type="submit"]').click()
    page.wait_for_url(install_route("step_2"))
    if page.locator(".alert-danger").count():
        raise AssertionError("OpenCart pre-installation checks reported an error: " + page.locator(".alert-danger").inner_text())
    page.locator('input[type="submit"]').click()
    page.wait_for_url(install_route("step_3"))
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
    page.wait_for_url(install_route("step_4"), timeout=30000)


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
    page.get_by_text(f"AppNuno OpenCart {OPENCART_LINE}", exact=True).wait_for()


def click_module_list_button(page: Page, row, selector: str) -> None:
    if legacy_module_layout():
        with page.expect_navigation():
            row.locator(selector).click()
        return
    row.locator(selector).click()


def install_module(page: Page, token: str) -> None:
    module_row = module_list_row(page, token)
    module_row.wait_for()
    click_module_list_button(page, module_row, "a.btn-success")
    module_row.locator("a.btn-danger").wait_for()
    open_module_editor(page, token)
    page.locator("#default-tabs .appnuno-tab").first.wait_for()


def save_and_check_tab(page: Page, artifact_dir: Path) -> None:
    default_panel = page.locator("#default-tabs").locator("xpath=../..")
    if not default_panel.locator("#cart-tab-id").count():
        raise AssertionError("Cart badge selection is not inside the Default tabs panel.")
    for index, labels in enumerate((("Home", "Pradžia", "Start"), ("Cart", "Krepšelis", "Warenkorb"))):
        tab = page.locator("#default-tabs .appnuno-tab").nth(index)
        tab.locator('[data-language-label="en-gb"]').fill(labels[0])
        tab.locator('[data-language-label="lt-lt"]').fill(labels[1])
        tab.locator('[data-language-label="de-de"]').fill(labels[2])
    page.locator('button[data-add-tab="default-tabs"]').click()
    tab = page.locator("#default-tabs .appnuno-tab").last
    tab.locator('[data-field="id"]').fill("ci-added-tab")
    tab.locator('[data-language-label="en-gb"]').fill("CI Added Tab")
    tab.locator('[data-language-label="lt-lt"]').fill("CI pridėtas skirtukas")
    tab.locator('[data-language-label="de-de"]').fill("CI hinzugefügter Tab")
    tab.locator('[data-url-select]').select_option("__custom__")
    tab.locator('[data-language-url="en-gb"]').fill("/en/contact")
    tab.locator('[data-language-url="lt-lt"]').fill("/lt/kontaktai")
    tab.locator('[data-language-url="de-de"]').fill("/de/kontakt")
    tab.locator('[data-field="icon"]').fill("home-outline")
    tab.locator('[data-field="activeIcon"]').fill("home")
    page.locator("#cart-tab-id").select_option("ci-added-tab")
    page.locator("#input-status").select_option("1")
    page.locator('a[href="#tab-back"]').click()
    page.locator("#tab-back").wait_for(state="visible")
    page.locator("#back-enabled").select_option("0")
    page.locator("#back-placement").select_option("bar")
    page.locator('a[href="#tab-glass"]').click()
    page.locator("#tab-glass").wait_for(state="visible")
    page.locator("#glass-enabled").select_option("1")
    page.locator("#glass-color-scheme").select_option("dark")
    page.locator("#glass-style").select_option("clear")
    page.locator("#glass-animate").select_option("0")
    page.locator("#glass-duration").fill("0.7")
    page.locator("#glass-interactive").select_option("0")
    page.locator("#glass-tint-color").fill("#123456")
    page.locator("#glass-tint-alpha").fill("0.4")
    page.locator('a[href="#tab-colors"]').click()
    page.locator("#tab-colors").wait_for(state="visible")
    page.locator("#light-background").fill("#112233")
    page.locator("#light-content").fill("#223344")
    page.locator("#dark-background").fill("#334455")
    page.locator("#dark-content").fill("#445566")
    page.locator('a[href="#tab-navigation"]').click()
    page.locator("#tab-navigation").wait_for(state="visible")
    page.locator('button[data-add-tab="default-tabs"]').click()
    page_tab = page.locator("#default-tabs .appnuno-tab").last
    page_tab.locator('[data-field="id"]').fill("ci-cart-route-tab")
    page_tab.locator('[data-language-label="en-gb"]').fill("Cart route tab")
    page_tab.locator('[data-language-label="lt-lt"]').fill("Krepšelio skirtukas")
    page_tab.locator('[data-language-label="de-de"]').fill("Warenkorb-Route")
    page_tab.locator('[data-url-select]').select_option("__custom__")
    page_tab.locator('[data-language-url="en-gb"]').fill("/en/cart")
    page_tab.locator('[data-language-url="lt-lt"]').fill("/lt/krepselis")
    page_tab.locator('[data-language-url="de-de"]').fill("/de/warenkorb")
    page_tab.locator('[data-field="icon"]').fill("cart-outline")
    page_tab.locator('[data-field="activeIcon"]').fill("cart")
    page_tab.locator("[data-pages]").select_option(["checkout/cart"])
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
    saved_page_tab = page.locator("#default-tabs .appnuno-tab").nth(3)
    if saved_page_tab.locator('[data-language-label="lt-lt"]').input_value() != "Krepšelio skirtukas":
        raise AssertionError("The cart page tab label did not persist after reloading the settings page.")
    if saved_page_tab.locator('[data-language-url="lt-lt"]').input_value() != "/lt/krepselis":
        raise AssertionError("The cart page tab URL did not persist after reloading the settings page.")
    selected_pages = saved_page_tab.locator("[data-pages]").evaluate("select => Array.from(select.selectedOptions).map(option => option.value)")
    if selected_pages != ["checkout/cart"]:
        raise AssertionError(f"The cart page selection did not persist after reloading the settings page: {selected_pages}.")
    capture_screenshot(page, artifact_dir, "03-admin-settings-after-save.png")


def use_en_gb_language_code(page: Page) -> None:
    page.goto(BASE_URL + "/admin/index.php?route=localisation/language&token=" + admin_token(page))
    page.locator('a[href*="route=localisation/language/edit"]').first.click()
    page.locator('input[name="code"]').fill("en-gb")
    with page.expect_navigation():
        page.locator('button[form="form-language"]').click()
    page.locator(".alert-success").wait_for()
    switch_admin_language(page, "en-gb")


def switch_admin_language(page: Page, code: str) -> None:
    page.goto(BASE_URL + "/admin/index.php?route=setting/setting&token=" + admin_token(page))
    page.locator('a[href="#tab-local"]').click()
    if OPENCART_LINE == "2.1":
        page.locator("#input-language").select_option(code)
        page.locator("#input-admin-language").select_option(code)
        with page.expect_navigation():
            page.locator('button[form="form-setting"]').click()
    else:
        page.wait_for_function("() => { const button = document.querySelector('#button-save'); return !!button && !button.disabled; }")
        page.locator("#input-admin-language").select_option(code)
        with page.expect_navigation():
            page.locator("#button-save").click()
    page.locator(".alert-success").wait_for()


def expect_module_language(page: Page, phrases: tuple[str, ...]) -> None:
    open_module_editor(page, admin_token(page))
    page.locator("#default-tabs .appnuno-tab").first.wait_for()
    content = page.locator("#content").inner_text()
    for href in ("#tab-back", "#tab-glass", "#tab-colors"):
        page.locator(f'a[href="{href}"]').click()
        page.locator(href).wait_for(state="visible")
        content += "\n" + page.locator(href).inner_text()
    missing = [phrase for phrase in phrases if phrase not in content]
    if missing:
        raise AssertionError("The AppNuno extension did not show these translated names: " + ", ".join(missing) + ".")


def check_admin_module_languages(page: Page) -> None:
    switch_admin_language(page, "lt-lt")
    expect_module_language(page, (
        "AppNuno vietinė navigacija",
        "Redaguoti AppNuno vietinę navigaciją",
        "Numatytieji skirtukai",
        "Krepšelio ženkliuko skirtukas",
        "Rodoma puslapiuose",
        "Grįžimo mygtukas",
        "Stiklinė mygtukų juosta",
        "Navigacijos spalvos",
        "Šviesus režimas",
        "Skirtukų pavadinimai pagal kalbą",
        "Įjungta",
    ))
    switch_admin_language(page, "de-de")
    expect_module_language(page, (
        "AppNuno native Navigation",
        "AppNuno native Navigation bearbeiten",
        "Standard-Tabs",
        "Tab für das Warenkorbsymbol",
        "Angezeigt auf Seiten",
        "Zurück-Schaltfläche",
        "Glas-Schaltflächenleiste",
        "Navigationsfarben",
        "Heller Modus",
        "Tab-Beschriftungen nach Sprache",
        "Aktiviert",
    ))
    switch_admin_language(page, "en-gb")
    expect_module_language(page, (
        "AppNuno Native Navigation",
        "Edit AppNuno Native Navigation",
        "Default tabs",
        "Cart badge tab",
        "Shown on pages",
        "Back button",
        "Glass button bar",
        "Navigation colors",
        "Light mode",
        "Tab labels by language",
        "Enabled",
    ))


def get_bootstrap_payload(page: Page, responses: list[Response], expected_route: str) -> dict:
    page.wait_for_function("window.AppNunoIntegration && window.AppNunoIntegration.config && window.AppNunoIntegration.navigation && window.AppNuno && window.AppNuno.ready === true && window.__appnunoReadyEvents && window.__appnunoReadyEvents.length")
    bootstrap_scripts = page.locator('script[src*="appnuno"][src*="bootstrap"]')
    if bootstrap_scripts.count() != 1:
        raise AssertionError("The storefront must include exactly one AppNuno bootstrap script.")
    bootstrap_url = bootstrap_scripts.first.evaluate("script => script.src")
    bootstrap_responses = [
        response
        for response in responses
        if parse_qs(urlparse(response.url).query).get("route", []) == [admin_module_route() + "/bootstrap"]
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
    if parse_qs(urlparse(payload["stateUrl"]).query).get("route", []) != [admin_module_route() + "/state"]:
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


def check_default_bootstrap(payload: dict) -> None:
    configuration = payload["config"]
    if configuration["cartTabId"] != "ci-added-tab":
        raise AssertionError("The bootstrap configuration did not publish the selected cart badge tab ID.")
    if len(configuration["defaultTabs"]) != 4:
        raise AssertionError(f"The bootstrap configuration has an unexpected default tab set: {configuration['defaultTabs']}.")
    for tab_id, labels in (("home", {"en-gb": "Home", "lt-lt": "Pradžia", "de-de": "Start"}), ("cart", {"en-gb": "Cart", "lt-lt": "Krepšelis", "de-de": "Warenkorb"})):
        configured_tab = next(tab for tab in configuration["defaultTabs"] if tab["id"] == tab_id)
        if configured_tab.get("labels") != labels:
            raise AssertionError(f"The bootstrap configuration did not retain translations for {tab_id}: {configured_tab}.")
    configured_tab = next(tab for tab in configuration["defaultTabs"] if tab["id"] == "ci-added-tab")
    if configured_tab.get("labels") != {"en-gb": "CI Added Tab", "lt-lt": "CI pridėtas skirtukas", "de-de": "CI hinzugefügter Tab"}:
        raise AssertionError(f"The bootstrap configuration did not retain all default tab translations: {configured_tab}.")
    if configured_tab.get("urls") != {"en-gb": "/en/contact", "lt-lt": "/lt/kontaktai", "de-de": "/de/kontakt"}:
        raise AssertionError(f"The bootstrap configuration did not retain all default tab URLs: {configured_tab}.")
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
    page_tab = next(tab for tab in configuration["defaultTabs"] if tab["id"] == "ci-cart-route-tab")
    if page_tab.get("pages") != ["checkout/cart"]:
        raise AssertionError(f"The cart-only tab was not limited to the cart page: {page_tab}.")
    if page_tab.get("labels") != {"en-gb": "Cart route tab", "lt-lt": "Krepšelio skirtukas", "de-de": "Warenkorb-Route"}:
        raise AssertionError(f"The cart-only tab translations were not retained: {page_tab}.")
    if configuration.get("routes"):
        raise AssertionError(f"Page visibility replaced route-specific tab sets: {configuration['routes']}.")
    if any(tab["id"] == "ci-cart-route-tab" for tab in payload["navigation"]["tabs"]):
        raise AssertionError(f"A tab limited to the cart page was published on {payload['route']}: {payload['navigation']['tabs']}.")
    tab = next(tab for tab in payload["navigation"]["tabs"] if tab["id"] == "ci-added-tab")
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


def check_route_bootstrap(payload: dict) -> None:
    if payload["route"] != "checkout/cart":
        raise AssertionError(f"Expected the checkout/cart bootstrap route, got {payload['route']}.")
    configured_tab = next(tab for tab in payload["config"]["defaultTabs"] if tab["id"] == "ci-cart-route-tab")
    if configured_tab.get("labels") != {"en-gb": "Cart route tab", "lt-lt": "Krepšelio skirtukas", "de-de": "Warenkorb-Route"}:
        raise AssertionError(f"The saved cart page tab translations are incorrect: {configured_tab}.")
    if configured_tab.get("urls") != {"en-gb": "/en/cart", "lt-lt": "/lt/krepselis", "de-de": "/de/warenkorb"}:
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
    if "labels" in tab or "urls" in tab or "pages" in tab:
        raise AssertionError("The resolved cart page tab still contains stored translations or page limits.")


def check_cart_and_customer_state(page: Page) -> None:
    product_id = create_cart_product()
    page.goto(BASE_URL + "/index.php?route=product/product&product_id=" + str(product_id))
    page.locator("#button-cart").wait_for()
    page.locator("#button-cart").click()
    page.wait_for_timeout(500)
    page.locator("#button-cart").click()
    page.wait_for_timeout(500)
    state_url = BASE_URL + "/index.php?route=" + admin_module_route() + "/state"
    cart_state = page.evaluate("async url => (await fetch(url, { credentials: 'same-origin', cache: 'no-store' })).json()", state_url)
    if cart_state.get("cartCount") != 2 or cart_state.get("identity") is not None:
        raise AssertionError(f"The storefront state did not report the real cart quantity: {cart_state}.")
    page.goto(BASE_URL + "/index.php?route=account/register")
    page.locator('input[name="firstname"]').fill("AppNuno")
    page.locator('input[name="lastname"]').fill("CI Customer")
    page.locator('input[name="email"]').fill("appnuno-ci-customer@example.test")
    page.locator('input[name="telephone"]').fill("5550100")
    page.locator('input[name="password"]').fill("ci-customer-password")
    page.locator('input[name="confirm"]').fill("ci-customer-password")
    page.locator('input[name="address_1"]').fill("1 CI Street")
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
    with page.expect_navigation():
        page.locator('#content form input[type="submit"]').click()
    if "route=account/success" not in page.url and "route=account/account" not in page.url:
        errors = page.locator(".alert-danger, .text-danger").all_inner_texts()
        raise AssertionError(f"OpenCart did not accept the customer registration at {page.url}: {errors}.")
    customer_state = page.evaluate("async url => (await fetch(url, { credentials: 'same-origin', cache: 'no-store' })).json()", state_url)
    identity = customer_state.get("identity")
    if customer_state.get("cartCount") != 2 or not isinstance(identity, dict) or identity.get("guard") != "customer" or identity.get("is_logged_in") is not True or not identity.get("identifier"):
        raise AssertionError(f"The customer registration did not publish the authenticated storefront identity: {customer_state}.")
    page.goto(BASE_URL + "/index.php?route=account/logout")
    logged_out = page.evaluate("async url => (await fetch(url, { credentials: 'same-origin', cache: 'no-store' })).json()", state_url)
    if logged_out.get("identity") != {"identifier": identity["identifier"], "guard": "customer", "is_logged_in": False}:
        raise AssertionError(f"The storefront did not preserve an explicit customer logout identity: {logged_out}.")
    if logged_out.get("cartCount") != 0:
        raise AssertionError(f"Customer logout left the guest cart with the account items: {logged_out}.")


def save_module_status(page: Page, enabled: bool) -> None:
    page.locator("#input-status").select_option("1" if enabled else "0")
    with page.expect_navigation():
        page.locator('button[form="form-appnuno"]').click()
    page.locator(".alert-success").wait_for()


def check_module_lifecycle_and_admin_validation(page: Page, token: str) -> None:
    open_module_editor(page, token)
    original_config = setting_value("module_appnuno_config")
    if not original_config:
        raise AssertionError("The AppNuno module configuration was not persisted.")
    page.locator('#default-tabs .appnuno-tab').first.locator('[data-field="id"]').fill("")
    page.locator('button[form="form-appnuno"]').click()
    page.locator(".alert-danger:not(#client-validation-error)").wait_for()
    if setting_value("module_appnuno_config") != original_config:
        raise AssertionError("Invalid AppNuno settings changed the stored module configuration.")
    open_module_editor(page, token)
    connection = pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASSWORD, database=DB_NAME, autocommit=True)
    with connection.cursor() as cursor:
        cursor.execute("SELECT user_group_id FROM oc_user WHERE username = %s", (ADMIN_USER,))
        group_id = cursor.fetchone()[0]
        cursor.execute("SELECT permission FROM oc_user_group WHERE user_group_id = %s", (group_id,))
        original_permissions = json.loads(cursor.fetchone()[0])
        restricted_permissions = json.loads(json.dumps(original_permissions))
        restricted_permissions["modify"] = [route for route in restricted_permissions.get("modify", []) if route != admin_module_route()]
        cursor.execute("UPDATE oc_user_group SET permission = %s WHERE user_group_id = %s", (json.dumps(restricted_permissions), group_id))
    connection.close()
    try:
        page.locator("#input-status").select_option("0")
        page.locator('button[form="form-appnuno"]').click()
        server_error = page.locator(".alert-danger:not(#client-validation-error)")
        server_error.wait_for()
        if "permission" not in server_error.inner_text().lower():
            raise AssertionError("The module settings controller did not reject a user without modify permission.")
        if setting_value("module_appnuno_status") != "1":
            raise AssertionError("A user without modify permission changed the module status.")
    finally:
        connection = pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASSWORD, database=DB_NAME, autocommit=True)
        with connection.cursor() as cursor:
            cursor.execute("UPDATE oc_user_group SET permission = %s WHERE user_group_id = %s", (json.dumps(original_permissions), group_id))
        connection.close()
    token = admin_token(page)
    open_module_editor(page, token)
    save_module_status(page, False)
    page.goto(BASE_URL + "/")
    if page.locator('script[src*="appnuno"][src*="bootstrap"]').count():
        raise AssertionError("The disabled module still injected its bootstrap script.")
    disabled = page.evaluate("async url => { const response = await fetch(url, { credentials: 'same-origin' }); return response.status; }", BASE_URL + "/index.php?route=" + admin_module_route() + "/state")
    if disabled != 404:
        raise AssertionError(f"The disabled module state endpoint returned HTTP {disabled} instead of 404.")
    open_module_editor(page, token)
    save_module_status(page, True)
    token = admin_token(page)
    page.goto(BASE_URL + "/")
    page.wait_for_function("window.AppNunoIntegration && window.AppNunoIntegration.navigation")
    if page.locator('script[src*="appnuno"][src*="bootstrap"]').count() != 1:
        raise AssertionError("The re-enabled module did not restore exactly one bootstrap script.")
    module_row = module_list_row(page, token)
    module_row.wait_for()
    page.once("dialog", lambda dialog: dialog.accept())
    click_module_list_button(page, module_row, "a.btn-danger")
    module_row.locator("a.btn-success").wait_for()
    if setting_value("module_appnuno_status") is not None or appnuno_event_count():
        raise AssertionError("Uninstalling the module did not remove its settings and catalog event.")
    click_module_list_button(page, module_row, "a.btn-success")
    module_row.locator("a.btn-danger").wait_for()
    if appnuno_event_count() != 1:
        raise AssertionError("Reinstalling the module did not restore exactly one catalog event.")
    token = admin_token(page)
    open_module_editor(page, token)
    save_module_status(page, True)
    page.goto(BASE_URL + "/")
    page.wait_for_function("window.AppNunoIntegration && window.AppNunoIntegration.navigation")


def set_storefront_language(page: Page, code: str) -> None:
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


def expect_resolved_storefront_language(page: Page, responses: list[Response], home_label: str, cart_label: str, added_label: str, added_url: str) -> None:
    page.goto(BASE_URL + "/")
    payload = get_bootstrap_payload(page, responses, "common/home")
    labels = {tab["id"]: (tab.get("label"), tab.get("url")) for tab in payload["navigation"]["tabs"]}
    expected = {
        "home": (home_label, "/"),
        "cart": (cart_label, "/index.php?route=checkout/cart"),
        "ci-added-tab": (added_label, added_url),
    }
    if labels != expected:
        raise AssertionError(f"The storefront published the wrong language labels: {labels}.")


def check_storefront_label_languages(page: Page, responses: list[Response]) -> None:
    set_storefront_language(page, "lt-lt")
    expect_resolved_storefront_language(page, responses, "Pradžia", "Krepšelis", "CI pridėtas skirtukas", "/lt/kontaktai")
    page.goto(BASE_URL + "/index.php?route=checkout/cart")
    published = {tab["id"]: tab for tab in get_bootstrap_payload(page, responses, "checkout/cart")["navigation"]["tabs"]}
    route_tab = published.get("ci-cart-route-tab")
    if not route_tab or route_tab.get("label") != "Krepšelio skirtukas" or route_tab.get("url") != "/lt/krepselis":
        raise AssertionError(f"The storefront published the wrong checkout/cart language: {route_tab}.")
    if published.get("home", {}).get("label") != "Pradžia":
        raise AssertionError(f"The shared home tab was not published in Lithuanian on the cart page: {published.get('home')}.")
    set_storefront_language(page, "de-de")
    expect_resolved_storefront_language(page, responses, "Start", "Warenkorb", "CI hinzugefügter Tab", "/de/kontakt")
    set_storefront_language(page, "en-gb")
    expect_resolved_storefront_language(page, responses, "Home", "Cart", "CI Added Tab", "/en/contact")


def check_storefront(page: Page, artifact_dir: Path) -> None:
    responses: list[Response] = []
    page.on("response", lambda response: responses.append(response))
    page.goto(BASE_URL + "/")
    check_default_bootstrap(get_bootstrap_payload(page, responses, "common/home"))
    capture_screenshot(page, artifact_dir, "04-storefront-desktop.png")
    page.set_viewport_size({"width": 390, "height": 844})
    page.reload()
    check_default_bootstrap(get_bootstrap_payload(page, responses, "common/home"))
    capture_screenshot(page, artifact_dir, "05-storefront-mobile.png")
    page.set_viewport_size({"width": 1440, "height": 1000})
    page.goto(BASE_URL + "/index.php?route=product/special")
    check_default_bootstrap(get_bootstrap_payload(page, responses, "product/special"))
    page.goto(BASE_URL + "/index.php?route=checkout/cart")
    check_route_bootstrap(get_bootstrap_payload(page, responses, "checkout/cart"))
    capture_screenshot(page, artifact_dir, "06-storefront-cart-route.png")
    check_storefront_label_languages(page, responses)


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
                    context.route("https://appnuno.com/sdk/v1/appnuno.js", lambda route: route.fulfill(status=200, content_type="application/javascript", body=SDK_SOURCE))
                    context.add_init_script("window.__appnunoReadyEvents = []; window.addEventListener('appnuno:ready', event => window.__appnunoReadyEvents.push({ type: event.type, navigation: event.detail.navigation, identity: event.detail.identity }));")
                    tracing_started = False
                    try:
                        context.tracing.start(screenshots=True, snapshots=True, sources=True)
                        tracing_started = True
                        page = context.new_page()
                        install_store(page)
                        login_admin(page, web_root)
                        configure_ftp()
                        ftp_server = start_ftp_server(web_root)
                        token = admin_token(page)
                        upload_extension(page, token)
                        refresh_modifications(page, admin_token(page))
                        if OPENCART_LINE == "2.1":
                            use_en_gb_language_code(page)
                        add_store_language(page, "Lithuanian", "lt-lt", "lt_LT.UTF-8,lt_LT,lt-lt,lithuanian")
                        add_store_language(page, "German", "de-de", "de_DE.UTF-8,de_DE,de-de,german")
                        install_module(page, admin_token(page))
                        capture_screenshot(page, artifact_dir, "01-admin-settings-initial.png")
                        save_and_check_tab(page, artifact_dir)
                        check_admin_module_languages(page)
                        token = admin_token(page)
                        check_storefront(page, artifact_dir)
                        check_cart_and_customer_state(page)
                        check_module_lifecycle_and_admin_validation(page, token)
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
