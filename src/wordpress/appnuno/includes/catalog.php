<?php
if (!defined('ABSPATH')) {
    exit;
}

final class Appnuno_Catalog {
    private static $buildingLanguages = false;

    public static function text(string $key): string {
        $catalog = self::chromeCatalog();
        if (isset($catalog[$key]) && is_string($catalog[$key])) {
            return $catalog[$key];
        }

        return $key;
    }

    public static function normalizeLocale(string $locale): string {
        $code = strtolower(str_replace('_', '-', $locale));
        if (preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/', $code)) {
            return $code;
        }

        return 'en-gb';
    }

    public static function chromeLocale(): string {
        $code = self::normalizeLocale(self::wordpressLocale());
        foreach (self::catalogCandidates($code) as $candidate) {
            if (is_file(self::catalogPath($candidate))) {
                return $candidate;
            }
        }

        return 'en-gb';
    }

    public static function currentLanguageCode(): string {
        if (function_exists('pll_current_language')) {
            $locale = pll_current_language('locale');
            if (is_string($locale) && $locale !== '') {
                return self::normalizeLocale($locale);
            }
        }
        if (function_exists('icl_get_languages')) {
            $languages = icl_get_languages('skip_missing=0');
            if (is_array($languages)) {
                foreach ($languages as $language) {
                    if (is_array($language) && !empty($language['active']) && isset($language['default_locale']) && is_string($language['default_locale'])) {
                        return self::normalizeLocale($language['default_locale']);
                    }
                }
            }
        }
        $locale = self::wordpressLocale();

        return self::normalizeLocale(is_string($locale) ? $locale : 'en-gb');
    }

    public static function languages(): array {
        $found = array();
        self::$buildingLanguages = true;
        if (function_exists('pll_languages_list')) {
            $locales = pll_languages_list(array('fields' => 'locale'));
            $names = pll_languages_list(array('fields' => 'name'));
            if (is_array($locales)) {
                foreach ($locales as $index => $locale) {
                    if (!is_string($locale) || $locale === '') {
                        continue;
                    }
                    $code = self::normalizeLocale($locale);
                    $name = is_array($names) && isset($names[$index]) && is_string($names[$index]) && $names[$index] !== '' ? $names[$index] : self::knownLanguageName($code);
                    $found[$code] = $name;
                }
            }
        } elseif (function_exists('icl_get_languages')) {
            $languages = icl_get_languages('skip_missing=0');
            if (is_array($languages)) {
                foreach ($languages as $language) {
                    if (!is_array($language) || !isset($language['default_locale']) || !is_string($language['default_locale'])) {
                        continue;
                    }
                    $code = self::normalizeLocale($language['default_locale']);
                    $name = isset($language['native_name']) && is_string($language['native_name']) && $language['native_name'] !== '' ? $language['native_name'] : self::knownLanguageName($code);
                    $found[$code] = $name;
                }
            }
        }
        if (!count($found)) {
            $code = self::normalizeLocale(is_string(get_locale()) ? get_locale() : 'en-gb');
            $found[$code] = self::knownLanguageName($code);
        }
        $languages = array();
        foreach ($found as $code => $name) {
            $languages[] = array(
                'code' => $code,
                'name' => $name,
                'image' => false
            );
        }
        self::$buildingLanguages = false;

        return $languages;
    }

    public static function languageCodes(): array {
        $codes = array();
        foreach (self::languages() as $language) {
            if (isset($language['code']) && is_string($language['code'])) {
                $codes[] = $language['code'];
            }
        }

        return $codes;
    }

    public static function defaultLanguageCode(): string {
        $codes = self::languageCodes();
        $current = self::currentLanguageCode();
        if (in_array($current, $codes, true)) {
            return $current;
        }

        return count($codes) ? $codes[0] : 'en-gb';
    }

    public static function languageName(string $code): string {
        if ($code === 'default') {
            return self::text('text_default_language');
        }
        if (!self::$buildingLanguages) {
            foreach (self::languages() as $language) {
                if (isset($language['code'], $language['name']) && $language['code'] === $code && is_string($language['name']) && $language['name'] !== '') {
                    return $language['name'];
                }
            }
        }

        return self::knownLanguageName($code);
    }

    public static function routeKeys(): array {
        return array(
            'common/home' => 'text_route_home',
            'shop' => 'text_route_shop',
            'product/category' => 'text_route_category',
            'product/product' => 'text_route_product',
            'product/tag' => 'text_route_tag',
            'product/search' => 'text_route_search',
            'product/special' => 'text_route_special',
            'checkout/cart' => 'text_route_cart',
            'checkout/checkout' => 'text_route_checkout',
            'account/account' => 'text_route_account',
            'information/information' => 'text_route_information',
            'post' => 'text_route_post',
            'blog' => 'text_route_blog'
        );
    }

    public static function routeLabel(string $route): string {
        $labels = self::routeKeys();

        return isset($labels[$route]) ? self::text($labels[$route]) : $route;
    }

    public static function routeOptions(): array {
        $options = array();
        foreach (self::routeKeys() as $route => $key) {
            $options[] = array(
                'label' => self::text($key),
                'route' => $route
            );
        }

        return $options;
    }

    public static function destinations(): array {
        $shop = self::pageUrl('shop');
        $account = self::pageUrl('myaccount');
        $destinations = array(
            array('label' => 'Home', 'url' => '/'),
            array('label' => 'Shop', 'url' => $shop),
            array('label' => 'Special offers', 'url' => self::withQuery($shop, 'on_sale', '1')),
            array('label' => 'Search', 'url' => self::withQuery('/', 's', '') . '&post_type=product'),
            array('label' => 'Account', 'url' => $account),
            array('label' => 'Login', 'url' => $account),
            array('label' => 'Register', 'url' => $account),
            array('label' => 'Cart', 'url' => self::relativeUrl(self::stringUrl(function_exists('wc_get_cart_url') ? wc_get_cart_url() : ''))),
            array('label' => 'Checkout', 'url' => self::relativeUrl(self::stringUrl(function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : '')))
        );
        $reserved = array();
        foreach (array('shop', 'cart', 'checkout', 'myaccount') as $page) {
            if (function_exists('wc_get_page_id')) {
                $page_id = (int) wc_get_page_id($page);
                if ($page_id > 0) {
                    $reserved[$page_id] = true;
                }
            }
        }
        $contact = get_page_by_path('contact', OBJECT, 'page');
        if ($contact instanceof WP_Post && $contact->post_status === 'publish') {
            $destinations[] = array(
                'label' => 'Contact',
                'url' => self::relativeUrl((string) get_permalink($contact))
            );
            $reserved[(int) $contact->ID] = true;
        }
        $pages = get_posts(array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'suppress_filters' => false
        ));
        foreach ($pages as $page) {
            if (!$page instanceof WP_Post || isset($reserved[(int) $page->ID])) {
                continue;
            }
            $destinations[] = array(
                'label' => 'Page: ' . $page->post_title,
                'url' => self::relativeUrl((string) get_permalink($page))
            );
        }
        $terms = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false
        ));
        if (is_array($terms)) {
            foreach ($terms as $term) {
                if (!$term instanceof WP_Term) {
                    continue;
                }
                $link = get_term_link($term);
                if (!is_string($link)) {
                    continue;
                }
                $destinations[] = array(
                    'label' => 'Category: ' . $term->name,
                    'url' => self::relativeUrl($link)
                );
            }
        }

        return self::translatedDestinations($destinations);
    }

    public static function currentRoute(): string {
        if (function_exists('is_cart') && is_cart()) {
            return 'checkout/cart';
        }
        if (function_exists('is_checkout') && is_checkout() && !(function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received'))) {
            return 'checkout/checkout';
        }
        if (function_exists('is_account_page') && is_account_page()) {
            return 'account/account';
        }
        if (function_exists('is_product') && is_product()) {
            return 'product/product';
        }
        if (function_exists('is_product_category') && is_product_category()) {
            return 'product/category';
        }
        if (function_exists('is_product_tag') && is_product_tag()) {
            return 'product/tag';
        }
        if (function_exists('is_shop') && is_shop() && self::onSaleRequested()) {
            return 'product/special';
        }
        if (function_exists('is_shop') && is_shop()) {
            return 'shop';
        }
        if (is_search()) {
            $post_type = get_query_var('post_type');
            if ($post_type === 'product' || (is_array($post_type) && in_array('product', $post_type, true))) {
                return 'product/search';
            }
        }
        if (is_front_page()) {
            return 'common/home';
        }
        if (is_home()) {
            return 'blog';
        }
        if (is_singular('post')) {
            return 'post';
        }
        if (is_page()) {
            return 'information/information';
        }
        if (function_exists('is_checkout') && is_checkout()) {
            return 'checkout/checkout';
        }

        return 'common/home';
    }

    public static function onSaleRequested(): bool {
        if (!isset($_GET['on_sale'])) {
            return false;
        }
        $value = wp_unslash($_GET['on_sale']);
        if (!is_string($value) || $value === '' || $value === '0') {
            return false;
        }

        return true;
    }

    private static function wordpressLocale(): string {
        if (function_exists('determine_locale')) {
            $locale = determine_locale();
        } elseif (is_admin() && function_exists('get_user_locale')) {
            $locale = get_user_locale();
        } else {
            $locale = get_locale();
        }

        return is_string($locale) ? $locale : 'en_US';
    }

    private static function knownLanguageName(string $code): string {
        $names = array(
            'en-us' => 'English (United States)',
            'en-gb' => 'English (United Kingdom)',
            'lt-lt' => 'Lietuvių',
            'de-de' => 'Deutsch',
            'en' => 'English'
        );

        return isset($names[$code]) ? $names[$code] : $code;
    }

    private static function stringUrl($url): string {
        return is_string($url) ? $url : '';
    }

    private static function translatedDestinations(array $destinations): array {
        $labels = array(
            'Home' => 'text_destination_home',
            'Shop' => 'text_destination_shop',
            'Special offers' => 'text_destination_special',
            'Search' => 'text_destination_search',
            'Account' => 'text_destination_account',
            'Login' => 'text_destination_login',
            'Register' => 'text_destination_register',
            'Cart' => 'text_destination_cart',
            'Checkout' => 'text_destination_checkout',
            'Contact' => 'text_destination_contact'
        );
        foreach ($destinations as $index => $destination) {
            if (!isset($destination['label']) || !is_string($destination['label'])) {
                continue;
            }
            if (isset($labels[$destination['label']])) {
                $destinations[$index]['label'] = self::text($labels[$destination['label']]);
                continue;
            }
            foreach (array('Page: ' => 'text_destination_page', 'Information: ' => 'text_destination_information', 'Category: ' => 'text_destination_category') as $prefix => $key) {
                if (strncmp($destination['label'], $prefix, strlen($prefix)) === 0) {
                    $destinations[$index]['label'] = self::text($key) . substr($destination['label'], strlen($prefix));
                    break;
                }
            }
        }

        return $destinations;
    }

    private static function pageUrl(string $page): string {
        if (function_exists('wc_get_page_permalink')) {
            $url = wc_get_page_permalink($page);
            if (is_string($url) && $url !== '') {
                return self::relativeUrl($url);
            }
        }

        return '/';
    }

    private static function withQuery(string $url, string $key, string $value): string {
        $base = $url === '' ? '/' : $url;
        $separator = strpos($base, '?') === false ? '?' : '&';

        return $base . $separator . rawurlencode($key) . '=' . rawurlencode($value);
    }

    private static function relativeUrl(string $url): string {
        $url = trim($url);
        if ($url === '') {
            return '/';
        }
        $parts = wp_parse_url($url);
        if (!is_array($parts)) {
            return '/';
        }
        $path = isset($parts['path']) && is_string($parts['path']) && $parts['path'] !== '' ? $parts['path'] : '/';
        $home = wp_parse_url(home_url('/'));
        $home_path = is_array($home) && isset($home['path']) && is_string($home['path']) ? $home['path'] : '/';
        $home_path = $home_path === '' ? '/' : $home_path;
        if ($home_path !== '/' && strpos($path, untrailingslashit($home_path)) === 0) {
            $path = substr($path, strlen(untrailingslashit($home_path)));
            if ($path === '' || $path[0] !== '/') {
                $path = '/' . ltrim($path, '/');
            }
        }
        if (isset($parts['query']) && is_string($parts['query']) && $parts['query'] !== '') {
            $path .= '?' . $parts['query'];
        }

        return $path === '' ? '/' : $path;
    }

    private static function chromeCatalog(): array {
        static $catalog = null;
        if (is_array($catalog)) {
            return $catalog;
        }
        $path = self::catalogPath(self::chromeLocale());
        if (!is_file($path)) {
            $path = self::catalogPath('en-gb');
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        $catalog = is_array($decoded) ? $decoded : array();

        return $catalog;
    }

    private static function catalogPath(string $locale): string {
        return Appnuno_Paths::adminDirectory() . '/i18n/' . $locale . '.json';
    }

    private static function catalogCandidates(string $code): array {
        $candidates = array($code);
        $canonical = self::canonicalLocale($code);
        if ($canonical !== '' && !in_array($canonical, $candidates, true)) {
            $candidates[] = $canonical;
        }
        $dash = strpos($code, '-');
        if ($dash !== false) {
            $language = self::canonicalLocale(substr($code, 0, $dash));
            if ($language !== '' && !in_array($language, $candidates, true)) {
                $candidates[] = $language;
            }
        }
        if (!in_array('en-gb', $candidates, true)) {
            $candidates[] = 'en-gb';
        }

        return $candidates;
    }

    private static function canonicalLocale(string $code): string {
        $locales = array(
            'ar' => 'ar-sa',
            'be' => 'be-by',
            'bg' => 'bg-bg',
            'cs' => 'cs-cz',
            'da' => 'da-dk',
            'de' => 'de-de',
            'de-at' => 'de-de',
            'de-ch' => 'de-de',
            'de-li' => 'de-de',
            'de-lu' => 'de-de',
            'el' => 'el-gr',
            'en' => 'en-gb',
            'en-au' => 'en-gb',
            'en-ca' => 'en-gb',
            'en-ie' => 'en-gb',
            'en-nz' => 'en-gb',
            'en-za' => 'en-gb',
            'es' => 'es-es',
            'es-ar' => 'es-es',
            'es-mx' => 'es-es',
            'et' => 'et-ee',
            'fi' => 'fi-fi',
            'fr' => 'fr-fr',
            'fr-be' => 'fr-fr',
            'fr-ca' => 'fr-fr',
            'fr-ch' => 'fr-fr',
            'hi' => 'hi-in',
            'hr' => 'hr-hr',
            'hu' => 'hu-hu',
            'hy' => 'hy-am',
            'is' => 'is-is',
            'it' => 'it-it',
            'ja' => 'ja-jp',
            'ka' => 'ka-ge',
            'ko' => 'ko-kr',
            'lt' => 'lt-lt',
            'lv' => 'lv-lv',
            'nb' => 'nb-no',
            'nl' => 'nl-nl',
            'nl-be' => 'nl-nl',
            'no' => 'nb-no',
            'pl' => 'pl-pl',
            'pt' => 'pt-pt',
            'pt-br' => 'pt-pt',
            'ro' => 'ro-ro',
            'ru' => 'ru-ru',
            'sk' => 'sk-sk',
            'sl' => 'sl-si',
            'sq' => 'sq-al',
            'sv' => 'sv-se',
            'tr' => 'tr-tr',
            'uk' => 'uk-ua',
            'zh' => 'zh-cn',
            'zh-hans' => 'zh-cn',
            'zh-hant' => 'zh-tw',
            'zh-hk' => 'zh-tw',
            'zh-mo' => 'zh-tw',
            'zh-sg' => 'zh-cn'
        );

        return isset($locales[$code]) ? $locales[$code] : '';
    }
}
