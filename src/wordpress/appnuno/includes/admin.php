<?php
if (!defined('ABSPATH')) {
    exit;
}

final class Appnuno_Admin {
    public static function register(): void {
        add_action('admin_menu', array(self::class, 'menu'));
        add_action('admin_enqueue_scripts', array(self::class, 'assets'));
        add_filter('admin_body_class', array(self::class, 'bodyClass'));
    }

    public static function menu(): void {
        add_menu_page(
            'AppNuno',
            'AppNuno',
            'manage_woocommerce',
            'appnuno',
            array(self::class, 'render'),
            'dashicons-admin-site-alt3',
            56
        );
    }

    public static function bodyClass(string $classes): string {
        $page = isset($_GET['page']) ? wp_unslash($_GET['page']) : '';
        if ($page === 'appnuno') {
            return $classes . ' appnuno-admin-screen';
        }

        return $classes;
    }

    public static function assets(string $hook): void {
        if ($hook !== 'toplevel_page_appnuno') {
            return;
        }
        $version = Appnuno_Paths::assetVersion();
        wp_enqueue_style('appnuno-settings', Appnuno_Paths::assetUrl('settings.css'), array('common', 'forms'), $version);
        wp_enqueue_style('appnuno-adapter', Appnuno_Paths::assetUrl('adapter.css'), array('appnuno-settings'), $version);
    }

    public static function render(): void {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html(self::text('error_permission')));
        }
        $posted = isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
        $errors = array(
            'warning' => '',
            'config' => '',
            'config_path' => ''
        );
        if ($posted) {
            $result = self::persist();
            if (is_array($result)) {
                $errors = $result;
            }
        }
        $success = !$posted && isset($_GET['appnuno_saved']) && (string) wp_unslash($_GET['appnuno_saved']) === '1' ? self::text('text_success') : '';
        echo '<div class="wrap appnuno-admin">';
        echo self::settingsMarkup($posted, $success, $errors);
        echo '</div>';
    }

    public static function text(string $key): string {
        return Appnuno_Catalog::text($key);
    }

    private static function persist(): array {
        if (!current_user_can('manage_woocommerce')) {
            return array(
                'warning' => self::text('error_permission'),
                'config' => '',
                'config_path' => ''
            );
        }
        check_admin_referer('appnuno_settings', 'appnuno_settings_nonce');
        $errors = array(
            'warning' => '',
            'config' => '',
            'config_path' => ''
        );
        $status = isset($_POST['module_appnuno_status']) ? wp_unslash($_POST['module_appnuno_status']) : null;
        if (!in_array($status, array('0', '1', 0, 1), true)) {
            $errors['warning'] = self::text('error_status');
        }
        $posted_config = isset($_POST['module_appnuno_config']) && is_string($_POST['module_appnuno_config']) ? wp_unslash($_POST['module_appnuno_config']) : '';
        if (trim($posted_config) === '') {
            $errors['config'] = self::text('error_config_missing');
            $errors['config_path'] = 'module_appnuno_config';

            return $errors;
        }
        $normalized = AppnunoConfig::normalizeConfig($posted_config);
        $config_error = AppnunoConfig::validateConfig($normalized, Appnuno_Catalog::languageCodes());
        if (strlen($config_error)) {
            $errors['config'] = AppnunoConfig::readableConfigError(
                $config_error,
                $normalized,
                static function ($key) {
                    return Appnuno_Admin::text((string) $key);
                },
                static function ($route) {
                    return Appnuno_Catalog::routeLabel((string) $route);
                },
                static function ($code) {
                    return Appnuno_Catalog::languageName((string) $code);
                },
                Appnuno_Catalog::defaultLanguageCode()
            );
            $separator = strrpos($config_error, ':');
            $errors['config_path'] = is_int($separator) ? substr($config_error, 0, $separator) : '';
        }
        if ($errors['warning'] !== '' || $errors['config'] !== '') {
            return $errors;
        }
        update_option('module_appnuno_status', ($status === 1 || $status === '1') ? '1' : '0', false);
        update_option('module_appnuno_config', $normalized, false);
        wp_safe_redirect(add_query_arg(array(
            'page' => 'appnuno',
            'appnuno_saved' => '1'
        ), admin_url('admin.php')));
        exit;
    }

    private static function settingsMarkup(bool $posted, string $success, array $errors): string {
        $path = Appnuno_Paths::adminDirectory() . '/settings.html';
        $html = is_file($path) ? file_get_contents($path) : '';
        $status = self::moduleStatus($posted);
        $enabled = $status === '1';
        list($config_json, $configuration) = self::configurationState($posted);
        $text = self::interfaceText();
        $languages = Appnuno_Catalog::languages();
        $destinations = Appnuno_Catalog::destinations();
        $route_options = Appnuno_Catalog::routeOptions();
        $button_cancel = self::text('button_cancel');
        $replacements = array(
            'appnuno_version' => htmlspecialchars(Appnuno_Paths::version(), ENT_QUOTES, 'UTF-8'),
            'documentation_url' => 'https://appnuno.com/wordpress',
            'cancel' => htmlspecialchars(admin_url('index.php'), ENT_QUOTES, 'UTF-8'),
            'form_action' => htmlspecialchars(admin_url('admin.php?page=appnuno'), ENT_QUOTES, 'UTF-8'),
            'form_nonce' => wp_nonce_field('appnuno_settings', 'appnuno_settings_nonce', true, false),
            'status_enabled_pressed' => $enabled ? 'true' : 'false',
            'status_disabled_pressed' => $enabled ? 'false' : 'true',
            'status_value' => $enabled ? '1' : '0',
            'alert_success' => AppnunoConfig::alert('success', 'check', $success, $button_cancel),
            'alert_warning' => AppnunoConfig::alert('danger', 'alert', isset($errors['warning']) ? $errors['warning'] : '', $button_cancel),
            'alert_config' => AppnunoConfig::alert('danger', 'alert', isset($errors['config']) ? $errors['config'] : '', $button_cancel),
            'configuration_json' => self::json($configuration),
            'text_json' => self::json($text),
            'languages_json' => self::json($languages),
            'destinations_json' => self::json($destinations),
            'route_options_json' => self::json($route_options),
            'error_config_path_json' => self::json(isset($errors['config_path']) ? $errors['config_path'] : ''),
            'default_language_json' => self::json(Appnuno_Catalog::defaultLanguageCode()),
            'settings_script' => esc_url(Appnuno_Paths::assetUrl('settings.js') . '?v=' . Appnuno_Paths::assetVersion())
        );
        foreach (array('text_documentation', 'button_cancel', 'button_save', 'text_default_tabs', 'entry_status', 'text_enabled', 'text_disabled') as $key) {
            $replacements[$key] = htmlspecialchars(self::text($key), ENT_QUOTES, 'UTF-8');
        }
        $replacements['text_icon_catalog_search'] = htmlspecialchars(self::text('text_icon_catalog_search'), ENT_QUOTES, 'UTF-8');
        foreach ($text as $key => $value) {
            $replacements[$key] = htmlspecialchars(is_string($value) ? $value : '', ENT_QUOTES, 'UTF-8');
        }

        return AppnunoConfig::renderSettings(is_string($html) ? $html : '', $replacements);
    }

    private static function interfaceText(): array {
        $text = array();
        foreach (array('text_enabled', 'text_disabled', 'text_yes', 'text_no', 'text_visibility_on', 'text_icon_page_previous', 'text_icon_page_next', 'button_add_default_tab', 'button_add_first_tab', 'text_default_tabs_empty', 'text_default_tabs_empty_help', 'entry_cart_tab', 'help_cart_tab', 'help_status', 'text_back_button', 'entry_back_enabled', 'entry_back_placement', 'text_placement_separate', 'text_placement_bar', 'text_glass', 'text_glass_button', 'entry_glass_enabled', 'entry_glass_color_scheme', 'text_color_auto', 'text_color_light', 'text_color_dark', 'entry_glass_style', 'text_style_regular', 'text_style_clear', 'text_style_none', 'entry_glass_animate', 'entry_glass_duration', 'entry_glass_interactive', 'entry_glass_tint', 'entry_glass_opacity', 'text_navigation_colors', 'text_light_mode', 'text_dark_mode', 'entry_background', 'entry_content', 'text_language_labels', 'text_label', 'text_default_language', 'text_destination', 'text_choose_destination', 'text_custom_url', 'text_url_placeholder', 'text_tab_id', 'text_tab_id_placeholder', 'text_icon', 'text_choose_icon', 'text_icon_catalog_search', 'text_icon_catalog_loading', 'text_icon_catalog_empty', 'text_icon_catalog_error', 'text_icon_catalog_clear', 'text_icon_catalog_total', 'button_remove_tab', 'text_shown_on_pages', 'help_shown_on_pages', 'text_all_pages', 'button_manage_pages', 'text_show_on_all_pages', 'text_show_on_selected_pages', 'text_show_on_no_pages', 'button_apply_pages', 'button_cancel_pages', 'button_select_all_pages', 'button_clear_pages', 'text_tab_missing', 'text_tab_visible', 'text_tab_visibility_toggle', 'text_visibility_indicator', 'text_tab_list_empty', 'help_default_tabs', 'help_back_enabled', 'help_back_placement', 'help_glass_enabled', 'help_glass_color_scheme', 'help_glass_style', 'help_glass_animate', 'help_glass_duration', 'help_glass_interactive', 'help_glass_tint', 'help_glass_opacity', 'help_background', 'help_content', 'help_tab_id', 'help_tab_labels', 'help_tab_destination', 'help_icon_catalog', 'help_tab_visibility') as $key) {
            $text[$key] = self::text($key);
        }

        return $text;
    }

    private static function moduleStatus(bool $posted): string {
        if ($posted && isset($_POST['module_appnuno_status'])) {
            $status = wp_unslash($_POST['module_appnuno_status']);

            return in_array($status, array('1', 1), true) ? '1' : '0';
        }
        $stored = get_option('module_appnuno_status', '0');

        return in_array($stored, array('1', 1, '1'), true) ? '1' : '0';
    }

    private static function configurationState(bool $posted): array {
        if ($posted && isset($_POST['module_appnuno_config']) && is_string($_POST['module_appnuno_config'])) {
            $json = wp_unslash($_POST['module_appnuno_config']);
        } else {
            $stored = get_option('module_appnuno_config', '');
            $json = is_string($stored) ? $stored : '';
        }
        if (!is_string($json) || trim($json) === '') {
            $json = AppnunoConfig::defaultConfig();
        }
        $configuration = json_decode($json, true);
        if (!$posted && (!is_array($configuration) || strlen(AppnunoConfig::validateConfig($json)))) {
            $json = AppnunoConfig::defaultConfig();
            $configuration = json_decode($json, true);
        }
        if (!is_array($configuration)) {
            $json = AppnunoConfig::defaultConfig();
            $configuration = json_decode($json, true);
        }

        return array($json, is_array($configuration) ? $configuration : array());
    }

    private static function json($value): string {
        $json = json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return is_string($json) ? $json : 'null';
    }
}
