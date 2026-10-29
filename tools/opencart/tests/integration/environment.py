import os
import shutil
import subprocess
import threading
import time
import zipfile
from collections.abc import Iterator
from contextlib import contextmanager
from pathlib import Path
from urllib.request import urlopen, urlretrieve

from playwright.sync_api import Page, Route, sync_playwright
from pyftpdlib.authorizers import DummyAuthorizer
from pyftpdlib.handlers import FTPHandler
from pyftpdlib.servers import FTPServer

from integration.compatibility import install_route
from integration.config import BASE_URL, Runtime


def capture_screenshot(page: Page, artifact_dir: Path, filename: str) -> None:
    page.screenshot(path=str(artifact_dir / filename), full_page=True, animations="disabled")


def click_install_continue(page: Page) -> None:
    # OpenCart 4 license step continues through a link; other installer steps use a submit control.
    control = page.locator('input[type="submit"], button[type="submit"]')
    if control.count():
        control.first.click()
        return
    page.locator("a.btn-primary").click()


def install_store(runtime: Runtime, page: Page) -> None:
    page.goto(runtime.base_url)
    click_install_continue(page)
    page.wait_for_url(install_route(runtime, "step_2"))
    if page.locator(".alert-danger").count():
        raise AssertionError("OpenCart pre-installation checks reported an error: " + page.locator(".alert-danger").inner_text())
    click_install_continue(page)
    page.wait_for_url(install_route(runtime, "step_3"))
    page.locator('select[name="db_driver"]').select_option("mysqli")
    page.locator('input[name="db_hostname"]').fill(runtime.db_host)
    page.locator('input[name="db_username"]').fill(runtime.db_user)
    page.locator('input[name="db_password"]').fill(runtime.db_password)
    page.locator('input[name="db_database"]').fill(runtime.db_name)
    database_port = page.locator('input[name="db_port"]')
    if database_port.count():
        database_port.fill("3306")
    page.locator('input[name="db_prefix"]').fill("oc_")
    page.locator('input[name="username"]').fill(runtime.admin_user)
    page.locator('input[name="password"]').fill(runtime.admin_password)
    page.locator('input[name="email"]').fill("opencart-ci@example.test")
    click_install_continue(page)
    page.wait_for_url(install_route(runtime, "step_4"), timeout=30000)


@contextmanager
def open_browser(runtime: Runtime, artifact_dir: Path) -> Iterator[Page]:
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch()
        context = browser.new_context(
            viewport={"width": 1440, "height": 1000},
            record_video_dir=str(artifact_dir / "video"),
            record_video_size={"width": 1440, "height": 1000},
        )

        def fulfill_sdk(route: Route) -> None:
            route.fulfill(status=200, content_type="application/javascript", body=runtime.sdk_source)

        context.route("https://appnuno.com/sdk/v1/appnuno.js", fulfill_sdk)
        context.add_init_script("window.__appnunoReadyEvents = []; window.addEventListener('appnuno:ready', event => window.__appnunoReadyEvents.push({ type: event.type, navigation: event.detail.navigation, identity: event.detail.identity }));")
        tracing_started = False
        try:
            context.tracing.start(screenshots=True, snapshots=True, sources=True)
            tracing_started = True
            yield context.new_page()
        finally:
            if tracing_started:
                context.tracing.stop(path=str(artifact_dir / "playwright-trace.zip"))
            context.close()
            browser.close()


@contextmanager
def open_php_server(web_root: Path, log_path: Path) -> Iterator[None]:
    # OpenCart 3.0.0.0 needs an explicit session path on current PHP to avoid writing /sess_* at filesystem root.
    with log_path.open("w", encoding="utf-8") as server_log:
        process = subprocess.Popen(["php", "-S", "127.0.0.1:8080", "-t", str(web_root), "-d", "session.save_path=/tmp"], stdout=server_log, stderr=subprocess.STDOUT)
        try:
            wait_for_store(process, log_path)
            yield
        finally:
            process.terminate()
            process.wait(timeout=10)


def prepare_artifact_dir(runtime: Runtime) -> Path:
    artifact_dir = Path(os.environ.get("OPENCART_ARTIFACT_DIR", str(runtime.repository / "artifacts" / "opencart" / runtime.version)))
    if artifact_dir.exists():
        shutil.rmtree(artifact_dir)
    (artifact_dir / "video").mkdir(parents=True)
    return artifact_dir


def prepare_store(runtime: Runtime, workspace: Path) -> Path:
    archive_path = workspace / "opencart.zip"
    urlretrieve(runtime.archive_url, archive_path)
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


def start_ftp_server(runtime: Runtime, web_root: Path) -> FTPServer:
    authorizer = DummyAuthorizer()
    authorizer.add_user(runtime.ftp_user, runtime.ftp_password, str(web_root), perm="elradfmwMT")
    handler = FTPHandler
    handler.authorizer = authorizer
    server = FTPServer(("127.0.0.1", runtime.ftp_port), handler)
    thread = threading.Thread(target=server.serve_forever, kwargs={"timeout": 0.2, "blocking": True, "handle_exit": False}, daemon=True)
    thread.start()
    return server


def wait_for_store(process: subprocess.Popen[bytes], log_path: Path) -> None:
    deadline = time.monotonic() + 60
    while time.monotonic() < deadline:
        if process.poll() is not None:
            raise RuntimeError("OpenCart PHP server stopped before becoming ready.\n" + log_path.read_text(errors="replace"))
        try:
            with urlopen(BASE_URL, timeout=2):
                return
        except Exception:
            time.sleep(1)
    raise RuntimeError("OpenCart PHP server did not become ready.\n" + log_path.read_text(errors="replace"))
