import os
import shutil
import subprocess
import tempfile
import threading
import time
import urllib.request
import zipfile
from pathlib import Path

import pymysql
from playwright.sync_api import Page, sync_playwright
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


def save_and_check_tab(page: Page) -> None:
    default_panel = page.locator("#default-tabs").locator("xpath=../..")
    if not default_panel.locator("#cart-tab-id").count():
        raise AssertionError("Cart badge selection is not inside the Default tabs panel.")
    page.locator('button[data-add-tab="default-tabs"]').click()
    tab = page.locator("#default-tabs .appnuno-tab").last
    tab.locator('[data-field="id"]').fill("ci-added-tab")
    tab.locator('[data-language-label="en-gb"]').fill("CI Added Tab")
    tab.locator('[data-url-select]').select_option("/index.php?route=information/contact")
    tab.locator('[data-field="icon"]').fill("home-outline")
    page.locator("#cart-tab-id").select_option("ci-added-tab")
    page.locator("#input-status").select_option("1")
    page.locator('button[form="form-appnuno"]').click()
    page.locator(".alert-success").filter(has_text="modified AppNuno Native Navigation").wait_for(timeout=15000)
    page.reload()
    saved_tab = page.locator("#default-tabs .appnuno-tab").nth(2)
    saved_tab.wait_for()
    if saved_tab.locator('[data-field="id"]').input_value() != "ci-added-tab":
        raise AssertionError("The newly added default tab ID did not persist after reloading the settings page.")
    if saved_tab.locator('[data-language-label="en-gb"]').input_value() != "CI Added Tab":
        raise AssertionError("The newly added default tab label did not persist after reloading the settings page.")
    if page.locator("#cart-tab-id").input_value() != "ci-added-tab":
        raise AssertionError("The cart badge tab selection did not persist after reloading the settings page.")


def check_storefront(page: Page) -> None:
    responses = []
    page.on("response", lambda response: responses.append(response) if "/extension/module/appnuno/bootstrap" in response.url else None)
    page.goto(BASE_URL + "/")
    page.wait_for_function("window.AppNunoIntegration && window.AppNunoIntegration.config && window.AppNunoIntegration.navigation")
    bootstrap = next((response for response in responses if "/extension/module/appnuno/bootstrap" in response.url), None)
    if bootstrap is None or bootstrap.status != 200:
        raise AssertionError("The storefront did not successfully request the AppNuno bootstrap endpoint.")
    bootstrap_scripts = page.locator('script[src*="/extension/module/appnuno/bootstrap"]')
    if bootstrap_scripts.count() != 1:
        raise AssertionError("The storefront must include exactly one AppNuno bootstrap script.")
    sdk_scripts = page.locator('script[src="https://appnuno.com/sdk/v1/appnuno.js"]')
    sdk_scripts.wait_for()
    if page.evaluate("window.AppNunoIntegration.config.cartTabId") != "ci-added-tab":
        raise AssertionError("The storefront bootstrap did not publish the persisted cart badge tab ID.")


def main() -> None:
    if not PACKAGE.is_file():
        raise FileNotFoundError("Build the OCMOD package before running the integration test: " + str(PACKAGE))
    with tempfile.TemporaryDirectory(prefix="appnuno-opencart-") as temporary:
        workspace = Path(temporary)
        web_root = prepare_store(workspace)
        log_path = workspace / "php-server.log"
        with log_path.open("w", encoding="utf-8") as server_log:
            process = subprocess.Popen(["php", "-S", "127.0.0.1:8080", "-t", str(web_root)], stdout=server_log, stderr=subprocess.STDOUT)
            ftp_server = None
            try:
                wait_for_store(process, log_path)
                with sync_playwright() as playwright:
                    browser = playwright.chromium.launch()
                    page = browser.new_page()
                    install_store(page)
                    login_admin(page, web_root)
                    configure_ftp()
                    ftp_server = start_ftp_server(web_root)
                    token = admin_token(page)
                    upload_extension(page, token)
                    refresh_modifications(page, admin_token(page))
                    install_module(page, admin_token(page))
                    save_and_check_tab(page)
                    check_storefront(page)
                    browser.close()
            finally:
                if ftp_server:
                    ftp_server.close_all()
                process.terminate()
                process.wait(timeout=10)
        print(f"OpenCart {OPENCART_VERSION} installation, OCMOD upload, module settings persistence and storefront bootstrap tests passed.")


if __name__ == "__main__":
    main()
