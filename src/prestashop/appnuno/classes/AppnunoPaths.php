<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

final class AppnunoPaths
{
    public static function moduleDirectory()
    {
        return dirname(__DIR__);
    }

    public static function adminDirectory()
    {
        $packaged = self::moduleDirectory() . '/assets';
        if (is_file($packaged . '/settings.html')) {
            return $packaged;
        }

        return dirname(dirname(dirname(__DIR__))) . '/shared/admin';
    }

    public static function adapterFile()
    {
        return self::moduleDirectory() . '/views/css/adapter.css';
    }

    public static function loadConfig()
    {
        if (class_exists('AppnunoConfig', false)) {
            return;
        }
        $packaged = self::moduleDirectory() . '/includes/appnuno-config.php';
        if (is_file($packaged)) {
            require_once $packaged;

            return;
        }
        require_once dirname(dirname(dirname(__DIR__))) . '/shared/php/appnuno-config.php';
    }

    public static function assetUrl($name)
    {
        $base = self::moduleBaseUri();
        if ($name === 'adapter.css') {
            return $base . 'views/css/adapter.css';
        }

        return $base . 'assets/' . ltrim($name, '/');
    }

    public static function version()
    {
        $path = self::moduleDirectory() . '/assets/version.txt';
        if (is_file($path)) {
            $version = trim((string) file_get_contents($path));
            if ($version !== '' && $version !== '__APPNUNO_VERSION__') {
                return $version;
            }
        }

        return '1.0.0';
    }

    public static function assetVersion()
    {
        $body = '';
        foreach (array(self::adminDirectory() . '/settings.css', self::adapterFile(), self::adminDirectory() . '/settings.js', self::adminDirectory() . '/settings.html') as $path) {
            $body .= is_file($path) ? hash_file('sha256', $path) : '';
        }

        return substr(hash('sha256', $body), 0, 12);
    }

    private static function moduleBaseUri()
    {
        $module = Module::getInstanceByName('appnuno');
        if ($module instanceof Module) {
            return $module->getPathUri();
        }
        $base = defined('__PS_BASE_URI__') ? (string) __PS_BASE_URI__ : '/';

        return rtrim($base, '/') . '/modules/appnuno/';
    }
}
