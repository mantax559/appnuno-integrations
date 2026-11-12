<?php
if (!defined('ABSPATH')) {
    exit;
}

final class Appnuno_Storefront {
    public static function register(): void {
        add_action('init', array(self::class, 'rememberCustomer'), 1);
        add_action('pre_get_posts', array(self::class, 'limitSaleProducts'));
        add_action('wp_enqueue_scripts', array(self::class, 'enqueue'));
        add_action('template_redirect', array(self::class, 'respond'), 0);
        add_filter('script_loader_src', array(self::class, 'stripScriptVersion'), 100, 2);
    }

    public static function rememberCustomer(): void {
        if (!is_user_logged_in()) {
            return;
        }
        $identifier = (string) get_current_user_id();
        if ($identifier === '0') {
            return;
        }
        $existing = isset($_COOKIE['appnuno_customer_id']) ? (string) wp_unslash($_COOKIE['appnuno_customer_id']) : '';
        if ($existing === $identifier) {
            return;
        }
        $path = defined('COOKIEPATH') && is_string(COOKIEPATH) && COOKIEPATH !== '' ? COOKIEPATH : '/';
        $domain = defined('COOKIE_DOMAIN') && is_string(COOKIE_DOMAIN) ? COOKIE_DOMAIN : '';
        $expires = time() + (30 * DAY_IN_SECONDS);
        $secure = is_ssl();
        if (PHP_VERSION_ID >= 70300) {
            setcookie('appnuno_customer_id', $identifier, array(
                'expires' => $expires,
                'path' => $path,
                'domain' => $domain,
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ));
        } else {
            setcookie('appnuno_customer_id', $identifier, $expires, $path, $domain, $secure, true);
        }
        $_COOKIE['appnuno_customer_id'] = $identifier;
    }

    public static function limitSaleProducts(WP_Query $query): void {
        if (is_admin() || !$query->is_main_query() || !function_exists('is_shop') || !is_shop() || !Appnuno_Catalog::onSaleRequested()) {
            return;
        }
        $ids = function_exists('wc_get_product_ids_on_sale') ? wc_get_product_ids_on_sale() : array();
        $safe = array();
        if (is_array($ids)) {
            foreach ($ids as $id) {
                $safe[] = (int) $id;
            }
        }
        if (!count($safe)) {
            $safe = array(0);
        }
        $query->set('post__in', $safe);
    }

    public static function enqueue(): void {
        if (!self::enabled()) {
            return;
        }
        $args = array(
            'appnuno' => 'bootstrap',
            'page_route' => Appnuno_Catalog::currentRoute(),
            'page' => self::requestUri()
        );
        foreach ($_GET as $key => $value) {
            if (!is_string($key) || !is_string($value) || in_array($key, array('appnuno', 'page_route', 'page'), true)) {
                continue;
            }
            $args[$key] = wp_unslash($value);
        }
        wp_enqueue_script('appnuno-bootstrap', add_query_arg($args, home_url('/')), array(), false, false);
    }

    public static function stripScriptVersion($src, $handle) {
        if ($handle !== 'appnuno-bootstrap' || !is_string($src)) {
            return $src;
        }

        return remove_query_arg('ver', $src);
    }

    public static function respond(): void {
        if (!isset($_GET['appnuno'])) {
            return;
        }
        $action = wp_unslash($_GET['appnuno']);
        if ($action === 'bootstrap') {
            self::bootstrap();
            exit;
        }
        if ($action === 'state') {
            self::state();
            exit;
        }
    }

    private static function bootstrap(): void {
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

    private static function state(): void {
        if (!self::enabled()) {
            self::headers('application/json; charset=utf-8', 404);
            echo '{}';
            return;
        }
        self::headers('application/json; charset=utf-8', 200);
        echo self::json(self::statePayload());
    }

    private static function integration(): string {
        $stored = get_option('module_appnuno_config', '');
        if (!is_string($stored) || strlen($stored) > 65535) {
            return '';
        }
        $config = json_decode($stored, true);
        if (!is_array($config) || json_last_error() || !isset($config['defaultTabs']) || !is_array($config['defaultTabs']) || !isset($config['routes']) || !is_array($config['routes'])) {
            return '';
        }
        $page = isset($_GET['page']) && is_string($_GET['page']) ? wp_unslash($_GET['page']) : '';
        $route = isset($_GET['page_route']) && is_string($_GET['page_route']) ? wp_unslash($_GET['page_route']) : 'common/home';
        $query = array();
        foreach ($_GET as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $query[$key] = wp_unslash($value);
            }
        }
        $integration = AppnunoConfig::integration($config, $route, self::statePayload(), Appnuno_Catalog::currentLanguageCode(), $page, $query);
        if (!is_array($integration)) {
            return '';
        }
        $integration['stateUrl'] = add_query_arg('appnuno', 'state', home_url('/'));

        return self::json($integration);
    }

    private static function statePayload(): array {
        return array(
            'cartCount' => self::cartCount(),
            'identity' => self::identity()
        );
    }

    private static function cartCount(): int {
        if (!function_exists('WC')) {
            return 0;
        }
        $woocommerce = WC();
        if (function_exists('wc_load_cart') && (!$woocommerce || !$woocommerce->cart instanceof WC_Cart)) {
            wc_load_cart();
            $woocommerce = WC();
        }
        if (!$woocommerce || !$woocommerce->cart instanceof WC_Cart) {
            return 0;
        }

        return (int) $woocommerce->cart->get_cart_contents_count();
    }

    private static function identity() {
        $user_id = get_current_user_id();
        if ($user_id > 0) {
            return array(
                'identifier' => (string) $user_id,
                'guard' => 'customer',
                'is_logged_in' => true
            );
        }
        if (!isset($_COOKIE['appnuno_customer_id'])) {
            return null;
        }
        $identifier = preg_replace('/[^0-9]/', '', (string) wp_unslash($_COOKIE['appnuno_customer_id']));
        if (!is_string($identifier) || $identifier === '') {
            return null;
        }

        return array(
            'identifier' => $identifier,
            'guard' => 'customer',
            'is_logged_in' => false
        );
    }

    private static function enabled(): bool {
        return in_array(get_option('module_appnuno_status', '0'), array('1', 1), true);
    }

    private static function requestUri(): string {
        $uri = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';

        return $uri === '' ? '/' : $uri;
    }

    private static function headers(string $content_type, int $status): void {
        status_header($status);
        nocache_headers();
        header('Content-Type: ' . $content_type);
        header('X-Content-Type-Options: nosniff');
    }

    private static function json($value): string {
        $json = json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return is_string($json) ? $json : 'null';
    }
}
