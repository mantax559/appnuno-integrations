<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

final class AppnunoCatalog
{
    private static $buildingLanguages = false;

    public static function text(string $key): string
    {
        $catalog = self::chromeCatalog();
        if (isset($catalog[$key]) && is_string($catalog[$key])) {
            return $catalog[$key];
        }

        return $key;
    }

    public static function normalizeLocale(string $locale): string
    {
        $code = strtolower(str_replace('_', '-', $locale));
        if (preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/', $code)) {
            return $code;
        }

        return 'en-gb';
    }

    public static function chromeLocale(): string
    {
        $code = self::normalizeLocale(self::contextLocale());
        foreach (self::catalogCandidates($code) as $candidate) {
            if (is_file(self::catalogPath($candidate))) {
                return $candidate;
            }
        }

        return 'en-gb';
    }

    public static function currentLanguageCode(): string
    {
        return self::normalizeLocale(self::contextLocale());
    }

    public static function languages(): array
    {
        $found = array();
        self::$buildingLanguages = true;
        $rows = Language::getLanguages(true);
        if (is_array($rows)) {
            foreach ($rows as $language) {
                if (!is_array($language)) {
                    continue;
                }
                $locale = '';
                if (isset($language['locale']) && is_string($language['locale']) && $language['locale'] !== '') {
                    $locale = $language['locale'];
                } elseif (isset($language['language_code']) && is_string($language['language_code']) && $language['language_code'] !== '') {
                    $locale = $language['language_code'];
                } elseif (isset($language['iso_code']) && is_string($language['iso_code'])) {
                    $locale = $language['iso_code'];
                }
                $code = self::normalizeLocale($locale);
                $name = isset($language['name']) && is_string($language['name']) && $language['name'] !== '' ? $language['name'] : self::knownLanguageName($code);
                $found[$code] = $name;
            }
        }
        if (!count($found)) {
            $code = self::currentLanguageCode();
            $found[$code] = self::knownLanguageName($code);
        }
        $languages = array();
        foreach ($found as $code => $name) {
            $languages[] = array(
                'code' => $code,
                'name' => $name,
                'image' => false,
            );
        }
        self::$buildingLanguages = false;

        return $languages;
    }

    public static function languageCodes(): array
    {
        $codes = array();
        foreach (self::languages() as $language) {
            if (isset($language['code']) && is_string($language['code'])) {
                $codes[] = $language['code'];
            }
        }

        return $codes;
    }

    public static function defaultLanguageCode(): string
    {
        $codes = self::languageCodes();
        $current = self::currentLanguageCode();
        if (in_array($current, $codes, true)) {
            return $current;
        }

        return count($codes) ? $codes[0] : 'en-gb';
    }

    public static function languageName(string $code): string
    {
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

    public static function routeKeys(): array
    {
        return array(
            'common/home' => 'text_route_home',
            'product/category' => 'text_route_category',
            'product/product' => 'text_route_product',
            'product/special' => 'text_route_special',
            'product/search' => 'text_route_search',
            'product/manufacturer' => 'text_route_manufacturer',
            'information/information' => 'text_route_information',
            'information/contact' => 'text_route_contact',
            'account/account' => 'text_route_account',
            'account/login' => 'text_route_login',
            'account/register' => 'text_route_register',
            'checkout/cart' => 'text_route_cart',
            'checkout/checkout' => 'text_route_checkout',
        );
    }

    public static function routeLabel(string $route): string
    {
        $labels = self::routeKeys();

        return isset($labels[$route]) ? self::text($labels[$route]) : $route;
    }

    public static function routeOptions(): array
    {
        $options = array();
        foreach (self::routeKeys() as $route => $key) {
            $options[] = array(
                'label' => self::text($key),
                'route' => $route,
            );
        }

        return $options;
    }

    public static function destinations(): array
    {
        $context = Context::getContext();
        $link = $context->link;
        $idLang = is_object($context->language) ? (int) $context->language->id : (int) Configuration::get('PS_LANG_DEFAULT');
        $idShop = is_object($context->shop) ? (int) $context->shop->id : (int) Configuration::get('PS_SHOP_DEFAULT');
        $destinations = array(
            array('label' => 'Home', 'url' => self::normalizeHome(self::relativeUrl($link->getPageLink('index', null, $idLang)))),
            array('label' => 'Special offers', 'url' => self::relativeUrl($link->getPageLink('prices-drop', null, $idLang))),
            array('label' => 'Search', 'url' => self::relativeUrl($link->getPageLink('search', null, $idLang))),
            array('label' => 'Account', 'url' => self::relativeUrl($link->getPageLink('my-account', null, $idLang))),
            array('label' => 'Login', 'url' => self::relativeUrl($link->getPageLink('authentication', null, $idLang))),
            array('label' => 'Register', 'url' => self::relativeUrl($link->getPageLink('registration', null, $idLang))),
            array('label' => 'Cart', 'url' => self::relativeUrl($link->getPageLink('cart', null, $idLang))),
            array('label' => 'Checkout', 'url' => self::relativeUrl($link->getPageLink('order', null, $idLang))),
            array('label' => 'Contact', 'url' => self::relativeUrl($link->getPageLink('contact', null, $idLang))),
        );
        $pages = Db::getInstance()->executeS('
            SELECT c.id_cms, cl.meta_title
            FROM `' . _DB_PREFIX_ . 'cms` c
            INNER JOIN `' . _DB_PREFIX_ . 'cms_lang` cl ON c.id_cms = cl.id_cms AND cl.id_lang = ' . $idLang . ' AND cl.id_shop = ' . $idShop . '
            WHERE c.active = 1
            ORDER BY cl.meta_title ASC
        ');
        if (is_array($pages)) {
            foreach ($pages as $page) {
                if (!isset($page['id_cms'])) {
                    continue;
                }
                $title = isset($page['meta_title']) && is_string($page['meta_title']) ? $page['meta_title'] : '';
                $destinations[] = array(
                    'label' => 'Information: ' . $title,
                    'url' => self::relativeUrl($link->getCMSLink((int) $page['id_cms'], null, null, $idLang, $idShop)),
                );
            }
        }
        $categories = Db::getInstance()->executeS('
            SELECT c.id_category, cl.name
            FROM `' . _DB_PREFIX_ . 'category` c
            INNER JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON c.id_category = cl.id_category AND cl.id_lang = ' . $idLang . ' AND cl.id_shop = ' . $idShop . '
            INNER JOIN `' . _DB_PREFIX_ . 'category_shop` cs ON c.id_category = cs.id_category AND cs.id_shop = ' . $idShop . '
            WHERE c.active = 1 AND c.id_category NOT IN (1, 2)
            ORDER BY cl.name ASC
        ');
        if (is_array($categories)) {
            foreach ($categories as $category) {
                if (!isset($category['id_category'])) {
                    continue;
                }
                $name = isset($category['name']) && is_string($category['name']) ? $category['name'] : '';
                $destinations[] = array(
                    'label' => 'Category: ' . $name,
                    'url' => self::relativeUrl($link->getCategoryLink((int) $category['id_category'], null, $idLang, null, $idShop)),
                );
            }
        }

        return self::translatedDestinations($destinations);
    }

    public static function currentRoute(): string
    {
        $controller = Context::getContext()->controller;
        $phpSelf = '';
        if (is_object($controller) && isset($controller->php_self) && is_string($controller->php_self)) {
            $phpSelf = $controller->php_self;
        }

        return self::routeFromController($phpSelf);
    }

    private static function routeFromController(string $phpSelf): string
    {
        $routes = array(
            'index' => 'common/home',
            'category' => 'product/category',
            'product' => 'product/product',
            'search' => 'product/search',
            'pricesdrop' => 'product/special',
            'prices-drop' => 'product/special',
            'manufacturer' => 'product/manufacturer',
            'cms' => 'information/information',
            'contact' => 'information/contact',
            'my-account' => 'account/account',
            'identity' => 'account/account',
            'addresses' => 'account/account',
            'address' => 'account/account',
            'history' => 'account/account',
            'order-detail' => 'account/account',
            'order-slip' => 'account/account',
            'order-follow' => 'account/account',
            'discount' => 'account/account',
            'order-return' => 'account/account',
            'authentication' => 'account/login',
            'registration' => 'account/register',
            'cart' => 'checkout/cart',
            'order' => 'checkout/checkout',
            'order-opc' => 'checkout/checkout',
            'order-confirmation' => 'checkout/checkout',
        );

        return isset($routes[$phpSelf]) ? $routes[$phpSelf] : 'common/home';
    }

    private static function knownLanguageName(string $code): string
    {
        $names = array(
            'en-us' => 'English (United States)',
            'en-gb' => 'English (United Kingdom)',
            'lt-lt' => 'Lietuvių',
            'de-de' => 'Deutsch',
            'en' => 'English',
        );

        return isset($names[$code]) ? $names[$code] : $code;
    }

    private static function translatedDestinations(array $destinations): array
    {
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
            'Contact' => 'text_destination_contact',
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

    public static function relativeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '/';
        }
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return '/';
        }
        $path = isset($parts['path']) && is_string($parts['path']) && $parts['path'] !== '' ? $parts['path'] : '/';
        $base = defined('__PS_BASE_URI__') ? (string) __PS_BASE_URI__ : '/';
        $base = $base === '' ? '/' : $base;
        $prefix = rtrim($base, '/');
        if ($prefix !== '' && strpos($path, $prefix) === 0) {
            $path = substr($path, strlen($prefix));
            if ($path === '' || $path[0] !== '/') {
                $path = '/' . ltrim($path, '/');
            }
        }
        if (isset($parts['query']) && is_string($parts['query']) && $parts['query'] !== '') {
            $path .= '?' . $parts['query'];
        }

        return $path === '' ? '/' : $path;
    }

    private static function normalizeHome(string $url): string
    {
        $query = '';
        $path = $url;
        $question = strpos($url, '?');
        if ($question !== false) {
            $path = substr($url, 0, $question);
            $query = substr($url, $question);
        }
        $trimmed = trim($path, '/');
        if ($trimmed === '') {
            return '/' . $query;
        }
        $languages = Language::getLanguages(true);
        if (is_array($languages)) {
            foreach ($languages as $language) {
                $iso = isset($language['iso_code']) && is_string($language['iso_code']) ? $language['iso_code'] : '';
                if ($iso !== '' && strcasecmp($trimmed, $iso) === 0) {
                    return '/' . $query;
                }
            }
        }

        return $url;
    }

    private static function contextLocale(): string
    {
        $language = Context::getContext()->language;
        if (is_object($language)) {
            if (isset($language->locale) && is_string($language->locale) && $language->locale !== '') {
                return $language->locale;
            }
            if (isset($language->language_code) && is_string($language->language_code) && $language->language_code !== '') {
                return $language->language_code;
            }
            if (isset($language->iso_code) && is_string($language->iso_code) && $language->iso_code !== '') {
                return $language->iso_code;
            }
        }

        return 'en-gb';
    }

    private static function chromeCatalog(): array
    {
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

    private static function catalogPath(string $locale): string
    {
        return AppnunoPaths::adminDirectory() . '/i18n/' . $locale . '.json';
    }

    private static function catalogCandidates(string $code): array
    {
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

    private static function canonicalLocale(string $code): string
    {
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
            'zh-sg' => 'zh-cn',
        );

        return isset($locales[$code]) ? $locales[$code] : '';
    }
}
