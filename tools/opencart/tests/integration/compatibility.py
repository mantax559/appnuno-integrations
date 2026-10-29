import re
import shutil
from pathlib import Path

from integration.config import Runtime


def admin_method_route(runtime: Runtime, route: str, method: str) -> str:
    # OpenCart 4.0.0.0 and 4.0.1.x use a pipe before the method; 4.0.2.0+ uses a dot.
    if opencart4(runtime):
        separator = "|" if opencart_version_parts(runtime) < (4, 0, 2, 0) else "."
        return route + separator + method
    return route + "/" + method


def admin_module_route(runtime: Runtime) -> str:
    if opencart4(runtime):
        return "extension/appnuno/module/appnuno"
    if legacy_module_layout(runtime):
        return "module/appnuno"
    return "extension/module/appnuno"


def admin_page(runtime: Runtime, route: str, token: str) -> str:
    return runtime.base_url + "/admin/index.php?route=" + route + "&" + admin_token_name(runtime) + "=" + token


def admin_token_name(runtime: Runtime) -> str:
    # OpenCart 3 renamed the admin session query key; OpenCart 4 retains user_token.
    return "user_token" if runtime.line == "3.0" or opencart4(runtime) else "token"


def catalog_method_route(runtime: Runtime, method: str) -> str:
    return admin_method_route(runtime, admin_module_route(runtime), method)


def catalog_method_url(runtime: Runtime, method: str) -> str:
    return runtime.base_url + "/index.php?route=" + catalog_method_route(runtime, method)


def clear_opencart4_language_cache(runtime: Runtime, web_root: Path) -> None:
    # OpenCart 4.0.2 caches languages under a versioned filename while addLanguage deletes admin.language.
    if not opencart4(runtime):
        return
    cache = web_root / "system" / "storage" / "cache"
    if not cache.is_dir():
        return
    for item in cache.iterdir():
        if item.name == "index.html":
            continue
        if item.is_dir():
            shutil.rmtree(item)
        else:
            item.unlink()


def header_event_injects_script(runtime: Runtime) -> bool:
    # OpenCart 2.2 and 2.3 register catalog/view/common/header/before, then startup strips the catalog/ prefix and the view event runs.
    # OpenCart 2.0 removes that registration, 2.1 leaves the view triggers commented out, and OpenCart 3 stores the trigger without catalog/, so startup strips it to a trigger that never fires.
    return runtime.line in ("2.2", "2.3")


def install_route(runtime: Runtime, route: str) -> str:
    if runtime.line in ("2.0", "2.1"):
        return "**route=" + route + "**"
    return "**route=install/" + route + "**"


def legacy_module_layout(runtime: Runtime) -> bool:
    # OpenCart 2.0–2.2 list modules directly; later supported lines use the extensions marketplace.
    return runtime.line not in ("2.3", "3.0", "4.0", "4.1")


def modification_refresh_keeps_nested_files(runtime: Runtime) -> bool:
    # OpenCart 2.0.0.0 refresh deletes only files in the modification root. The generated catalog/controller/common/header.php stays.
    return runtime.version == "2.0.0.0"


def opencart4(runtime: Runtime) -> bool:
    return runtime.version.startswith("4.")


def opencart_version_parts(runtime: Runtime) -> tuple[int, ...]:
    return tuple(int(part) for part in runtime.version.split("."))


def patch_installer_directory_listing(runtime: Runtime, web_root: Path) -> None:
    if runtime.version not in ("2.0.0.0", "2.0.1.0"):
        return
    path = web_root / "admin" / "controller" / "extension" / "installer.php"
    content = path.read_text(encoding="utf-8")
    needle = "\t\t\t\t\t\t\t\tif (!in_array($destination, $list)) {\n"
    replacement = (
        "\t\t\t\t\t\t\t\t$list_data = array();\n"
        "\n"
        "\t\t\t\t\t\t\t\tif (is_array($list)) {\n"
        "\t\t\t\t\t\t\t\t\tforeach ($list as $entry) {\n"
        "\t\t\t\t\t\t\t\t\t\t$list_data[] = basename($entry);\n"
        "\t\t\t\t\t\t\t\t\t}\n"
        "\t\t\t\t\t\t\t\t}\n"
        "\n"
        "\t\t\t\t\t\t\t\tif (!in_array(basename($destination), $list_data)) {\n"
    )
    if content.count(needle) != 1:
        raise AssertionError("OpenCart installer directory listing could not be patched.")
    path.write_text(content.replace(needle, replacement, 1), encoding="utf-8")


def patch_installer_storage_path(runtime: Runtime, web_root: Path) -> None:
    # OpenCart 3.0.0.2 has no release archive, and its installer writes a malformed DIR_STORAGE definition.
    if runtime.version != "3.0.0.2":
        return
    path = web_root / "install" / "controller" / "install" / "step_3.php"
    content = path.read_text(encoding="utf-8")
    broken = (
        r"define(\'DIR_STORAGE\', \' . DIR_SYSTEM . \'storage/\');",
        r"define(\'DIR_STORAGE\', \'DIR_SYSTEM . \'storage/\');",
    )
    fixed = r"define(\'DIR_STORAGE\', DIR_SYSTEM . \'storage/\');"
    for needle in broken:
        if content.count(needle) != 1:
            raise AssertionError("OpenCart 3.0.0.2 storage path could not be patched.")
        content = content.replace(needle, fixed, 1)
    path.write_text(content, encoding="utf-8")


def publish_admin_language_directories(runtime: Runtime, web_root: Path) -> None:
    # OpenCart 4 only lists language codes that already have admin/language directories.
    if not opencart4(runtime):
        return
    source = web_root / "admin" / "language" / "en-gb"
    destination = web_root / "admin" / "language" / "lt-lt"
    if not destination.exists():
        shutil.copytree(source, destination)


def repair_opencart402_admin_language(runtime: Runtime, web_root: Path) -> None:
    # These OpenCart 4.0.2 releases reload extension language files with the storefront language, which may differ from admin.
    if not opencart4(runtime) or not ((4, 0, 2, 0) <= opencart_version_parts(runtime) < (4, 0, 2, 3)):
        return
    path = web_root / "admin" / "controller" / "startup" / "language.php"
    text = path.read_text(encoding="utf-8").replace("\r\n", "\n")
    text, cookie_count = re.subn(
        r"if \(isset\(\$this->request->cookie\['language'\]\)\) \{\n\s+\$code = \(string\)\$this->request->cookie\['language'\];\n\s+\} else \{\n\s+\$code = \$this->config->get\('[^']+'\);\n\s+\}",
        "$code = $this->config->get('config_language_admin');",
        text,
        count=1,
    )
    text, after_count = re.subn(
        r"public function after\(&\$route, &\$prefix, &\$code, &\$output\): void \{.*?\n\t\}",
        "public function after(&$route, &$prefix, &$code, &$output): void {\n\t\t$code = $this->config->get('config_language_admin');\n\t\t$this->language->load($route, $prefix, $code);\n\t}",
        text,
        count=1,
        flags=re.S,
    )
    # These releases skip startup/language.after, leaving the language service initialized to en-gb.
    replacement = "$code = $this->config->get('config_language_admin');\n\t\t$language = new \\Opencart\\System\\Library\\Language((string)$code);\n\t\t$language->addPath(DIR_LANGUAGE);\n\t\t$this->registry->set('language', $language);"

    def replace_language_code(match: re.Match[str]) -> str:
        return replacement

    text, object_count = re.subn(
        r"\$code = \$this->config->get\('config_language_admin'\);",
        replace_language_code,
        text,
        count=1,
    )
    if cookie_count != 1 or after_count != 1 or object_count != 1:
        raise AssertionError("OpenCart 4.0.2 admin language startup was not patched.")
    path.write_text(text, encoding="utf-8")


def repair_opencart411_language_save(runtime: Runtime, web_root: Path) -> None:
    # OpenCart 4.1.0.1–4.1.0.2 dereferences the language record before checking whether it exists.
    if opencart_version_parts(runtime) not in ((4, 1, 0, 1), (4, 1, 0, 2)):
        return
    path = web_root / "admin" / "controller" / "localisation" / "language.php"
    text = path.read_text(encoding="utf-8")
    old = "if ($language_info && !$post_info['language_id'] || ($language_info['language_id'] != $post_info['language_id'])) {"
    new = "if ($language_info && (!$post_info['language_id'] || $language_info['language_id'] != $post_info['language_id'])) {"
    if old not in text:
        raise AssertionError("OpenCart 4.1 language save was not patched.")
    path.write_text(text.replace(old, new, 1), encoding="utf-8")


def repair_opencart4_language_controller(runtime: Runtime, web_root: Path) -> None:
    # OpenCart 4.0 invokes the language model in save() before loading it.
    if not opencart4(runtime):
        return
    path = web_root / "admin" / "controller" / "localisation" / "language.php"
    text = path.read_text(encoding="utf-8")
    start = text.find("function save")
    if start < 0:
        return
    tail = text[start:]
    needle = "$this->model_localisation_language->getLanguageByCode"
    use_at = tail.find(needle)
    load_at = tail.find("$this->load->model('localisation/language')")
    if use_at < 0 or (load_at >= 0 and load_at < use_at):
        return
    tail = tail.replace(needle, "$this->load->model('localisation/language');\n\t\t" + needle, 1)
    path.write_text(text[:start] + tail, encoding="utf-8")


def setting_scope_column(runtime: Runtime) -> str:
    if runtime.version == "2.0.0.0":
        return "`group`"
    return "`code`"
