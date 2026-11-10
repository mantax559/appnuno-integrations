<?php
if (!defined('ABSPATH')) {
    exit;
}

final class Appnuno_Paths {
    public static function pluginDirectory(): string {
        return dirname(APPNUNO_FILE);
    }

    public static function adminDirectory(): string {
        $packaged = self::pluginDirectory() . '/assets';
        if (is_file($packaged . '/settings.html')) {
            return $packaged;
        }

        return dirname(APPNUNO_FILE) . '/../../shared/admin';
    }

    public static function adapterFile(): string {
        return self::pluginDirectory() . '/assets/adapter.css';
    }

    public static function loadConfig(): void {
        if (class_exists('AppnunoConfig', false)) {
            return;
        }
        $packaged = self::pluginDirectory() . '/includes/appnuno-config.php';
        if (is_file($packaged)) {
            require_once $packaged;
            return;
        }
        require_once dirname(APPNUNO_FILE) . '/../../shared/php/appnuno-config.php';
    }

    public static function assetUrl(string $name): string {
        return plugins_url('assets/' . $name, APPNUNO_FILE);
    }

    public static function version(): string {
        $path = self::adminDirectory() . '/version.txt';
        if (!is_file($path)) {
            $path = self::pluginDirectory() . '/assets/version.txt';
        }
        if (is_file($path)) {
            $version = trim((string) file_get_contents($path));
            if ($version !== '' && $version !== '__APPNUNO_VERSION__') {
                return $version;
            }
        }

        return APPNUNO_VERSION === '__APPNUNO_VERSION__' ? '1.0.0' : APPNUNO_VERSION;
    }

    public static function assetVersion(): string {
        $body = '';
        foreach (array(self::adminDirectory() . '/settings.css', self::adapterFile(), self::adminDirectory() . '/settings.js', self::adminDirectory() . '/settings.html') as $path) {
            $body .= is_file($path) ? hash_file('sha256', $path) : '';
        }

        return substr(hash('sha256', $body), 0, 12);
    }
}
