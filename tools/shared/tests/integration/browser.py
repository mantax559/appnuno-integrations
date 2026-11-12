import os
import tempfile
from pathlib import Path

from playwright.sync_api import Browser, BrowserContext, Page, Playwright, sync_playwright

from integration.adapter import REPOSITORY, StoreAdapter


def artifact_dir(adapter: StoreAdapter) -> Path:
    configured = os.environ.get("APPNUNO_ARTIFACT_DIR")
    path = Path(configured) if configured else Path(tempfile.gettempdir()) / f"appnuno-{adapter.name}-artifacts"
    path.mkdir(parents=True, exist_ok=True)
    return path


def sdk_source() -> str | None:
    configured = os.environ.get("APPNUNO_SDK_SOURCE")
    if configured and Path(configured).is_file():
        return Path(configured).read_text(encoding="utf-8")
    sibling = REPOSITORY.parent / "appnuno-platform" / "public" / "sdk" / "v1" / "appnuno.js"
    if sibling.is_file():
        return sibling.read_text(encoding="utf-8")
    return None


def capture_screenshot(page: Page, directory: Path, filename: str) -> None:
    page.screenshot(path=str(directory / filename), full_page=True, animations="disabled")


class BrowserSession:
    def __init__(self, playwright: Playwright, browser: Browser, admin: BrowserContext, page: Page, directory: Path, adapter: StoreAdapter) -> None:
        self.playwright = playwright
        self.browser = browser
        self.admin = admin
        self.page = page
        self.directory = directory
        self.adapter = adapter
        self.contexts = [admin]

    def store_page(self) -> Page:
        context = self.browser.new_context(viewport={"width": 1440, "height": 1000}, record_video_dir=str(self.directory / "video"))
        prepare_context(context, self.adapter)
        self.contexts.append(context)
        page = context.new_page()
        page.set_default_timeout(30000)
        page.set_default_navigation_timeout(30000)
        return page

    def close(self) -> None:
        trace = self.directory / "playwright-trace.zip"
        try:
            self.admin.tracing.stop(path=str(trace))
        except Exception:
            pass
        for context in self.contexts:
            context.close()
        self.browser.close()
        self.playwright.stop()


def prepare_context(context: BrowserContext, adapter: StoreAdapter) -> None:
    source = sdk_source()
    if source is not None:
        context.route(
            "https://appnuno.com/sdk/v1/appnuno.js",
            lambda route: route.fulfill(status=200, content_type="application/javascript", body=source),
        )
    context.add_init_script(
        "window.__appnunoReadyEvents = []; window.addEventListener('appnuno:ready', event => window.__appnunoReadyEvents.push({ type: event.type, navigation: event.detail && event.detail.navigation, identity: event.detail && event.detail.identity }));"
    )


def open_session(adapter: StoreAdapter, directory: Path) -> BrowserSession:
    playwright = sync_playwright().start()
    browser = playwright.chromium.launch()
    admin = browser.new_context(
        viewport={"width": 1440, "height": 1000},
        record_video_dir=str(directory / "video"),
        record_video_size={"width": 1440, "height": 1000},
    )
    prepare_context(admin, adapter)
    admin.tracing.start(screenshots=True, snapshots=True, sources=True)
    page = admin.new_page()
    page.set_default_timeout(30000)
    return BrowserSession(playwright, browser, admin, page, directory, adapter)
