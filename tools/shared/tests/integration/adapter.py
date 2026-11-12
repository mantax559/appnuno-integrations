import json
from pathlib import Path

from playwright.sync_api import Page


REPOSITORY = Path(__file__).resolve().parents[4]


class StoreAdapter:
    name = ""
    base_url = ""
    documentation_url = ""
    version_key = ""
    english_code = "en-gb"
    lithuanian_code = "lt-lt"
    sidebar_selector = ""
    content_selector = ""
    bootstrap_selector = ""
    special_path = ""
    home_path = "/"
    cart_path = ""

    def absolute(self, path: str) -> str:
        if path.startswith("http://") or path.startswith("https://"):
            return path
        suffix = path if path.startswith("/") else "/" + path
        return self.base_url.rstrip("/") + suffix

    def storefront_url(self, code: str, path: str) -> str:
        return self.absolute(path)

    def version(self) -> str:
        payload = json.loads((REPOSITORY / "platform-versions.json").read_text(encoding="utf-8"))
        version = payload.get(self.version_key)
        if not isinstance(version, str) or version == "":
            raise AssertionError("The platform version is missing from platform-versions.json.")
        return version

    def state_url(self) -> str:
        raise NotImplementedError

    def bootstrap_url(self) -> str:
        raise NotImplementedError

    def accepts_state_url(self, url: str) -> bool:
        raise NotImplementedError

    def login_admin(self, page: Page) -> None:
        raise NotImplementedError

    def open_settings(self, page: Page) -> None:
        raise NotImplementedError

    def switch_admin_language(self, page: Page, code: str) -> None:
        raise NotImplementedError

    def switch_storefront_language(self, page: Page, code: str) -> None:
        raise NotImplementedError

    def add_two_products(self, page: Page) -> None:
        raise NotImplementedError

    def register_customer(self, page: Page) -> None:
        raise NotImplementedError

    def logout_customer(self, page: Page) -> None:
        raise NotImplementedError

    def cart_page_count(self, page: Page) -> int:
        raise NotImplementedError

    def deny_limited_save(self, page: Page) -> None:
        raise NotImplementedError

    def uninstall(self, page: Page) -> None:
        raise NotImplementedError

    def install(self, page: Page) -> None:
        raise NotImplementedError
