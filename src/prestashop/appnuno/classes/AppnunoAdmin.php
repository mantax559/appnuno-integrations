<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

final class AppnunoAdmin
{
    public static function persist(): array
    {
        $employee = Context::getContext()->employee;
        if (!$employee instanceof Employee || !(int) $employee->id) {
            return array(
                'warning' => self::text('error_permission'),
                'config' => '',
                'config_path' => '',
            );
        }
        $errors = array(
            'warning' => '',
            'config' => '',
            'config_path' => '',
        );
        $status = Tools::getValue('module_appnuno_status');
        if (!in_array($status, array('0', '1', 0, 1), true)) {
            $errors['warning'] = self::text('error_status');
        }
        $postedConfig = Tools::getValue('module_appnuno_config');
        if (!is_string($postedConfig) || trim($postedConfig) === '') {
            $errors['config'] = self::text('error_config_missing');
            $errors['config_path'] = 'module_appnuno_config';

            return $errors;
        }
        $normalized = AppnunoConfig::normalizeConfig($postedConfig);
        $configError = AppnunoConfig::validateConfig($normalized, AppnunoCatalog::languageCodes());
        if (is_string($configError) && strlen($configError)) {
            $errors['config'] = AppnunoConfig::readableConfigError(
                $configError,
                $normalized,
                static function ($key) {
                    return AppnunoAdmin::text((string) $key);
                },
                static function ($route) {
                    return AppnunoCatalog::routeLabel((string) $route);
                },
                static function ($code) {
                    return AppnunoCatalog::languageName((string) $code);
                },
                AppnunoCatalog::defaultLanguageCode()
            );
            $separator = strrpos($configError, ':');
            $errors['config_path'] = is_int($separator) ? substr($configError, 0, $separator) : '';
        }
        if ($errors['warning'] !== '' || $errors['config'] !== '') {
            return $errors;
        }
        Configuration::updateValue('module_appnuno_status', ($status === 1 || $status === '1') ? '1' : '0');
        Configuration::updateValue('module_appnuno_config', $normalized, true);
        $link = Context::getContext()->link->getAdminLink('AdminAppnuno');
        $separator = strpos($link, '?') === false ? '?' : '&';
        Tools::redirectAdmin($link . $separator . 'appnuno_saved=1');
        exit;
    }

    public static function render(array $errors): string
    {
        $posted = isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
        $saved = !$posted && (string) Tools::getValue('appnuno_saved') === '1';
        $success = $saved ? self::text('text_success') : '';

        return '<div class="appnuno-admin"><script>document.body.classList.add("appnuno-admin-screen");</script>' . self::settingsMarkup($posted, $success, $errors) . '</div>';
    }

    public static function text(string $key): string
    {
        return AppnunoCatalog::text($key);
    }

    private static function settingsMarkup(bool $posted, string $success, array $errors): string
    {
        $path = AppnunoPaths::adminDirectory() . '/settings.html';
        $html = is_file($path) ? file_get_contents($path) : '';
        $status = self::moduleStatus($posted);
        $enabled = $status === '1';
        list($configJson, $configuration) = self::configurationState($posted);
        $text = self::interfaceText();
        $languages = AppnunoCatalog::languages();
        $destinations = AppnunoCatalog::destinations();
        $routeOptions = AppnunoCatalog::routeOptions();
        $buttonCancel = self::text('button_cancel');
        $context = Context::getContext();
        $token = htmlspecialchars((string) Tools::getAdminTokenLite('AdminAppnuno'), ENT_QUOTES, 'UTF-8');
        $replacements = array(
            'appnuno_version' => htmlspecialchars(AppnunoPaths::version(), ENT_QUOTES, 'UTF-8'),
            'documentation_url' => 'https://appnuno.com/prestashop',
            'cancel' => htmlspecialchars($context->link->getAdminLink('AdminDashboard'), ENT_QUOTES, 'UTF-8'),
            'form_action' => htmlspecialchars($context->link->getAdminLink('AdminAppnuno'), ENT_QUOTES, 'UTF-8'),
            'form_nonce' => '<input type="hidden" name="token" value="' . $token . '" />',
            'status_enabled_pressed' => $enabled ? 'true' : 'false',
            'status_disabled_pressed' => $enabled ? 'false' : 'true',
            'status_value' => $enabled ? '1' : '0',
            'alert_success' => AppnunoConfig::alert('success', 'check', $success, $buttonCancel),
            'alert_warning' => AppnunoConfig::alert('danger', 'alert', isset($errors['warning']) ? $errors['warning'] : '', $buttonCancel),
            'alert_config' => AppnunoConfig::alert('danger', 'alert', isset($errors['config']) ? $errors['config'] : '', $buttonCancel),
            'configuration_json' => self::json($configuration),
            'text_json' => self::json($text),
            'languages_json' => self::json($languages),
            'destinations_json' => self::json($destinations),
            'route_options_json' => self::json($routeOptions),
            'error_config_path_json' => self::json(isset($errors['config_path']) ? $errors['config_path'] : ''),
            'default_language_json' => self::json(AppnunoCatalog::defaultLanguageCode()),
            'settings_script' => htmlspecialchars(AppnunoPaths::assetUrl('settings.js') . '?v=' . AppnunoPaths::assetVersion(), ENT_QUOTES, 'UTF-8'),
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

    private static function interfaceText(): array
    {
        $text = array();
        foreach (array('text_enabled', 'text_disabled', 'text_yes', 'text_no', 'text_visibility_on', 'text_icon_page_previous', 'text_icon_page_next', 'button_add_default_tab', 'button_add_first_tab', 'text_default_tabs_empty', 'text_default_tabs_empty_help', 'entry_cart_tab', 'help_cart_tab', 'help_status', 'text_back_button', 'entry_back_enabled', 'entry_back_placement', 'text_placement_separate', 'text_placement_bar', 'text_glass', 'text_glass_button', 'entry_glass_enabled', 'entry_glass_color_scheme', 'text_color_auto', 'text_color_light', 'text_color_dark', 'entry_glass_style', 'text_style_regular', 'text_style_clear', 'text_style_none', 'entry_glass_animate', 'entry_glass_duration', 'entry_glass_interactive', 'entry_glass_tint', 'entry_glass_opacity', 'text_navigation_colors', 'text_light_mode', 'text_dark_mode', 'entry_background', 'entry_content', 'text_language_labels', 'text_label', 'text_default_language', 'text_destination', 'text_choose_destination', 'text_custom_url', 'text_url_placeholder', 'text_tab_id', 'text_tab_id_placeholder', 'text_icon', 'text_choose_icon', 'text_icon_catalog_search', 'text_icon_catalog_loading', 'text_icon_catalog_empty', 'text_icon_catalog_error', 'text_icon_catalog_clear', 'text_icon_catalog_total', 'button_remove_tab', 'text_shown_on_pages', 'help_shown_on_pages', 'text_all_pages', 'button_manage_pages', 'text_show_on_all_pages', 'text_show_on_selected_pages', 'text_show_on_no_pages', 'button_apply_pages', 'button_cancel_pages', 'button_select_all_pages', 'button_clear_pages', 'text_tab_missing', 'text_tab_visible', 'text_tab_visibility_toggle', 'text_visibility_indicator', 'text_tab_list_empty', 'help_default_tabs', 'help_back_enabled', 'help_back_placement', 'help_glass_enabled', 'help_glass_color_scheme', 'help_glass_style', 'help_glass_animate', 'help_glass_duration', 'help_glass_interactive', 'help_glass_tint', 'help_glass_opacity', 'help_background', 'help_content', 'help_tab_id', 'help_tab_labels', 'help_tab_destination', 'help_icon_catalog', 'help_tab_visibility') as $key) {
            $text[$key] = self::text($key);
        }

        return $text;
    }

    private static function moduleStatus(bool $posted): string
    {
        if ($posted) {
            $status = Tools::getValue('module_appnuno_status');

            return in_array($status, array('1', 1), true) ? '1' : '0';
        }
        $stored = Configuration::get('module_appnuno_status');

        return in_array($stored, array('1', 1, '1'), true) ? '1' : '0';
    }

    private static function configurationState(bool $posted): array
    {
        if ($posted) {
            $json = Tools::getValue('module_appnuno_config');
        } else {
            $json = Configuration::get('module_appnuno_config');
        }
        if (!is_string($json) || trim($json) === '') {
            $json = AppnunoConfig::defaultConfig();
        }
        $configuration = json_decode($json, true);
        if (!$posted && (!is_array($configuration) || strlen((string) AppnunoConfig::validateConfig($json)))) {
            $json = AppnunoConfig::defaultConfig();
            $configuration = json_decode($json, true);
        }
        if (!is_array($configuration)) {
            $json = AppnunoConfig::defaultConfig();
            $configuration = json_decode($json, true);
        }

        return array($json, is_array($configuration) ? $configuration : array());
    }

    private static function json($value): string
    {
        $json = json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return is_string($json) ? $json : 'null';
    }
}
