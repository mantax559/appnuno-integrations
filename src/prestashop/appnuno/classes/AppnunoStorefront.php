<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

final class AppnunoStorefront
{
    public static function rememberCustomer(): void
    {
        $customer = Context::getContext()->customer;
        if (!$customer instanceof Customer || !$customer->isLogged()) {
            return;
        }
        $identifier = (string) (int) $customer->id;
        if ($identifier === '0') {
            return;
        }
        $existing = isset($_COOKIE['appnuno_customer_id']) ? (string) $_COOKIE['appnuno_customer_id'] : '';
        if ($existing === $identifier || headers_sent()) {
            return;
        }
        $path = defined('__PS_BASE_URI__') && is_string(__PS_BASE_URI__) && __PS_BASE_URI__ !== '' ? __PS_BASE_URI__ : '/';
        setcookie('appnuno_customer_id', $identifier, array(
            'expires' => time() + (30 * 86400),
            'path' => $path,
            'secure' => (bool) Configuration::get('PS_SSL_ENABLED'),
            'httponly' => true,
            'samesite' => 'Lax',
        ));
        $_COOKIE['appnuno_customer_id'] = $identifier;
    }

    public static function headerScript(): string
    {
        if (!self::enabled()) {
            return '';
        }
        $params = array(
            'page_route' => AppnunoCatalog::currentRoute(),
            'page' => self::requestPath(),
        );
        foreach ($_GET as $key => $value) {
            if (!is_string($key) || !is_string($value) || in_array($key, array('page_route', 'page', 'fc', 'module', 'controller'), true)) {
                continue;
            }
            $params[$key] = $value;
        }
        $url = Context::getContext()->link->getModuleLink('appnuno', 'bootstrap', $params);

        return '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></script>';
    }

    public static function bootstrap(): void
    {
        if (!self::enabled()) {
            self::headers('application/javascript; charset=utf-8', 404);
            echo '';

            return;
        }
        $integration = self::integration();
        self::headers('application/javascript; charset=utf-8', 200);
        if ($integration === '') {
            echo '';

            return;
        }
        echo 'window.AppNuno=window.AppNuno||{};window.AppNunoIntegration=' . $integration . ';window.AppNuno.navigation=window.AppNunoIntegration.navigation;if(window.AppNunoIntegration.state.identity){window.AppNuno.identity=window.AppNunoIntegration.state.identity;window.AppNuno.identities=[window.AppNunoIntegration.state.identity];}(function(){var s=document.createElement("script");s.src="https://appnuno.com/sdk/v1/appnuno.js";s.async=true;(document.head||document.documentElement).appendChild(s);}());';
    }

    public static function state(): void
    {
        if (!self::enabled()) {
            self::headers('application/json; charset=utf-8', 404);
            echo '{}';

            return;
        }
        self::headers('application/json; charset=utf-8', 200);
        echo self::json(self::statePayload());
    }

    private static function integration(): string
    {
        $stored = Configuration::get('module_appnuno_config');
        if (!is_string($stored) || strlen($stored) > 65535) {
            return '';
        }
        $config = json_decode($stored, true);
        if (!is_array($config) || json_last_error() || !isset($config['defaultTabs']) || !is_array($config['defaultTabs']) || !isset($config['routes']) || !is_array($config['routes'])) {
            return '';
        }
        $page = isset($_GET['page']) && is_string($_GET['page']) ? $_GET['page'] : '';
        $route = isset($_GET['page_route']) && is_string($_GET['page_route']) ? $_GET['page_route'] : 'common/home';
        $query = array();
        foreach ($_GET as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $query[$key] = $value;
            }
        }
        $integration = AppnunoConfig::integration($config, $route, self::statePayload(), AppnunoCatalog::currentLanguageCode(), $page, $query);
        if (!is_array($integration)) {
            return '';
        }
        $integration['stateUrl'] = Context::getContext()->link->getModuleLink('appnuno', 'state');

        return self::json($integration);
    }

    private static function statePayload(): array
    {
        return array(
            'cartCount' => self::cartCount(),
            'identity' => self::identity(),
        );
    }

    private static function cartCount(): int
    {
        $cart = Context::getContext()->cart;
        if (!$cart instanceof Cart) {
            return 0;
        }

        return (int) $cart->nbProducts();
    }

    private static function identity()
    {
        $customer = Context::getContext()->customer;
        if ($customer instanceof Customer && $customer->isLogged()) {
            $identifier = (string) (int) $customer->id;
            if ($identifier !== '0') {
                return array(
                    'identifier' => $identifier,
                    'guard' => 'customer',
                    'is_logged_in' => true,
                );
            }
        }
        if (!isset($_COOKIE['appnuno_customer_id'])) {
            return null;
        }
        $identifier = preg_replace('/[^0-9]/', '', (string) $_COOKIE['appnuno_customer_id']);
        if (!is_string($identifier) || $identifier === '') {
            return null;
        }

        return array(
            'identifier' => $identifier,
            'guard' => 'customer',
            'is_logged_in' => false,
        );
    }

    private static function enabled(): bool
    {
        $status = Configuration::get('module_appnuno_status');

        return $status === '1' || $status === 1;
    }

    private static function requestPath(): string
    {
        $uri = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';

        return AppnunoCatalog::relativeUrl($uri);
    }

    private static function headers(string $contentType, int $status): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: ' . $contentType);
        header('Cache-Control: no-store, private, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
    }

    private static function json($value): string
    {
        $json = json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return is_string($json) ? $json : 'null';
    }
}
