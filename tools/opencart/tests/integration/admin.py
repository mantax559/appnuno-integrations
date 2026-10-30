import json
import shutil
from pathlib import Path
from typing import Any
from urllib.parse import parse_qs, urlparse

from playwright.sync_api import Dialog, Locator, Page

from integration.compatibility import (
    admin_method_route,
    admin_module_route,
    admin_page,
    admin_token_name,
    catalog_method_url,
    clear_opencart4_language_cache,
    header_event_injects_script,
    legacy_module_layout,
    modification_refresh_keeps_nested_files,
    opencart4,
    opencart_version_parts,
)
from integration.config import Runtime
from integration.database import (
    appnuno_event_count,
    configuration_value,
    connect,
    remember_admin_language_code,
    setting_value,
    update_english_language_code,
)
from integration.environment import capture_screenshot


def admin_token(runtime: Runtime, page: Page) -> str:
    token = parse_qs(urlparse(page.url).query).get(admin_token_name(runtime), [])
    if not token:
        raise AssertionError("OpenCart admin token was not present after login.")
    return token[0]


def click_module_list_button(runtime: Runtime, page: Page, row: Locator, selector: str) -> None:
    if legacy_module_layout(runtime):
        with page.expect_navigation():
            row.locator(selector).click()
        return
    row.locator(selector).click()


def login_admin(runtime: Runtime, page: Page, web_root: Path) -> None:
    shutil.rmtree(web_root / "install")
    page.goto(runtime.base_url + "/admin/")
    page.locator('input[name="username"]').fill(runtime.admin_user)
    page.locator('input[name="password"]').fill(runtime.admin_password)
    page.locator('button[type="submit"], input[type="submit"]').first.click()
    page.locator("#column-left").wait_for()


def module_list_row(runtime: Runtime, page: Page, token: str) -> Locator:
    if legacy_module_layout(runtime):
        page.goto(admin_page(runtime, "extension/module", token))
        return page.locator("#content table tr").filter(has_text="AppNuno Native Navigation").first
    # OpenCart 3 moved the extension type list from extension/extension to marketplace/extension.
    page.goto(admin_page(runtime, "marketplace/extension" if runtime.line == "3.0" or opencart4(runtime) else "extension/extension", token))
    module_filter = page.locator('select[name="type"]')
    module_filter.select_option(module_filter.locator("option").filter(has_text="Modules").first.get_attribute("value"))
    return page.locator("#extension tr").filter(has_text="AppNuno Native Navigation").filter(has=page.locator('a[href*="extension=appnuno"]')).first


def open_module_editor(runtime: Runtime, page: Page, token: str) -> None:
    page.goto(admin_page(runtime, admin_module_route(runtime), token))


def upload_extension(runtime: Runtime, page: Page, token: str) -> None:
    if opencart4(runtime):
        upload_opencart4_extension(runtime, page, token)
        return
    # OpenCart 3 uses marketplace/installer and can report success before its progress bar completes.
    page.goto(admin_page(runtime, "marketplace/installer" if runtime.line == "3.0" else "extension/installer", token))
    with page.expect_file_chooser() as chooser:
        page.locator("#button-upload").click()
    chooser.value.set_files(str(runtime.package))
    page.wait_for_function(
        """() => {
            const bar = document.querySelector('#progress-bar');
            if (!bar) {
                return false;
            }
            return bar.classList.contains('progress-bar-danger') || bar.classList.contains('progress-bar-success');
        }""",
        timeout=90000,
    )
    if page.locator("#progress-text .text-danger").count():
        raise AssertionError("OpenCart extension uploader failed: " + page.locator("#progress-text").inner_text())


def upload_opencart4_extension(runtime: Runtime, page: Page, token: str) -> None:
    # Uploading registers the package; the Install action extracts it into extension/appnuno.
    page.goto(admin_page(runtime, "marketplace/installer", token))

    def accept_dialog(dialog: Dialog) -> None:
        dialog.accept()

    page.on("dialog", accept_dialog)
    with page.expect_file_chooser() as chooser:
        page.locator("#button-upload").click()
    chooser.value.set_files(str(runtime.package))
    row = page.locator("#extension tr").filter(has_text="AppNuno").first
    row.wait_for(timeout=90000)
    row.locator("a.btn-success").click()
    page.wait_for_function(
        """() => {
            const bar = document.querySelector('#progress-bar');
            if (!bar) {
                return false;
            }
            const width = parseFloat(bar.style.width || '0');
            const danger = bar.classList.contains('progress-bar-danger') || bar.classList.contains('bg-danger');
            const success = width >= 100 && (bar.classList.contains('progress-bar-success') || bar.classList.contains('bg-success'));
            return danger || success;
        }""",
        timeout=90000,
    )
    page.remove_listener("dialog", accept_dialog)
    if page.locator("#progress-text .text-danger").count():
        raise AssertionError("OpenCart extension installer failed: " + page.locator("#progress-text").inner_text())


def refresh_modifications(runtime: Runtime, page: Page, token: str) -> None:
    page.goto(admin_page(runtime, "marketplace/modification" if runtime.line == "3.0" else "extension/modification", token))
    with page.expect_navigation():
        page.locator(".page-header .btn-info").click()
    page.get_by_text(f"AppNuno OpenCart {runtime.release_line}.x", exact=True).wait_for()


def add_legacy_store_language(runtime: Runtime, page: Page, name: str, code: str, locale: str) -> None:
    page.goto(admin_page(runtime, "localisation/language/add", admin_token(runtime, page)))
    page.locator('input[name="name"]').fill(name)
    if runtime.line in ("2.0", "2.1"):
        page.locator('input[name="code"]').fill(code)
        page.locator('input[name="locale"]').fill(locale)
        page.locator('input[name="image"]').fill(code + ".png")
        page.locator('input[name="directory"]').fill(code)
        filename = page.locator('input[name="filename"]')
        if filename.count():
            filename.fill(code)
    else:
        page.locator('select[name="code"]').select_option(code)
        if runtime.line != "2.2":
            page.locator('input[name="locale"]').fill(locale)
    page.locator('select[name="status"]').select_option("1")
    with page.expect_navigation():
        page.locator('button[form="form-language"]').click()
    page.locator(".alert-success").wait_for()
    if code not in page.locator("#content").inner_text():
        raise AssertionError(f"OpenCart did not list the added {name} language.")


def add_opencart4_store_language(runtime: Runtime, page: Page, web_root: Path, name: str, code: str, locale: str) -> None:
    page.goto(admin_page(runtime, admin_method_route(runtime, "localisation/language", "form"), admin_token(runtime, page)))
    page.locator('input[name="name"]').fill(name)
    code_field = page.locator('select[name="code"]')
    if code_field.count():
        code_field.select_option(code)
    else:
        page.locator('input[name="code"]').fill(code)
    page.locator('input[name="locale"]').fill(locale)
    status = page.locator('select[name="status"]')
    if status.count():
        status.select_option("1")
    else:
        page.locator('input[type="checkbox"][name="status"]').check()
    page.locator('input[name="sort_order"]').fill("1")
    page.locator('button[form="form-language"]').click()
    saved = False
    for _ in range(30):
        if page.locator(".alert-danger, .invalid-feedback.d-block").count():
            details = " ".join(page.locator(".alert-danger, .invalid-feedback.d-block").all_inner_texts())
            raise AssertionError("OpenCart did not save the language: " + details)
        with connect(runtime) as connection:
            with connection.cursor() as cursor:
                cursor.execute("SELECT COUNT(*) FROM oc_language WHERE code = %s", (code,))
                saved = cursor.fetchone()[0] == 1
        if saved:
            break
        page.wait_for_timeout(500)
    if not saved:
        raise AssertionError("OpenCart did not save the language.")
    page.goto(admin_page(runtime, "localisation/language", admin_token(runtime, page)))
    page.locator("#content").get_by_text(code).wait_for()
    clear_opencart4_language_cache(runtime, web_root)


def add_store_language(runtime: Runtime, page: Page, web_root: Path, name: str, code: str, locale: str) -> None:
    with connect(runtime, autocommit=True) as connection:
        with connection.cursor() as cursor:
            cursor.execute("SET GLOBAL sql_mode = 'NO_ENGINE_SUBSTITUTION'")
    if opencart4(runtime):
        add_opencart4_store_language(runtime, page, web_root, name, code, locale)
        return
    add_legacy_store_language(runtime, page, name, code, locale)


def switch_extension_admin_language(page: Page, code: str) -> None:
    page.wait_for_function("() => { const button = document.querySelector('#button-save'); return !!button && !button.disabled; }")
    page.locator("#input-admin-language").select_option(code)
    with page.expect_navigation():
        page.locator("#button-save").click()
    page.locator(".alert-success").wait_for()


def switch_legacy_admin_language(runtime: Runtime, page: Page, code: str, update_storefront: bool) -> None:
    if update_storefront:
        page.locator("#input-language").select_option(code)
    page.locator("#input-admin-language").select_option(code)
    with page.expect_navigation():
        page.locator('button[form="form-setting"]').click()
    saved_admin_language = configuration_value(runtime, "config_admin_language")
    if saved_admin_language != code:
        raise AssertionError(f"OpenCart 2.1 did not save the selected admin language; found {saved_admin_language!r}.")
    if update_storefront:
        saved_store_language = configuration_value(runtime, "config_language")
        if saved_store_language != code:
            raise AssertionError(f"OpenCart 2.1 did not save the selected store language; found {saved_store_language!r}.")


def switch_opencart4_admin_language(runtime: Runtime, page: Page, code: str, update_storefront: bool) -> None:
    admin_language = page.locator("#input-language-admin")
    if not admin_language.is_visible():
        for tab in page.locator('a[data-bs-toggle="tab"]').all():
            tab.click()
            if admin_language.is_visible():
                break
    admin_language.select_option(code)
    # OpenCart 4.0.2 reloads admin language files using the storefront language.
    if update_storefront or (opencart_version_parts(runtime) >= (4, 0, 2, 0) and opencart_version_parts(runtime) < (4, 1, 0, 0)):
        store_language = page.locator("#input-language-catalog")
        if not store_language.count():
            store_language = page.locator("#input-language")
        store_language.select_option(code)
    page.evaluate("""() => {
        document.querySelectorAll('[id^="input-meta-title"]').forEach(field => {
            if (!field.value) field.value = 'CI Store';
        });
    }""")
    # The zone list load enables the settings form; the OpenCart 4 save button has no #button-save id.
    page.wait_for_function("() => { const zone = document.querySelector('#input-zone'); return !!zone && zone.options.length > 0; }")
    page.locator('button[form="form-setting"]').click()
    page.locator(".alert-success, .alert-danger").first.wait_for()
    if page.locator(".alert-danger").count():
        details = " ".join(page.locator(".invalid-feedback.d-block").all_inner_texts())
        raise AssertionError("OpenCart did not save the store settings: " + page.locator(".alert-danger").inner_text() + " " + details)
    remember_admin_language_code(runtime, code)


def switch_admin_language(runtime: Runtime, page: Page, code: str, update_storefront: bool = False) -> None:
    page.goto(admin_page(runtime, "setting/setting", admin_token(runtime, page)))
    if opencart4(runtime):
        switch_opencart4_admin_language(runtime, page, code, update_storefront)
        return
    page.locator('a[href="#tab-local"]').click()
    if runtime.line in ("2.0", "2.1"):
        switch_legacy_admin_language(runtime, page, code, update_storefront)
        return
    switch_extension_admin_language(page, code)


def use_en_gb_language_code(runtime: Runtime, page: Page) -> None:
    page.goto(admin_page(runtime, "localisation/language", admin_token(runtime, page)))
    page.locator('a[href*="route=localisation/language/edit"]').first.click()
    page.locator('input[name="code"]').fill("en-gb")
    update_english_language_code(runtime)
    with page.expect_navigation():
        page.locator('button[form="form-language"]').click()
    page.locator(".alert-success").wait_for()
    switch_admin_language(runtime, page, "en-gb", update_storefront=True)


def install_module(runtime: Runtime, page: Page, token: str) -> None:
    module_row = module_list_row(runtime, page, token)
    module_row.wait_for()
    click_module_list_button(runtime, page, module_row, "a.btn-success")
    module_row.locator("a.btn-danger").wait_for()
    open_module_editor(runtime, page, token)
    page.locator("#default-tabs .appnuno-tab").first.wait_for(state="attached")


def add_cart_page_tab(page: Page) -> None:
    page.locator('a[href="#tab-navigation"]').click()
    page.locator("#tab-navigation").wait_for(state="visible")
    page.locator('button[data-add-tab="default-tabs"]').click()
    page_tab = page.locator("#default-tabs .appnuno-tab").last
    page_tab.locator('[data-field="id"]').fill("ci-cart-route-tab")
    page_tab.locator('[data-language-label="en-gb"]').fill("Cart route tab")
    page_tab.locator('[data-language-label="lt-lt"]').fill("Krepšelio skirtukas")
    page_tab.locator('[data-url-select]').select_option("__custom__")
    page_tab.locator('[data-language-url="en-gb"]').fill("/en/cart")
    page_tab.locator('[data-language-url="lt-lt"]').fill("/lt/krepselis")
    page_tab.locator('[data-field="icon"]').fill("cart-outline")
    page_tab.locator('[data-field="activeIcon"]').fill("cart")
    visibility = page_tab.locator('[data-tab-visibility]')
    if not visibility.is_checked():
        raise AssertionError("A newly added tab should be visible by default.")
    page_help = page_tab.locator('[data-pages]').locator('xpath=../..').locator('label [data-toggle="tooltip"], label [data-bs-toggle="tooltip"]')
    if not page_help.count():
        raise AssertionError("The page visibility explanation should use the standard OpenCart label tooltip.")
    page_help_title = page_help.get_attribute("title") or page_help.get_attribute("data-original-title") or page_help.get_attribute("data-bs-original-title") or ""
    if "Choose whether" not in page_help_title or not page_help.locator("xpath=..").get_attribute("for"):
        raise AssertionError("The page visibility explanation should use the standard OpenCart label tooltip.")
    all_pages = page_tab.locator('[data-all-pages]')
    if not all_pages.is_checked() or page_tab.locator('[data-pages]').is_visible():
        raise AssertionError("A new tab should show on all pages by default and hide its page list.")
    all_pages.uncheck()
    if not page_tab.locator('[data-pages]').is_visible():
        raise AssertionError("Unchecking all-pages should reveal the page list.")
    page_tab.locator('[data-pages] input[value="checkout/cart"]').check()
    page_tab.locator('[data-page-action="select-all"]').click()
    if page_tab.locator('[data-pages] input:not(:checked)').count():
        raise AssertionError("The select-all action did not check every page.")
    page_tab.locator('[data-page-action="deselect-all"]').click()
    if page_tab.locator('[data-pages] input:checked').count():
        raise AssertionError("The clear-pages action did not uncheck every page.")
    page_tab.locator('[data-pages] input[value="checkout/cart"]').check()


def add_contact_tab(page: Page) -> None:
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
    if not tab.locator('[data-tab-visibility]').is_checked():
        raise AssertionError("A newly added tab should be visible by default.")
    page.locator("#cart-tab-id").select_option("ci-added-tab")
    page.locator("#input-status").select_option("1")


def add_hidden_tab(page: Page) -> None:
    page.locator('button[data-add-tab="default-tabs"]').click()
    tab = page.locator("#default-tabs .appnuno-tab").last
    tab.locator('[data-field="id"]').fill("ci-hidden-tab")
    tab.locator('[data-language-label="en-gb"]').fill("CI Hidden Tab")
    tab.locator('[data-language-label="lt-lt"]').fill("CI paslėptas skirtukas")
    tab.locator('[data-url-select]').select_option("__custom__")
    tab.locator('[data-language-url="en-gb"]').fill("/en/hidden")
    tab.locator('[data-language-url="lt-lt"]').fill("/lt/pasleptas")
    tab.locator('[data-tab-visibility]').uncheck()
    if not page.locator('#default-tabs [data-tab-target="appnuno-tab-4"] .fa-eye-slash').count():
        raise AssertionError("The vertical tab list did not mark a hidden tab.")


def assert_cart_badge_is_in_default_panel(page: Page) -> None:
    default_panel = page.locator("#tab-navigation")
    if not default_panel.locator("#cart-tab-id").count():
        raise AssertionError("Cart badge selection is not inside the Default tabs panel.")
    if not page.locator("#cart-tab-id").locator("xpath=../../label").locator('[data-toggle="tooltip"], [data-bs-toggle="tooltip"]').count():
        raise AssertionError("The cart badge help should use the standard OpenCart label tooltip.")
    first_tab = page.locator('#default-tabs [data-tab-target="appnuno-tab-0"]')
    if first_tab.locator("xpath=following-sibling::button[@aria-label]").count() != 1:
        raise AssertionError("The remove-tab action should be next to its vertical tab button.")
    if page.locator("#default-tabs [data-add-tab]").locator("xpath=preceding-sibling::ul[contains(@class, 'nav')]").count() != 1:
        raise AssertionError("The add-tab button should appear below the vertical tab list.")
    if page.locator("#default-tabs .appnuno-tab button[aria-label]").count():
        raise AssertionError("The remove-tab action should not be inside the tab editor panel.")
    if page.locator("#tab-navigation .alert-info, #default-tabs .help-block, #default-tabs .form-text").count():
        raise AssertionError("Tab help text should be shown in tooltips rather than beneath the fields.")
    if not page.locator("#default-tabs .appnuno-tab .col-sm-2.control-label, #default-tabs .appnuno-tab .col-sm-2.col-form-label").count() or not page.locator("#default-tabs .appnuno-tab .col-sm-10").count():
        raise AssertionError("Tab field labels and controls should use the standard two/ten-column layout.")


def assert_module_settings_persisted(page: Page) -> None:
    saved_tab = page.locator("#default-tabs .appnuno-tab").nth(2)
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
    if saved_page_tab.locator('[data-all-pages]').is_checked():
        raise AssertionError("The cart page tab did not persist its restricted-page setting.")
    selected_pages = saved_page_tab.locator("[data-pages]").evaluate("list => Array.from(list.querySelectorAll('[data-page-choice]:checked')).map(input => input.value)")
    if selected_pages != ["checkout/cart"]:
        raise AssertionError(f"The cart page selection did not persist after reloading the settings page: {selected_pages}.")
    hidden_tab = page.locator("#default-tabs .appnuno-tab").nth(4)
    if hidden_tab.locator('[data-field="id"]').input_value() != "ci-hidden-tab" or hidden_tab.locator('[data-tab-visibility]').is_checked():
        raise AssertionError("The hidden tab did not remain saved in settings with external visibility turned off.")


def configure_back_button(page: Page) -> None:
    page.locator('a[href="#tab-back"]').click()
    page.locator("#tab-back").wait_for(state="visible")
    page.locator("#back-enabled").select_option("0")
    page.locator("#back-placement").select_option("bar")


def configure_colors(page: Page) -> None:
    page.locator('a[href="#tab-colors"]').click()
    page.locator("#tab-colors").wait_for(state="visible")
    page.locator("#light-background").fill("#112233")
    page.locator("#light-content").fill("#223344")
    page.locator("#dark-background").fill("#334455")
    page.locator("#dark-content").fill("#445566")


def configure_glass(page: Page) -> None:
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


def fill_builtin_tab_labels(page: Page) -> None:
    for index, labels in enumerate((("Home", "Pradžia"), ("Cart", "Krepšelis"))):
        page.locator(f'#default-tabs [data-tab-target="appnuno-tab-{index}"]').click()
        tab = page.locator("#default-tabs .appnuno-tab").nth(index)
        if not tab.is_visible() or page.locator("#default-tabs .appnuno-tab").nth(1 - index).is_visible():
            raise AssertionError("The vertical tab list should display only the selected tab panel.")
        tab.locator('[data-language-label="en-gb"]').fill(labels[0])
        tab.locator('[data-language-label="lt-lt"]').fill(labels[1])


def submit_module_settings(page: Page) -> None:
    page.locator('button[form="form-appnuno"]').click()
    page.locator(".alert-success").filter(has_text="modified AppNuno Native Navigation").wait_for(timeout=15000)
    page.reload()
    page.locator("#default-tabs .appnuno-tab").nth(2).wait_for(state="attached")


def save_module_settings(page: Page, artifact_dir: Path) -> None:
    assert_cart_badge_is_in_default_panel(page)
    fill_builtin_tab_labels(page)
    add_contact_tab(page)
    configure_back_button(page)
    configure_glass(page)
    configure_colors(page)
    add_cart_page_tab(page)
    add_hidden_tab(page)
    capture_screenshot(page, artifact_dir, "02-admin-settings-before-save.png")
    submit_module_settings(page)
    assert_module_settings_persisted(page)
    capture_screenshot(page, artifact_dir, "03-admin-settings-after-save.png")


def expect_module_language(runtime: Runtime, page: Page, phrases: tuple[str, ...]) -> None:
    open_module_editor(runtime, page, admin_token(runtime, page))
    page.locator("#default-tabs .appnuno-tab").first.wait_for(state="attached")
    content = page.locator("#content").inner_text()
    for href in ("#tab-back", "#tab-glass", "#tab-colors"):
        page.locator(f'a[href="{href}"]').click()
        page.locator(href).wait_for(state="visible")
        content += "\n" + page.locator(href).inner_text()
    missing = [phrase for phrase in phrases if phrase not in content]
    if missing:
        raise AssertionError("The AppNuno extension did not show these translated names: " + ", ".join(missing) + ".")


def check_admin_module_languages(runtime: Runtime, page: Page) -> None:
    switch_admin_language(runtime, page, "lt-lt")
    expect_module_language(runtime, page, (
        "AppNuno vietinė navigacija",
        "Redaguoti AppNuno vietinę navigaciją",
        "Numatytieji skirtukai",
        "Krepšelio ženkliuko skirtukas",
        "Rodoma puslapiuose",
        "Atžymėti visus",
        "Išorinis matomumas",
        "Rodyti šį skirtuką lankytojams",
        "Grįžimo mygtukas",
        "Stiklinė mygtukų juosta",
        "Navigacijos spalvos",
        "Šviesus režimas",
        "Skirtukų pavadinimai pagal kalbą",
        "Įjungta",
    ))
    switch_admin_language(runtime, page, "en-gb")
    expect_module_language(runtime, page, (
        "AppNuno Native Navigation",
        "Edit AppNuno Native Navigation",
        "Default tabs",
        "Cart badge tab",
        "Shown on pages",
        "Uncheck all",
        "External visibility",
        "Show this tab to visitors",
        "Back button",
        "Glass button bar",
        "Navigation colors",
        "Light mode",
        "Tab labels by language",
        "Enabled",
    ))


def decode_permissions(value: str) -> dict[str, Any]:
    if value.startswith("{"):
        return json.loads(value)
    parsed, end = parse_php_value(value, 0)
    if end != len(value) or not isinstance(parsed, dict):
        raise AssertionError("OpenCart user group permissions could not be read.")
    return parsed


def encode_permissions(original_value: str, permissions: dict[str, Any]) -> str:
    if original_value.startswith("{"):
        return json.dumps(permissions)
    return serialize_php_value(permissions)


def parse_php_value(value: str, index: int) -> tuple[Any, int]:
    kind = value[index]
    if kind == "a":
        count_end = value.index(":", index + 2)
        count = int(value[index + 2:count_end])
        index = count_end + 2
        items = []
        sequential = True
        for _ in range(count):
            key, index = parse_php_value(value, index)
            item, index = parse_php_value(value, index)
            sequential = sequential and isinstance(key, int) and key == len(items)
            items.append((key, item))
        if value[index] != "}":
            raise AssertionError("OpenCart user group permissions could not be read.")
        parsed = [item for _, item in items] if sequential else {key: item for key, item in items}
        return parsed, index + 1
    if kind == "s":
        length_end = value.index(":", index + 2)
        length = int(value[index + 2:length_end])
        start = length_end + 2
        return value[start:start + length], start + length + 2
    if kind == "i":
        end = value.index(";", index)
        return int(value[index + 2:end]), end + 1
    raise AssertionError("OpenCart user group permissions could not be read.")


def serialize_php_value(value: Any) -> str:
    if isinstance(value, dict):
        content = "".join(serialize_php_value(key) + serialize_php_value(item) for key, item in value.items())
        return f"a:{len(value)}:{{{content}}}"
    if isinstance(value, list):
        content = "".join(serialize_php_value(index) + serialize_php_value(item) for index, item in enumerate(value))
        return f"a:{len(value)}:{{{content}}}"
    if isinstance(value, str):
        return f's:{len(value)}:"{value}";'
    if isinstance(value, int):
        return f"i:{value};"
    raise AssertionError("OpenCart user group permissions could not be written.")


def save_module_status(page: Page, enabled: bool) -> None:
    page.locator("#input-status").select_option("1" if enabled else "0")
    with page.expect_navigation():
        page.locator('button[form="form-appnuno"]').click()
    page.locator(".alert-success").wait_for()


def assert_disabled_module_is_not_published(runtime: Runtime, page: Page, token: str) -> None:
    open_module_editor(runtime, page, token)
    save_module_status(page, False)
    page.goto(runtime.base_url + "/")
    if page.locator('script[src*="appnuno"][src*="bootstrap"]').count():
        raise AssertionError("The disabled module still injected its bootstrap script.")
    disabled = page.evaluate("async url => { const response = await fetch(url, { credentials: 'same-origin' }); return response.status; }", catalog_method_url(runtime, "state"))
    if disabled != 404:
        raise AssertionError(f"The disabled module state endpoint returned HTTP {disabled} instead of 404.")
    open_module_editor(runtime, page, token)
    save_module_status(page, True)


def assert_enabled_module_bootstraps(runtime: Runtime, page: Page) -> str:
    token = admin_token(runtime, page)
    page.goto(runtime.base_url + "/")
    page.wait_for_function("window.AppNunoIntegration && window.AppNunoIntegration.navigation")
    if page.locator('script[src*="appnuno"][src*="bootstrap"]').count() != 1:
        raise AssertionError("The re-enabled module did not restore exactly one bootstrap script.")
    return token


def uninstall_module(runtime: Runtime, page: Page, token: str) -> None:
    module_row = module_list_row(runtime, page, token)
    module_row.wait_for()

    def accept_uninstall_dialog(dialog: Dialog) -> None:
        dialog.accept()

    page.once("dialog", accept_uninstall_dialog)
    click_module_list_button(runtime, page, module_row, "a.btn-danger")
    module_row.locator("a.btn-success").wait_for()
    if setting_value(runtime, "module_appnuno_status") is not None or appnuno_event_count(runtime):
        raise AssertionError("Uninstalling the module did not remove its settings and catalog event.")


def assert_reinstall_restores_module(runtime: Runtime, page: Page, token: str) -> str:
    uninstall_module(runtime, page, token)
    module_row = module_list_row(runtime, page, token)
    module_row.wait_for()
    click_module_list_button(runtime, page, module_row, "a.btn-success")
    module_row.locator("a.btn-danger").wait_for()
    expected_events = 0 if runtime.line == "2.0" else 1
    if appnuno_event_count(runtime) != expected_events:
        raise AssertionError(f"Reinstalling the module registered {appnuno_event_count(runtime)} catalog events instead of {expected_events}.")
    token = admin_token(runtime, page)
    open_module_editor(runtime, page, token)
    save_module_status(page, True)
    page.goto(runtime.base_url + "/")
    page.wait_for_function("window.AppNunoIntegration && window.AppNunoIntegration.navigation")
    return token


def reject_invalid_module_configuration(runtime: Runtime, page: Page, token: str) -> None:
    open_module_editor(runtime, page, token)
    original_config = setting_value(runtime, "module_appnuno_config")
    if not original_config:
        raise AssertionError("The AppNuno module configuration was not persisted.")
    page.locator('#default-tabs [data-tab-target="appnuno-tab-0"]').click()
    page.locator('#default-tabs .appnuno-tab').first.locator('[data-field="id"]').fill("")
    page.locator('button[form="form-appnuno"]').click()
    page.locator(".alert-danger:not(#client-validation-error)").wait_for()
    if setting_value(runtime, "module_appnuno_config") != original_config:
        raise AssertionError("Invalid AppNuno settings changed the stored module configuration.")


def reject_save_without_modify_permission(runtime: Runtime, page: Page, token: str) -> None:
    open_module_editor(runtime, page, token)
    with connect(runtime, autocommit=True) as connection:
        with connection.cursor() as cursor:
            cursor.execute("SELECT user_group_id FROM oc_user WHERE username = %s", (runtime.admin_user,))
            group_id = cursor.fetchone()[0]
            cursor.execute("SELECT permission FROM oc_user_group WHERE user_group_id = %s", (group_id,))
            original_permission_value = cursor.fetchone()[0]
            original_permissions = decode_permissions(original_permission_value)
            restricted_permissions = json.loads(json.dumps(original_permissions))
            restricted_permissions["modify"] = [route for route in restricted_permissions.get("modify", []) if route != admin_module_route(runtime)]
            cursor.execute("UPDATE oc_user_group SET permission = %s WHERE user_group_id = %s", (encode_permissions(original_permission_value, restricted_permissions), group_id))
    try:
        page.locator("#input-status").select_option("0")
        page.locator('button[form="form-appnuno"]').click()
        server_error = page.locator(".alert-danger:not(#client-validation-error)")
        server_error.wait_for()
        if "permission" not in server_error.inner_text().lower():
            raise AssertionError("The module settings controller did not reject a user without modify permission.")
        if setting_value(runtime, "module_appnuno_status") != "1":
            raise AssertionError("A user without modify permission changed the module status.")
    finally:
        with connect(runtime, autocommit=True) as connection:
            with connection.cursor() as cursor:
                cursor.execute("UPDATE oc_user_group SET permission = %s WHERE user_group_id = %s", (original_permission_value, group_id))


def uploaded_module_controllers(runtime: Runtime, web_root: Path) -> tuple[Path, Path]:
    directory = ("module",) if legacy_module_layout(runtime) else ("extension", "module")
    return (
        web_root.joinpath("admin", "controller", *directory, "appnuno.php"),
        web_root.joinpath("catalog", "controller", *directory, "appnuno.php"),
    )


def assert_uploaded_module_files(runtime: Runtime, web_root: Path, present: bool) -> None:
    unexpected = [str(path) for path in uploaded_module_controllers(runtime, web_root) if path.is_file() != present]
    if unexpected:
        expected_state = "present" if present else "removed"
        raise AssertionError(f"Uploaded module files were not {expected_state}: {unexpected}.")


def delete_appnuno_modification(runtime: Runtime, page: Page, token: str) -> None:
    page.goto(admin_page(runtime, "marketplace/modification" if runtime.line == "3.0" else "extension/modification", token))
    row = page.locator("#form-modification tbody tr").filter(has_text=f"AppNuno OpenCart {runtime.release_line}.x")
    row.locator("input[name='selected[]']").check()

    def accept_delete_dialog(dialog: Dialog) -> None:
        dialog.accept()

    page.once("dialog", accept_delete_dialog)
    with page.expect_navigation():
        page.locator(".page-header button.btn-danger").click()
    if f"AppNuno OpenCart {runtime.release_line}.x" in page.locator("#form-modification").inner_text():
        raise AssertionError("OpenCart did not delete the AppNuno modification.")
    with page.expect_navigation():
        page.locator(".page-header a.btn-info").click()


def remove_opencart3_uploaded_extension(runtime: Runtime, page: Page, token: str) -> None:
    # OpenCart 3 deletes its recorded upload paths on the local filesystem. This action does not use FTP.
    page.goto(admin_page(runtime, "marketplace/installer", token))
    page.locator("#history tr").filter(has_text=runtime.package.name).locator("button.btn-danger").click()
    page.locator(".alert-success").wait_for()


def check_extension_removal(runtime: Runtime, page: Page, token: str, web_root: Path) -> None:
    # OpenCart 2 copies extension files through FTP and keeps no path list, so deleting the modification cannot remove them.
    delete_appnuno_modification(runtime, page, token)
    page.goto(runtime.base_url + "/")
    bootstrap_present = page.locator('script[src*="appnuno"][src*="bootstrap"]').count() > 0
    script_stays_while_module_enabled = header_event_injects_script(runtime) or modification_refresh_keeps_nested_files(runtime)
    if script_stays_while_module_enabled and not bootstrap_present:
        raise AssertionError("The storefront bootstrap script disappeared while the module was still enabled.")
    if not script_stays_while_module_enabled and bootstrap_present:
        raise AssertionError("Deleting the modification left the storefront bootstrap script in place.")
    assert_uploaded_module_files(runtime, web_root, True)
    uninstall_module(runtime, page, token)
    assert_uploaded_module_files(runtime, web_root, True)
    page.goto(runtime.base_url + "/")
    if page.locator('script[src*="appnuno"][src*="bootstrap"]').count():
        raise AssertionError("Uninstalling the module left the storefront bootstrap script in place.")
    if runtime.line == "3.0":
        remove_opencart3_uploaded_extension(runtime, page, token)
        assert_uploaded_module_files(runtime, web_root, False)


def check_module_lifecycle_and_admin_validation(runtime: Runtime, page: Page, token: str) -> str:
    reject_invalid_module_configuration(runtime, page, token)
    reject_save_without_modify_permission(runtime, page, token)
    assert_disabled_module_is_not_published(runtime, page, admin_token(runtime, page))
    token = assert_enabled_module_bootstraps(runtime, page)
    return assert_reinstall_restores_module(runtime, page, token)
