import tempfile
from pathlib import Path

from integration.admin import (
    add_store_language,
    admin_token,
    check_admin_module_languages,
    check_module_lifecycle_and_admin_validation,
    install_module,
    login_admin,
    refresh_modifications,
    save_module_settings,
    upload_extension,
    use_en_gb_language_code,
)
from integration.compatibility import (
    opencart4,
    patch_installer_directory_listing,
    patch_installer_storage_path,
    publish_admin_language_directories,
    repair_opencart402_admin_language,
    repair_opencart411_language_save,
    repair_opencart4_language_controller,
)
from integration.config import Runtime
from integration.database import configure_ftp
from integration.environment import (
    capture_screenshot,
    install_store,
    open_browser,
    open_php_server,
    prepare_artifact_dir,
    prepare_store,
    start_ftp_server,
)
from integration.storefront import check_cart_and_customer_state, check_storefront


def run(runtime: Runtime) -> None:
    if not runtime.package.is_file():
        raise FileNotFoundError("Build the OCMOD package before running the integration test: " + str(runtime.package))
    artifact_dir = prepare_artifact_dir(runtime)
    with tempfile.TemporaryDirectory(prefix="appnuno-opencart-") as temporary:
        web_root = prepare_store(runtime, Path(temporary))
        patch_installer_directory_listing(runtime, web_root)
        patch_installer_storage_path(runtime, web_root)
        ftp_server = None
        with open_php_server(web_root, artifact_dir / "php-server.log"):
            try:
                with open_browser(runtime, artifact_dir) as page:
                    install_store(runtime, page)
                    login_admin(runtime, page, web_root)
                    configure_ftp(runtime)
                    ftp_server = start_ftp_server(runtime, web_root)
                    upload_extension(runtime, page, admin_token(runtime, page))
                    if not opencart4(runtime):
                        refresh_modifications(runtime, page, admin_token(runtime, page))
                    publish_admin_language_directories(runtime, web_root)
                    repair_opencart4_language_controller(runtime, web_root)
                    repair_opencart402_admin_language(runtime, web_root)
                    repair_opencart411_language_save(runtime, web_root)
                    if runtime.line in ("2.0", "2.1"):
                        use_en_gb_language_code(runtime, page)
                    add_store_language(runtime, page, web_root, "Lithuanian", "lt-lt", "lt_LT.UTF-8,lt_LT,lt-lt,lithuanian")
                    install_module(runtime, page, admin_token(runtime, page))
                    capture_screenshot(page, artifact_dir, "01-admin-settings-initial.png")
                    save_module_settings(page, artifact_dir)
                    check_admin_module_languages(runtime, page)
                    token = admin_token(runtime, page)
                    check_storefront(runtime, page, artifact_dir)
                    check_cart_and_customer_state(runtime, page)
                    check_module_lifecycle_and_admin_validation(runtime, page, token)
            finally:
                if ftp_server is not None:
                    ftp_server.close_all()
    print(f"OpenCart {runtime.version} installation, OCMOD upload, module settings persistence and storefront bootstrap tests passed.")
    print(f"Screenshots, video, Playwright trace and PHP log saved to {artifact_dir}.")
