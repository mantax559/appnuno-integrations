from typing import Any

from playwright.sync_api import Locator, Page

from integration.adapter import StoreAdapter
from integration.browser import capture_screenshot


ENGLISH_PHRASES = (
    "Navigation",
    "Cart badge tab",
    "Shown on pages",
    "Manage pages",
    "Uncheck all",
    "Apply",
    "Cancel",
    "External visibility",
    "Visible",
    "Buttons",
    "Back button",
    "Glass button effect",
    "Choose an icon",
    "Colors",
    "Light mode",
    "Tab labels by language",
    "Enabled",
)

LITHUANIAN_PHRASES = (
    "Navigacija",
    "Krepšelio ženkliuko skirtukas",
    "Rodoma puslapiuose",
    "Tvarkyti puslapius",
    "Atžymėti visus",
    "Pritaikyti",
    "Atšaukti",
    "Išorinis matomumas",
    "Matomas",
    "Mygtukai",
    "Grįžimo mygtukas",
    "Stiklo mygtuko efektas",
    "Pasirinkite piktogramą",
    "Spalvos",
    "Šviesus režimas",
    "Skirtukų pavadinimai pagal kalbą",
    "Įjungta",
)


def choose_switch(page: Page, field_id: str, value: str) -> None:
    button = page.locator(f'[data-status-switch="{field_id}"] button[data-value="{value}"]')
    if button.count():
        button.click()
        return
    page.locator(f"#{field_id}").select_option(value)


def choose_destination(tab: Locator, label: str) -> str:
    select = tab.locator("[data-url-select]")
    value = select.evaluate(
        """(element, label) => {
            const option = Array.from(element.options).find(item => item.textContent.trim() === label);
            return option ? option.value : "";
        }""",
        label,
    )
    if not value:
        options = select.locator("option").evaluate_all("options => options.map(option => option.textContent.trim())")
        raise AssertionError(f"The destination list has no {label} option: {options}.")
    select.select_option(value)
    return value


def seed_builtin_tabs(page: Page, adapter: StoreAdapter) -> None:
    if page.locator("#default-tabs .appnuno-tab").count():
        return
    for tab_id, label, english, lithuanian in (
        ("home", "Home", "Home", "Pradžia"),
        ("cart", "Cart", "Cart", "Krepšelis"),
    ):
        page.locator('button[data-add-tab="default-tabs"]').dispatch_event("click")
        tab = page.locator("#default-tabs .appnuno-tab").last
        tab.locator('[data-field="id"]').wait_for(state="attached")
        tab.locator('[data-field="id"]').fill(tab_id)
        english_field = tab.locator(f'[data-language-label="{adapter.english_code}"]')
        lithuanian_field = tab.locator(f'[data-language-label="{adapter.lithuanian_code}"]')
        if not english_field.count() or not lithuanian_field.count():
            codes = tab.locator("[data-language-label]").evaluate_all("fields => fields.map(field => field.getAttribute('data-language-label'))")
            raise AssertionError(f"The settings editor did not list English and Lithuanian together: {codes}.")
        english_field.fill(english)
        lithuanian_field.fill(lithuanian)
        destination = choose_destination(tab, label)
        if tab_id == "home":
            adapter.home_path = destination
        else:
            adapter.cart_path = destination
    page.locator('#default-tabs [data-tab-target="appnuno-tab-0"]').click()


def assert_editor(page: Page, adapter: StoreAdapter) -> None:
    settings = page.locator(".appnuno-settings")
    settings.wait_for()
    brand = settings.locator(".appnuno-brand")
    logo = brand.locator("img.appnuno-brand-mark")
    version = adapter.version()
    version_style = brand.locator(".appnuno-version-group").evaluate(
        """element => ({
            gap: getComputedStyle(element).gap,
            border: getComputedStyle(element).borderTopWidth,
            radius: getComputedStyle(element).borderTopLeftRadius,
            versionBorder: getComputedStyle(element.querySelector('.appnuno-version')).borderRightWidth,
            documentationBorder: getComputedStyle(element.querySelector('.appnuno-documentation')).borderLeftWidth
        })"""
    )
    documentation = brand.locator(".appnuno-documentation")
    if logo.get_attribute("src") != "https://appnuno.com/logo-dark.png" or logo.get_attribute("alt") != "AppNuno" or brand.locator(".panel-title").count() or brand.locator(".appnuno-version").inner_text() != f"v{version}" or documentation.get_attribute("href") != adapter.documentation_url or version_style != {"gap": "0px", "border": "1px", "radius": "8px", "versionBorder": "1px", "documentationBorder": "0px"}:
        raise AssertionError(f"The module editor should show AppNuno branding and a joined version/documentation group: {version_style}.")
    page.wait_for_function("() => Array.from(document.styleSheets).some(sheet => sheet.href && sheet.href.includes('settings.css'))")
    page.wait_for_function("() => Array.from(document.scripts).some(script => script.src && script.src.includes('settings.js'))")
    page.wait_for_function("() => document.querySelector('.appnuno-section-switch').getAttribute('data-placed') === 'true'")
    seed_builtin_tabs(page, adapter)
    seeded = page.locator("#default-tabs .appnuno-tab [data-field='id']").evaluate_all("fields => fields.map(field => field.value)")
    if seeded != ["home", "cart"]:
        raise AssertionError(f"The settings editor did not seed Home and Cart: {seeded}.")
    for href in ("#tab-navigation", "#tab-glass", "#tab-colors"):
        page.locator(f'.appnuno-settings a[href="{href}"]').dispatch_event("click")
        page.locator(href).wait_for(state="visible")
        switches = page.locator(f"{href} [data-status-switch], .appnuno-status-field [data-status-switch]").evaluate_all(
            """controls => controls.filter(control => control.getClientRects().length && control.getBoundingClientRect().width > 0).map(control => ({
                id: control.getAttribute('data-status-switch'),
                selected: control.querySelectorAll('button[aria-pressed="true"]').length,
                placed: control.getAttribute('data-placed') === 'true'
            }))"""
        )
        if any(state["selected"] != 1 or not state["placed"] for state in switches):
            raise AssertionError(f"Every visible segmented switch in {href} should have one selected option and a positioned indicator: {switches}.")
    page.locator('a[href="#tab-navigation"]').dispatch_event("click")
    seeded = page.locator("#default-tabs .appnuno-tab [data-field='id']").evaluate_all("fields => fields.map(field => field.value)")
    if seeded != ["home", "cart"]:
        raise AssertionError(f"Section navigation removed seeded tabs: {seeded}.")
    if settings.locator(".appnuno-fieldset, fieldset").count():
        raise AssertionError("Buttons and navigation color settings should use panel sections, not fieldsets.")
    page.locator(".appnuno-page-manage").first.click()
    page.locator("#page-picker-dialog").wait_for(state="visible")
    design = settings.evaluate(
        """element => {
            const styles = getComputedStyle(element);
            const section = element.querySelector('.appnuno-section-switch');
            const sectionToolbar = element.querySelector('.appnuno-section-toolbar');
            const activeTab = element.querySelector('.appnuno-section-list > li.active > a, .appnuno-section-list > li > a.active');
            const inactiveTab = element.querySelector('.appnuno-section-list > li:not(.active) > a, .appnuno-section-list > li > a:not(.active)');
            const indicator = section.querySelector('.appnuno-switch-indicator');
            const panel = element.querySelector('.appnuno-setting-card');
            const title = panel.querySelector('.appnuno-setting-title');
            const fieldRow = panel.querySelector('.appnuno-setting-card-body > .appnuno-field');
            const description = fieldRow.querySelector('.appnuno-field-description');
            const panelBody = element.querySelector('.appnuno-body');
            const pageCheckbox = document.querySelector('#page-picker-dialog .appnuno-page-picker-choices input[type="checkbox"]:not(:checked)');
            const pageList = element.querySelector('[data-pages]');
            const status = element.querySelector('.appnuno-status-field');
            const controlLabel = element.querySelector('.appnuno-field-label');
            return {
                radius: styles.borderRadius,
                panelPadding: getComputedStyle(panelBody).padding,
                switchPadding: getComputedStyle(section).padding,
                checkboxBorder: getComputedStyle(pageCheckbox).borderTopColor,
                checkboxBackground: getComputedStyle(pageCheckbox).backgroundColor,
                pageListBorder: getComputedStyle(pageList).borderTopColor,
                pageListBackground: getComputedStyle(pageList).backgroundColor,
                statusRightOfSections: status.getBoundingClientRect().left >= section.getBoundingClientRect().right,
                sectionLeftAligned: Math.abs(section.getBoundingClientRect().left - sectionToolbar.getBoundingClientRect().left) <= 1,
                primary: styles.getPropertyValue('--appnuno-primary').trim(),
                tabSurface: getComputedStyle(section).backgroundColor,
                tabBorder: getComputedStyle(section).borderTopWidth + ' ' + getComputedStyle(section).borderTopStyle + ' ' + getComputedStyle(section).borderTopColor,
                activeTabSurface: getComputedStyle(indicator).backgroundColor,
                indicatorRadius: getComputedStyle(indicator).borderRadius,
                indicatorTransition: getComputedStyle(indicator).transitionProperty,
                panelBorder: getComputedStyle(panel).borderTopWidth,
                panelBackground: getComputedStyle(panel).backgroundColor,
                titleBorder: getComputedStyle(title).borderTopWidth,
                titleBottomBorder: getComputedStyle(title).borderBottomWidth,
                titleRadius: getComputedStyle(title).borderRadius,
                titlePadding: getComputedStyle(title).padding,
                titleFontSize: getComputedStyle(title).fontSize,
                fieldRowDisplay: getComputedStyle(fieldRow).display,
                fieldRowBorder: getComputedStyle(fieldRow).borderBottomWidth,
                labelFontSize: getComputedStyle(controlLabel).fontSize,
                descriptionFontSize: getComputedStyle(description).fontSize,
                activeColor: getComputedStyle(activeTab).color,
                controlLabelColor: getComputedStyle(controlLabel).color,
                sameWeight: getComputedStyle(activeTab).fontWeight === getComputedStyle(inactiveTab).fontWeight,
                compact: section.getBoundingClientRect().width < element.getBoundingClientRect().width - 120
            };
        }"""
    )
    page.locator('#page-picker-dialog .appnuno-dialog-footer [data-appnuno-dismiss="dialog"]').click()
    page.locator("#page-picker-dialog").wait_for(state="hidden")
    expected = {
        "radius": "12px",
        "panelPadding": "24px",
        "switchPadding": "4px",
        "checkboxBorder": "rgb(228, 228, 231)",
        "checkboxBackground": "rgb(255, 255, 255)",
        "pageListBorder": "rgb(228, 228, 231)",
        "pageListBackground": "rgb(249, 249, 249)",
        "statusRightOfSections": True,
        "sectionLeftAligned": True,
        "primary": "#18181b",
        "tabSurface": "rgb(244, 244, 245)",
        "tabBorder": "0px none rgb(24, 24, 27)",
        "activeTabSurface": "rgb(255, 255, 255)",
        "indicatorRadius": "7px",
        "indicatorTransition": "transform, width, height",
        "panelBorder": "1px",
        "panelBackground": "rgb(255, 255, 255)",
        "titleBorder": "0px",
        "titleBottomBorder": "1px",
        "titleRadius": "0px",
        "titlePadding": "16px 24px",
        "titleFontSize": "18px",
        "fieldRowDisplay": "grid",
        "fieldRowBorder": "0px",
        "labelFontSize": "16px",
        "descriptionFontSize": "13px",
        "activeColor": "rgb(24, 24, 27)",
        "controlLabelColor": "rgb(0, 0, 0)",
        "sameWeight": True,
        "compact": True,
    }
    if design != expected:
        raise AssertionError(f"The settings editor did not load the neutral AppNuno design system: {design}.")
    page.emulate_media(reduced_motion="reduce")
    duration = page.locator(".appnuno-section-switch .appnuno-switch-indicator").evaluate("element => getComputedStyle(element).transitionDuration")
    page.emulate_media(reduced_motion="no-preference")
    if duration != "0s":
        raise AssertionError("The switch indicator should respect the reduced-motion preference.")
    if not page.locator('.appnuno-toolbar button[form="form-appnuno"]').is_visible():
        raise AssertionError("The settings editor must expose its own save toolbar.")


def assert_responsive_layout(page: Page, adapter: StoreAdapter) -> None:
    original = page.viewport_size or {"width": 1440, "height": 1000}
    original_ids = page.locator("#default-tabs .appnuno-tab [data-field='id']").evaluate_all("fields => fields.map(field => field.value)")
    if "home" not in original_ids or "cart" not in original_ids:
        raise AssertionError(f"Seeded tabs were missing before the viewport sweep: {original_ids}.")
    original_keys = page.locator("#default-tabs .appnuno-tab").evaluate_all("tabs => tabs.map(tab => tab.dataset.tabKey)")
    try:
        for width in (320, 360, 375, 390, 414, 540, 767, 768, 820, 1024, 1280, 1440, 1600, 1920):
            page.set_viewport_size({"width": width, "height": 1000})
            for section in ("#tab-navigation", "#tab-glass", "#tab-colors"):
                before = page.locator("#default-tabs .appnuno-tab [data-field='id']").evaluate_all("fields => fields.map(field => field.value)")
                page.locator(f'.appnuno-settings a[href="{section}"]').dispatch_event("click")
                page.locator(section).wait_for(state="visible")
                seeded = page.locator("#default-tabs .appnuno-tab [data-field='id']").evaluate_all("fields => fields.map(field => field.value)")
                if "home" not in seeded or "cart" not in seeded:
                    raise AssertionError(f"Seeded tabs disappeared at {width}px {section}. Before: {before}. After: {seeded}.")
                measurement = page.evaluate(
                    """({ section, sidebarSelector, contentSelector }) => {
                        const settings = document.querySelector('.appnuno-settings');
                        const content = document.querySelector(contentSelector) || document.querySelector('#main-div') || document.querySelector('#content') || document.querySelector('#wpbody-content');
                        const sidebar = document.querySelector(sidebarSelector);
                        const panel = document.querySelector(section);
                        if (!settings || !content || !panel) {
                            return { missing: true, settings: !!settings, content: !!content, panel: !!panel };
                        }
                        const settingsRect = settings.getBoundingClientRect();
                        const contentRect = content.getBoundingClientRect();
                        const sidebarRect = sidebar ? sidebar.getBoundingClientRect() : { left: 0, right: 0, width: 0 };
                        const sidebarVisible = !!sidebar && getComputedStyle(sidebar).display !== 'none' && sidebarRect.width > 20 && sidebarRect.right > 0 && sidebarRect.left < window.innerWidth;
                        const overflowing = Array.from(panel.querySelectorAll('*')).filter(element => {
                            if (!element.getClientRects().length || element.tagName === 'OPTION') return false;
                            const rect = element.getBoundingClientRect();
                            return rect.width > 0 && (rect.right > settingsRect.right + 1 || rect.left < settingsRect.left - 1);
                        }).slice(0, 6).map(element => ({
                            tag: element.tagName,
                            className: typeof element.className === 'string' ? element.className.slice(0, 80) : '',
                            left: element.getBoundingClientRect().left,
                            right: element.getBoundingClientRect().right
                        }));
                        return {
                            missing: false,
                            viewportWidth: window.innerWidth,
                            documentWidth: Math.max(document.documentElement.scrollWidth, document.body.scrollWidth),
                            settingsRight: settingsRect.right,
                            settingsLeft: settingsRect.left,
                            contentLeft: contentRect.left,
                            contentRight: contentRect.right,
                            settingsClientWidth: settings.clientWidth,
                            settingsScrollWidth: settings.scrollWidth,
                            sectionClientWidth: panel.clientWidth,
                            sectionScrollWidth: panel.scrollWidth,
                            sidebarVisible,
                            sidebarRight: sidebarRect.right,
                            overflowing
                        };
                    }""",
                    {"section": section, "sidebarSelector": adapter.sidebar_selector, "contentSelector": adapter.content_selector},
                )
                if measurement.get("missing"):
                    raise AssertionError(f"The settings layout could not be measured ({width}px {section}): {measurement}.")
                context = f"{width}px {section}"
                if measurement["documentWidth"] > measurement["viewportWidth"] + 1:
                    raise AssertionError(f"The settings page overflows horizontally ({context}): {measurement}.")
                if measurement["settingsScrollWidth"] > measurement["settingsClientWidth"] + 1 or measurement["overflowing"]:
                    raise AssertionError(f"The settings panel clips or scrolls horizontally ({context}): {measurement}.")
                if measurement["settingsLeft"] < measurement["contentLeft"] - 1 or measurement["settingsRight"] > measurement["contentRight"] + 1:
                    raise AssertionError(f"The settings panel escapes the admin content area ({context}): {measurement}.")
                if width >= 1024 and measurement["sidebarVisible"] and measurement["settingsLeft"] < measurement["sidebarRight"] - 1:
                    raise AssertionError(f"The settings content is covered by the admin sidebar ({context}): {measurement}.")
        page.locator('a[href="#tab-navigation"]').click()
        page.set_viewport_size({"width": 390, "height": 844})
        targets = page.evaluate(
            """() => [
                document.querySelector('[data-status-switch="input-status"] button[aria-pressed="true"]'),
                document.querySelector('#default-tabs [data-add-tab="default-tabs"]'),
                document.querySelector('#default-tabs [data-tab-target]')
            ].map(element => element.getBoundingClientRect().height)"""
        )
        if any(height < 44 for height in targets):
            raise AssertionError(f"Mobile settings controls must provide 44px touch targets: {targets}.")
        edges = page.locator("#default-tabs ul.appnuno-tab-list > li > a").evaluate_all("elements => elements.map(element => element.getBoundingClientRect().left)")
        if edges and any(abs(left - edges[0]) > 1 for left in edges):
            raise AssertionError(f"Mobile tab navigation items must align on a shared edge: {edges}.")
        original_count = page.locator("#default-tabs .appnuno-tab").count()
        page.locator('#default-tabs [data-add-tab="default-tabs"]').dispatch_event("click")
        temporary = page.locator("#default-tabs .appnuno-tab").last
        temporary_key = temporary.get_attribute("data-tab-key")
        page.locator(f'#default-tabs [data-tab-target="{temporary_key}"] [data-remove-tab]').dispatch_event("click")
        remaining = page.locator("#default-tabs .appnuno-tab [data-field='id']").evaluate_all("fields => fields.map(field => field.value)")
        if page.locator("#default-tabs .appnuno-tab").count() != original_count or "home" not in remaining or "cart" not in remaining:
            raise AssertionError(f"Adding and removing a tab did not keep the seeded tabs: {remaining}.")
    finally:
        page.set_viewport_size(original)
        page.locator('a[href="#tab-navigation"]').dispatch_event("click")
        current = page.locator("#default-tabs .appnuno-tab").evaluate_all("tabs => tabs.map(tab => tab.dataset.tabKey)")
        for key in current:
            if key not in original_keys:
                page.locator(f'#default-tabs [data-tab-target="{key}"] [data-remove-tab]').dispatch_event("click")
        page.locator('#default-tabs [data-tab-target="appnuno-tab-0"]').dispatch_event("click")


def focus_appearance(target: Locator) -> dict[str, Any]:
    return target.evaluate(
        """async element => {
            await Promise.all(element.getAnimations().map(animation => animation.finished.catch(() => {})));
            const style = getComputedStyle(element);
            const neutral = value => {
                const colors = value.match(/rgba?\\([^)]+\\)/g) || [];
                return colors.every(color => {
                    const channels = color.match(/[\\d.]+/g).slice(0, 3).map(Number);
                    return Math.max(...channels) - Math.min(...channels) <= 12;
                });
            };
            return {
                focused: document.activeElement === element,
                focusVisible: element.matches(':focus-visible'),
                outlineStyle: style.outlineStyle,
                outlineWidth: style.outlineWidth,
                outlineColor: style.outlineColor,
                shadow: style.boxShadow,
                neutralShadow: neutral(style.boxShadow) && !style.boxShadow.includes('inset'),
                neutralBorder: neutral(style.borderColor),
                neutralOutline: neutral(style.outlineColor)
            };
        }"""
    )


def assert_mouse_and_keyboard_focus(page: Page, target: Locator) -> None:
    target.click()
    mouse = focus_appearance(target)
    if not mouse["focused"] or (mouse["outlineStyle"] != "none" and mouse["outlineWidth"] != "0px") or not mouse["neutralShadow"]:
        raise AssertionError(f"Mouse-clicked links and buttons must not show a focus outline or host focus shadow: {mouse}.")
    page.keyboard.press("Shift+Tab")
    if target.evaluate("element => document.activeElement === element"):
        raise AssertionError("Shift+Tab must move keyboard focus away from the clicked control.")
    page.keyboard.press("Tab")
    keyboard = focus_appearance(target)
    if not keyboard["focused"] or not keyboard["focusVisible"] or keyboard["outlineStyle"] != "solid" or keyboard["outlineWidth"] != "2px" or keyboard["outlineColor"] != "rgb(113, 113, 122)" or not keyboard["neutralShadow"]:
        raise AssertionError(f"Keyboard Tab must reveal the neutral 2px focus outline: {keyboard}.")
    page.locator(".appnuno-brand").click()
    if target.evaluate("element => document.activeElement === element"):
        raise AssertionError("Clicking the settings brand must move focus away from the keyboard-focused control.")


def assert_settings_focus(page: Page) -> None:
    assert_mouse_and_keyboard_focus(page, page.locator('a[href="#tab-glass"]'))
    page.locator('a[href="#tab-navigation"]').click()
    assert_mouse_and_keyboard_focus(page, page.locator("#default-tabs [data-tab-target]").first)
    assert_mouse_and_keyboard_focus(page, page.locator('[data-status-switch="input-status"] button[data-value="1"]'))
    field = page.locator('#default-tabs .appnuno-tab.active input[type="text"]').first
    field.click()
    appearance = focus_appearance(field)
    if not appearance["focused"] or not appearance["neutralBorder"] or not appearance["neutralShadow"] or not appearance["neutralOutline"]:
        raise AssertionError(f"Input focus must use neutral borders and shadows: {appearance}.")
    checkbox = page.locator("#default-tabs .appnuno-tab.active [data-all-pages]")
    decorations = checkbox.evaluate(
        """element => ['::before', '::after'].map(pseudo => {
            const style = getComputedStyle(element, pseudo);
            return { content: style.content, display: style.display };
        })"""
    )
    if any(decoration["content"] not in ("none", "normal") and decoration["display"] != "none" for decoration in decorations):
        raise AssertionError(f"Checkboxes must not inherit a second host checkmark: {decorations}.")
    page_button = page.locator("#default-tabs .appnuno-tab.active [data-page-manage]")
    page_button.focus()
    page.keyboard.press("Tab")
    page.keyboard.press("Shift+Tab")
    appearance = focus_appearance(page_button)
    if not appearance["focused"] or not appearance["focusVisible"] or appearance["outlineStyle"] != "solid" or appearance["outlineWidth"] != "2px" or not appearance["neutralBorder"] or not appearance["neutralShadow"]:
        raise AssertionError(f"Keyboard focus on the page control must stay visible and neutral: {appearance}.")
    page.locator('a[href="#tab-navigation"]').click()


def assert_dialog_bounds(page: Page, dialog_id: str, sizes: tuple[tuple[int, int], ...]) -> None:
    original = page.viewport_size or {"width": 1280, "height": 900}
    try:
        for width, height in sizes:
            page.set_viewport_size({"width": width, "height": height})
            bounds = page.locator(dialog_id).evaluate(
                """async dialog => {
                    await Promise.all(dialog.getAnimations({ subtree: true }).map(animation => animation.finished.catch(() => {})));
                    const content = dialog.querySelector('.appnuno-dialog-panel');
                    const body = dialog.querySelector('.appnuno-dialog-body');
                    const rect = content.getBoundingClientRect();
                    return {
                        left: rect.left,
                        right: rect.right,
                        top: rect.top,
                        bottom: rect.bottom,
                        viewportWidth: window.innerWidth,
                        viewportHeight: window.innerHeight,
                        contentClientWidth: content.clientWidth,
                        contentScrollWidth: content.scrollWidth,
                        bodyClientWidth: body.clientWidth,
                        bodyScrollWidth: body.scrollWidth
                    };
                }"""
            )
            if bounds["left"] < -1 or bounds["top"] < -1 or bounds["right"] > bounds["viewportWidth"] + 1 or bounds["bottom"] > bounds["viewportHeight"] + 1:
                raise AssertionError(f"{dialog_id} must remain within the {width}x{height} viewport: {bounds}.")
            if bounds["contentScrollWidth"] > bounds["contentClientWidth"] + 1 or bounds["bodyScrollWidth"] > bounds["bodyClientWidth"] + 1:
                raise AssertionError(f"{dialog_id} must not clip or scroll horizontally at {width}x{height}: {bounds}.")
    finally:
        page.set_viewport_size(original)


def choose_catalog_icon(page: Page, tab: Locator, icon_id: str) -> None:
    original = page.viewport_size or {"width": 1280, "height": 900}
    try:
        page.set_viewport_size({"width": 390, "height": 844})
        tab.locator("[data-icon-picker]").click()
        dialog = page.locator("#icon-picker-dialog")
        dialog.wait_for(state="visible")
        design = dialog.evaluate(
            """element => {
                const spinner = element.querySelector('.appnuno-spinner');
                return {
                    radius: getComputedStyle(element.querySelector('.appnuno-dialog-panel')).borderRadius,
                    gridDisplay: getComputedStyle(element.querySelector('#icon-picker-grid')).display,
                    spinnerSize: getComputedStyle(spinner).width,
                    spinnerBorder: getComputedStyle(spinner).borderTopWidth
                };
            }"""
        )
        if design != {"radius": "12px", "gridDisplay": "grid", "spinnerSize": "48px", "spinnerBorder": "4px"}:
            raise AssertionError(f"The icon picker did not load the shared modal design: {design}.")
        search_height = page.locator("#icon-picker-search").evaluate("element => element.getBoundingClientRect().height")
        if search_height < 44:
            raise AssertionError(f"Mobile icon picker controls must provide 44px touch targets: {search_height}.")
        page.locator("#icon-picker-grid button").first.wait_for(state="visible", timeout=30000)
        page.wait_for_function(
            """() => {
                const loading = document.querySelector('#icon-picker-loading');
                const images = Array.from(document.querySelectorAll('#icon-picker-grid img'));
                return loading.hidden && images.length > 0 && images.every(image => image.complete && image.naturalWidth > 0);
            }""",
            timeout=30000,
        )
        assert_dialog_bounds(page, "#icon-picker-dialog", ((320, 568), (390, 844), (667, 375), (768, 600), (1024, 768), (1600, 900), (1920, 1080)))
        page.locator("#icon-picker-search").fill(icon_id)
        option = page.locator(f'#icon-picker-grid button[title="{icon_id}"]').first
        option.wait_for(state="visible", timeout=30000)
        option.click()
        dialog.wait_for(state="hidden")
        icon = page.evaluate("value => JSON.parse(value)", tab.locator('[data-field="icon"]').input_value())
        if icon.get("id") != icon_id:
            raise AssertionError(f"The picker did not save the selected catalog icon: {icon}.")
        if tab.locator('[data-field="activeIcon"]').count() or tab.locator("[data-icon-picker] img").count() != 1:
            raise AssertionError("The selected catalog icon preview should contain exactly one image and no separate active icon.")
    finally:
        page.set_viewport_size(original)


def assert_cart_badge(page: Page) -> None:
    layout = page.locator("#default-tabs").evaluate(
        """element => Array.from(element.querySelectorAll('.appnuno-tab')).map(card => {
            const destination = card.querySelector('[data-url-select]').closest('[data-field-group]');
            const badge = card.querySelector('[data-badge-source]').closest('[data-field-group]');
            return {
                followsDestination: destination.nextElementSibling === badge,
                hasSource: !!card.querySelector('[data-badge-source]'),
                options: Array.from(card.querySelector('[data-badge-source]').options).map(option => option.value),
                selected: card.querySelector('[data-field="id"]').value
            };
        })"""
    )
    if not layout or any(not card["followsDestination"] or not card["hasSource"] or card["options"] != ["", "cart"] for card in layout):
        raise AssertionError(f"Each tab should have a source selector immediately after Destination: {layout}.")
    home_source = page.locator("#default-tabs .appnuno-tab").nth(0).locator('[data-badge-source]')
    cart_source = page.locator("#default-tabs .appnuno-tab").nth(1).locator('[data-badge-source]')
    if cart_source.input_value() != "cart":
        raise AssertionError("The legacy cart tab selection should be loaded as the cart source.")
    home_source.select_option("cart")
    if cart_source.input_value() != "cart":
        raise AssertionError("Assigning a source to one tab must not change another tab's source.")
    home_source.select_option("")
    if home_source.input_value() or cart_source.input_value() != "cart":
        raise AssertionError("Disabling a tab badge must not affect other tab assignments.")


def add_contact_tab(page: Page, adapter: StoreAdapter) -> None:
    page.locator('button[data-add-tab="default-tabs"]').dispatch_event("click")
    tab = page.locator("#default-tabs .appnuno-tab").last
    tab.locator('[data-field="id"]').fill("ci-added-tab")
    tab.locator(f'[data-language-label="{adapter.english_code}"]').fill("CI Added Tab")
    tab.locator(f'[data-language-label="{adapter.lithuanian_code}"]').fill("CI pridėtas skirtukas")
    tab.locator("[data-url-select]").select_option("__custom__")
    tab.locator(f'[data-language-url="{adapter.english_code}"]').fill("/en/contact")
    tab.locator(f'[data-language-url="{adapter.lithuanian_code}"]').fill("/lt/kontaktai")
    choose_catalog_icon(page, tab, "duo:user")
    if tab.locator("[data-tab-visibility]").input_value() != "1":
        raise AssertionError("A newly added tab should be visible by default.")
    tab.locator('[data-badge-source]').select_option("cart")
    choose_switch(page, "input-status", "1")


def add_page_tab(page: Page, adapter: StoreAdapter) -> None:
    page.locator('a[href="#tab-navigation"]').click()
    page.locator('button[data-add-tab="default-tabs"]').dispatch_event("click")
    tab = page.locator("#default-tabs .appnuno-tab").last
    tab.locator('[data-field="id"]').fill("ci-cart-route-tab")
    tab.locator(f'[data-language-label="{adapter.english_code}"]').fill("Cart route tab")
    tab.locator(f'[data-language-label="{adapter.lithuanian_code}"]').fill("Krepšelio skirtukas")
    tab.locator("[data-url-select]").select_option("__custom__")
    tab.locator(f'[data-language-url="{adapter.english_code}"]').fill("/en/cart")
    tab.locator(f'[data-language-url="{adapter.lithuanian_code}"]').fill("/lt/krepselis")
    choose_catalog_icon(page, tab, "duo:shopping-bag")
    summary = tab.locator("[data-page-manage]")
    if not tab.locator("[data-all-pages]").is_checked() or summary.inner_text() != "Showing on all pages":
        raise AssertionError("A new tab should show on all pages by default and summarize that selection.")
    summary.click()
    dialog = page.locator("#page-picker-dialog")
    dialog.wait_for(state="visible")
    assert_dialog_bounds(page, "#page-picker-dialog", ((320, 568), (390, 844), (667, 375), (768, 600), (1600, 900)))
    if not dialog.locator("#page-picker-all").is_checked() or dialog.locator("#page-picker-choices [data-page-choice]").count() < 10:
        raise AssertionError("The page manager should open with all-pages selected and the available routes listed.")
    dialog.locator("#page-picker-all").uncheck()
    dialog.locator("#page-picker-select-all").click()
    if dialog.locator("#page-picker-choices [data-page-choice]:not(:checked)").count():
        raise AssertionError("The modal select-all action did not check every page.")
    dialog.locator("#page-picker-clear").click()
    if dialog.locator("#page-picker-choices [data-page-choice]:checked").count():
        raise AssertionError("The modal clear-pages action did not uncheck every page.")
    dialog.locator('#page-picker-choices [data-page-choice][value="common/home"]').check()
    dialog.locator('#page-picker-choices [data-page-choice][value="checkout/cart"]').check()
    dialog.locator("#page-picker-apply").click()
    dialog.wait_for(state="hidden")
    if summary.inner_text() != "Showing on 2 pages":
        raise AssertionError("Applying a two-page selection should update its summary.")
    summary.click()
    dialog.wait_for(state="visible")
    dialog.locator("#page-picker-clear").click()
    dialog.locator("#page-picker-apply").click()
    dialog.wait_for(state="hidden")
    if summary.inner_text() != "Showing on no pages":
        raise AssertionError("Clearing all modal choices should hide the tab on every page and update its summary.")
    summary.click()
    dialog.wait_for(state="visible")
    dialog.locator('#page-picker-choices [data-page-choice][value="common/home"]').check()
    dialog.locator('#page-picker-choices [data-page-choice][value="checkout/cart"]').check()
    dialog.locator("#page-picker-apply").click()
    dialog.wait_for(state="hidden")
    selected = tab.locator("[data-pages]").evaluate("list => Array.from(list.querySelectorAll('[data-page-choice]:checked')).map(input => input.value)")
    if selected != ["common/home", "checkout/cart"]:
        raise AssertionError(f"Applying the modal should keep the home and cart routes: {selected}.")


def add_hidden_tab(page: Page, adapter: StoreAdapter) -> None:
    page.locator('button[data-add-tab="default-tabs"]').dispatch_event("click")
    tab = page.locator("#default-tabs .appnuno-tab").last
    tab.locator('[data-field="id"]').fill("ci-hidden-tab")
    tab.locator(f'[data-language-label="{adapter.english_code}"]').fill("CI Hidden Tab")
    tab.locator(f'[data-language-label="{adapter.lithuanian_code}"]').fill("CI paslėptas skirtukas")
    tab.locator("[data-url-select]").select_option("__custom__")
    tab.locator(f'[data-language-url="{adapter.english_code}"]').fill("/en/hidden")
    tab.locator(f'[data-language-url="{adapter.lithuanian_code}"]').fill("/lt/pasleptas")
    tab.locator('[data-status-switch] button[data-value="0"]').click()
    tab_key = tab.get_attribute("data-tab-key")
    marker = page.locator(f'#default-tabs [data-tab-target="{tab_key}"] [data-visibility-indicator]')
    if marker.count() != 1 or marker.get_attribute("data-appnuno-tooltip") != "Hidden":
        raise AssertionError("A hidden tab should show the eye icon with a Hidden tooltip.")
    if marker.evaluate("icon => icon.nextElementSibling && icon.nextElementSibling.hasAttribute('data-remove-tab')") is not True:
        raise AssertionError("The hidden icon should sit immediately before the remove action.")


def configure_visuals(page: Page) -> None:
    page.locator('a[href="#tab-glass"]').click()
    page.locator("#tab-glass").wait_for(state="visible")
    for field_id, count in (("back-enabled", 2), ("back-placement", 2), ("glass-enabled", 2), ("glass-color-scheme", 3), ("glass-style", 3), ("glass-animate", 2), ("glass-interactive", 2)):
        if page.locator(f'[data-status-switch="{field_id}"] button[data-value]').count() != count:
            raise AssertionError(f"{field_id} should use the shared segmented switch control.")
    if page.locator("#tab-glass .appnuno-field-description").count() != 10:
        raise AssertionError("Back and glass settings should show explanatory text below every field label.")
    choose_switch(page, "back-placement", "bar")
    choose_switch(page, "back-enabled", "0")
    choose_switch(page, "glass-enabled", "1")
    choose_switch(page, "glass-color-scheme", "dark")
    choose_switch(page, "glass-style", "clear")
    choose_switch(page, "glass-animate", "0")
    page.locator("#glass-duration").fill("0.7")
    choose_switch(page, "glass-interactive", "0")
    page.locator("#glass-tint-color").fill("#123456")
    page.locator("#glass-tint-alpha").fill("0.4")
    page.locator('a[href="#tab-colors"]').click()
    page.locator("#tab-colors").wait_for(state="visible")
    if page.locator("#tab-colors .appnuno-field-description").count() != 4:
        raise AssertionError("Navigation color explanations should appear beneath labels without tooltips.")
    page.locator("#light-background").fill("#112233")
    page.locator("#light-content").fill("#223344")
    page.locator("#dark-background").fill("#334455")
    page.locator("#dark-content").fill("#445566")


def fill_builtin_labels(page: Page, adapter: StoreAdapter) -> None:
    icons = ("duo:dashboard", "duo:shopping-bag")
    labels = (("Home", "Pradžia"), ("Cart", "Krepšelis"))
    for index, ((english, lithuanian), icon_id) in enumerate(zip(labels, icons)):
        page.locator(f'#default-tabs [data-tab-target="appnuno-tab-{index}"]').click()
        tab = page.locator("#default-tabs .appnuno-tab").nth(index)
        tab.locator(f'[data-language-label="{adapter.english_code}"]').fill(english)
        tab.locator(f'[data-language-label="{adapter.lithuanian_code}"]').fill(lithuanian)
        choose_catalog_icon(page, tab, icon_id)


def save_settings(page: Page, adapter: StoreAdapter, directory) -> None:
    assert_cart_badge(page)
    fill_builtin_labels(page, adapter)
    add_contact_tab(page, adapter)
    configure_visuals(page)
    add_page_tab(page, adapter)
    add_hidden_tab(page, adapter)
    capture_screenshot(page, directory, "02-admin-settings-before-save.png")
    page.locator('button[form="form-appnuno"]').click()
    page.locator(".appnuno-alert-success").wait_for(timeout=20000)
    page.reload()
    saved = page.locator("#default-tabs .appnuno-tab").nth(2)
    saved.wait_for(state="attached")
    if saved.locator('[data-field="id"]').input_value() != "ci-added-tab":
        raise AssertionError("The newly added default tab ID did not persist after reloading the settings page.")
    if saved.locator(f'[data-language-label="{adapter.english_code}"]').input_value() != "CI Added Tab" or saved.locator(f'[data-language-label="{adapter.lithuanian_code}"]').input_value() != "CI pridėtas skirtukas":
        raise AssertionError("The newly added tab translations did not persist after reloading the settings page.")
    if saved.locator(f'[data-language-url="{adapter.lithuanian_code}"]').input_value() != "/lt/kontaktai":
        raise AssertionError("The newly added Lithuanian tab URL did not persist after reloading the settings page.")
    if saved.locator('[data-badge-source]').input_value() != "cart":
        raise AssertionError("The cart badge tab selection did not persist after reloading the settings page.")
    if page.locator("#default-tabs .appnuno-tab").nth(1).locator('[data-badge-source]').input_value() != "cart":
        raise AssertionError("The original cart badge selection was not preserved after saving another tab source.")
    page_tab = page.locator("#default-tabs .appnuno-tab").nth(3)
    if page_tab.locator(f'[data-language-label="{adapter.lithuanian_code}"]').input_value() != "Krepšelio skirtukas" or page_tab.locator(f'[data-language-url="{adapter.lithuanian_code}"]').input_value() != "/lt/krepselis":
        raise AssertionError("The cart page tab did not persist after reloading the settings page.")
    if page_tab.locator("[data-all-pages]").is_checked():
        raise AssertionError("The cart page tab did not persist its restricted-page setting.")
    selected = page_tab.locator("[data-pages]").evaluate("list => Array.from(list.querySelectorAll('[data-page-choice]:checked')).map(input => input.value)")
    if selected != ["common/home", "checkout/cart"]:
        raise AssertionError(f"The cart page selection did not persist after reloading the settings page: {selected}.")
    hidden = page.locator("#default-tabs .appnuno-tab").nth(4)
    if hidden.locator('[data-field="id"]').input_value() != "ci-hidden-tab" or hidden.locator("[data-tab-visibility]").input_value() != "0":
        raise AssertionError("The hidden tab did not remain saved with external visibility turned off.")
    capture_screenshot(page, directory, "03-admin-settings-after-save.png")


def expect_admin_language(page: Page, phrases: tuple[str, ...]) -> None:
    if "AppNuno" not in page.title():
        raise AssertionError(f"The module document title should contain AppNuno: {page.title()}.")
    first = page.locator("#default-tabs .appnuno-tab").first
    first.wait_for(state="attached")
    first_key = first.get_attribute("data-tab-key")
    page.locator(f'#default-tabs [data-tab-target="{first_key}"]').click()
    first.locator("[data-page-manage]").click()
    page.locator("#page-picker-dialog").wait_for(state="visible")
    labels = page.locator("[data-page-manage], [data-icon-picker]").evaluate_all(
        "elements => elements.flatMap(element => [element.textContent || '', element.getAttribute('aria-label') || '', element.getAttribute('title') || ''])"
    )
    content = (page.locator(".appnuno-settings").text_content() or "") + "\n" + (page.locator("#page-picker-dialog").text_content() or "") + "\n" + "\n".join(labels)
    page.locator('#page-picker-dialog [data-appnuno-dismiss="dialog"]').first.click()
    for href in ("#tab-glass", "#tab-colors"):
        page.locator(f'a[href="{href}"]').click()
        page.locator(href).wait_for(state="visible")
        content += "\n" + page.locator(href).inner_text()
    equivalents = {"Colors": ("Colors", "Colours")}
    missing = [phrase for phrase in phrases if not any(option in content for option in equivalents.get(phrase, (phrase,)))]
    if missing:
        raise AssertionError("The AppNuno extension did not show these translated names: " + ", ".join(missing) + ".")


def save_status(page: Page, enabled: bool) -> None:
    choose_switch(page, "input-status", "1" if enabled else "0")
    page.locator('button[form="form-appnuno"]').click()
    page.locator(".appnuno-alert-success").wait_for(timeout=20000)


def reject_invalid_configuration(page: Page, adapter: StoreAdapter) -> None:
    adapter.open_settings(page)
    original = page.locator("#default-tabs .appnuno-tab").first.locator('[data-field="id"]').input_value()
    page.locator('#default-tabs [data-tab-target="appnuno-tab-0"]').click()
    page.locator("#default-tabs .appnuno-tab").first.locator('[data-field="id"]').fill("")
    page.locator('button[form="form-appnuno"]').click()
    page.locator(".appnuno-alert-danger").first.wait_for(timeout=20000)
    adapter.open_settings(page)
    if page.locator("#default-tabs .appnuno-tab").first.locator('[data-field="id"]').input_value() != original:
        raise AssertionError("Invalid AppNuno settings changed the stored module configuration.")


def assert_saved_module(page: Page) -> None:
    if page.locator("#input-status").input_value() != "1":
        raise AssertionError("The module status did not remain enabled.")
    ids = page.locator("#default-tabs .appnuno-tab [data-field='id']").evaluate_all("fields => fields.map(field => field.value)")
    if "ci-added-tab" not in ids or "ci-hidden-tab" not in ids:
        raise AssertionError(f"The stored tabs changed: {ids}.")
