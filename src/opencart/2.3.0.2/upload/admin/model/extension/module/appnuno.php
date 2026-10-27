<?php
class ModelExtensionModuleAppnuno extends Model {
    public function defaultConfig() {
        return json_encode(array(
            'cartTabId' => 'cart',
            'defaultTabs' => array(
                array('id' => 'home', 'label' => 'Home', 'url' => '/', 'icon' => 'home-outline', 'activeIcon' => 'home'),
                array('id' => 'cart', 'label' => 'Cart', 'url' => '/index.php?route=checkout/cart', 'icon' => 'cart-outline', 'activeIcon' => 'cart')
            ),
            'routes' => (object)array(),
            'back' => array('enabled' => true, 'placement' => 'separate'),
            'glass' => array('enabled' => true, 'colorScheme' => 'auto', 'style' => 'regular', 'animate' => true, 'animationDuration' => 0.3, 'isInteractive' => true, 'tintColor' => 'rgba(0, 0, 0, 0.02)'),
            'colors' => array(
                'light' => array('backgroundColor' => '#FFFFFF', 'contentColor' => '#2C3136'),
                'dark' => array('backgroundColor' => '#2C3136', 'contentColor' => '#FFFFFF')
            )
        ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function getDestinations($language_id, $store_id) {
        $destinations = array(
            array('label' => 'Home', 'url' => '/'),
            array('label' => 'Special offers', 'url' => '/index.php?route=product/special'),
            array('label' => 'Search', 'url' => '/index.php?route=product/search'),
            array('label' => 'Account', 'url' => '/index.php?route=account/account'),
            array('label' => 'Login', 'url' => '/index.php?route=account/login'),
            array('label' => 'Register', 'url' => '/index.php?route=account/register'),
            array('label' => 'Cart', 'url' => '/index.php?route=checkout/cart'),
            array('label' => 'Checkout', 'url' => '/index.php?route=checkout/checkout'),
            array('label' => 'Contact', 'url' => '/index.php?route=information/contact')
        );
        $information = $this->db->query("SELECT i.information_id, id.title FROM " . DB_PREFIX . "information i INNER JOIN " . DB_PREFIX . "information_description id ON (i.information_id = id.information_id) INNER JOIN " . DB_PREFIX . "information_to_store i2s ON (i.information_id = i2s.information_id) WHERE id.language_id = '" . (int)$language_id . "' AND i2s.store_id = '" . (int)$store_id . "' AND i.status = '1' ORDER BY id.title ASC");
        foreach ($information->rows as $page) {
            $destinations[] = array('label' => 'Information: ' . $page['title'], 'url' => '/index.php?route=information/information&information_id=' . (int)$page['information_id']);
        }
        $categories = $this->db->query("SELECT c.category_id, cd.name, (SELECT GROUP_CONCAT(cp.path_id ORDER BY cp.level SEPARATOR '_') FROM " . DB_PREFIX . "category_path cp WHERE cp.category_id = c.category_id) AS category_path FROM " . DB_PREFIX . "category c INNER JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id) INNER JOIN " . DB_PREFIX . "category_to_store c2s ON (c.category_id = c2s.category_id) WHERE cd.language_id = '" . (int)$language_id . "' AND c2s.store_id = '" . (int)$store_id . "' AND c.status = '1' ORDER BY cd.name ASC");
        foreach ($categories->rows as $category) {
            $path = isset($category['category_path']) && strlen($category['category_path']) ? $category['category_path'] : (int)$category['category_id'];
            $destinations[] = array('label' => 'Category: ' . $category['name'], 'url' => '/index.php?route=product/category&path=' . $path);
        }
        return $destinations;
    }

    public function validateConfig($json) {
        if (!is_string($json) || strlen($json) > 65535) {
            return 'module_appnuno_config: Configuration must be valid JSON smaller than 64 KB.';
        }
        $config = json_decode($json, true);
        if (!is_array($config) || json_last_error() || !isset($config['defaultTabs']) || !is_array($config['defaultTabs']) || !isset($config['routes']) || !is_array($config['routes'])) {
            return 'module_appnuno_config: Configuration is missing its tab sets. Submit the settings page again.';
        }
        if (count($config['routes']) > 30) {
            return 'routes: A maximum of 30 route-specific tab sets is allowed.';
        }
        $error = $this->validateTabs($config['defaultTabs'], 'defaultTabs');
        if (strlen($error)) {
            return $error;
        }
        foreach ($config['routes'] as $route => $tabs) {
            if (!is_string($route) || !preg_match('/^[A-Za-z0-9_\/.]+$/', $route)) {
                return 'routes.' . $route . '.route: Each route must be a valid OpenCart route such as checkout/cart.';
            }
            $error = $this->validateTabs($tabs, 'routes.' . $route);
            if (strlen($error)) {
                return $error;
            }
        }
        if (isset($config['cartTabId']) && (!is_string($config['cartTabId']) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $config['cartTabId']))) {
            return 'cartTabId: Choose a valid cart badge tab.';
        }
        if (isset($config['back']) && (!is_array($config['back']) || (isset($config['back']['placement']) && !in_array($config['back']['placement'], array('separate', 'bar'), true)) || (isset($config['back']['enabled']) && !is_bool($config['back']['enabled'])))) {
            return 'back: Choose a valid back-button configuration.';
        }
        $visual_error = $this->visualError($config);
        if (strlen($visual_error)) {
            return $visual_error;
        }
        return '';
    }

    private function validateTabs($tabs, $path) {
        if (!is_array($tabs) || count($tabs) > 8) {
            return $path . ': A tab set must contain no more than 8 tabs.';
        }
        $ids = array();
        foreach ($tabs as $index => $tab) {
            $tab_path = $path . '[' . $index . ']';
            if (!is_array($tab) || !isset($tab['id']) || !is_string($tab['id']) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $tab['id'])) {
                return $tab_path . '.id: Enter a valid, unique tab ID.';
            }
            if (in_array($tab['id'], $ids, true)) {
                return $tab_path . '.id: Tab IDs must be unique within each tab set.';
            }
            if (isset($tab['url']) && !$this->validLocalUrl($tab['url'])) {
                return $tab_path . '.url: Choose a store destination or enter a valid local path beginning with /.';
            }
            if (isset($tab['urls'])) {
                if (!is_array($tab['urls']) || !count($tab['urls']) || count($tab['urls']) > 20) {
                    return $tab_path . '.urls: Add at least one localized URL.';
                }
                foreach ($tab['urls'] as $language_code => $url) {
                    if (!is_string($language_code) || (!in_array($language_code, array('default'), true) && !preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $language_code))) {
                        return $tab_path . '.urls: Use a valid OpenCart language code.';
                    }
                    if (!$this->validLocalUrl($url)) {
                        return $tab_path . '.urls.' . $language_code . ': Enter a valid local URL beginning with /.';
                    }
                }
            } elseif (!isset($tab['url'])) {
                return $tab_path . '.url: Choose a store destination or enter a valid local path beginning with /.';
            }
            if (!$this->validTabLabels($tab)) {
                return $tab_path . '.labels: Enter a label for each enabled store language.';
            }
            foreach (array('icon', 'activeIcon') as $icon_key) {
                if (isset($tab[$icon_key]) && (!is_string($tab[$icon_key]) || !preg_match('/^[a-z0-9-]{1,80}$/', $tab[$icon_key]))) {
                    return $tab_path . '.' . $icon_key . ': Use a valid Material Design Icon name.';
                }
            }
            if (isset($tab['active']) && !is_bool($tab['active'])) {
                return $tab_path . '.active: The selected state must be true or false.';
            }
            if (isset($tab['badge']) && (!is_int($tab['badge']) || !is_int(filter_var($tab['badge'], FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)))))) {
                return $tab_path . '.badge: The badge count must be zero or a positive integer.';
            }
            $ids[] = $tab['id'];
        }
        return '';
    }

    private function visualError($config) {
        if (isset($config['glass']) && !is_null($config['glass'])) {
            if (!is_array($config['glass'])) {
                return 'glass: Glass settings must be an object.';
            }
            foreach (array('enabled', 'animate', 'isInteractive') as $key) {
                if (isset($config['glass'][$key]) && !is_bool($config['glass'][$key])) {
                    return 'glass.' . $key . ': Choose a valid glass setting.';
                }
            }
            if (isset($config['glass']['colorScheme']) && !in_array($config['glass']['colorScheme'], array('auto', 'light', 'dark'), true)) {
                return 'glass.colorScheme: Choose Automatic, Light or Dark.';
            }
            if (isset($config['glass']['style']) && !in_array($config['glass']['style'], array('regular', 'clear', 'none'), true)) {
                return 'glass.style: Choose a valid glass style.';
            }
            if (isset($config['glass']['animationDuration']) && !preg_match('/^(?:10(?:\.0+)?|[0-9](?:\.[0-9]+)?)$/', (string)$config['glass']['animationDuration'])) {
                return 'glass.animationDuration: Enter a duration from 0 to 10 seconds.';
            }
            foreach (array('tintColor', 'contentColor') as $key) {
                if (!isset($config['glass'][$key])) {
                    continue;
                }
                if (!is_string($config['glass'][$key])) {
                    return 'glass.' . $key . ': Enter a valid tint color and opacity.';
                }
                $tint_pattern = '/^(#[A-Fa-f0-9]{6}|rgba?\\(\\s*(?:25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9])\\s*,\\s*(?:25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9])\\s*,\\s*(?:25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9])(?:\\s*,\\s*(?:0(?:\\.[0-9]+)?|1(?:\\.0+)?))?\\s*\\))$/i';
                if ($key === 'tintColor' && !preg_match($tint_pattern, $config['glass'][$key])) {
                    return 'glass.' . $key . ': Enter a valid tint color and opacity.';
                }
            }
        }
        if (isset($config['colors'])) {
            if (!is_array($config['colors'])) {
                return 'colors: Navigation colors must be an object.';
            }
            foreach (array('light', 'dark') as $scheme) {
                if (isset($config['colors'][$scheme])) {
                    if (!is_array($config['colors'][$scheme])) {
                        return 'colors.' . $scheme . ': Navigation color values must be an object.';
                    }
                    foreach (array('backgroundColor', 'contentColor') as $key) {
                        if (isset($config['colors'][$scheme][$key]) && (!is_string($config['colors'][$scheme][$key]) || !preg_match('/^#[0-9A-Fa-f]{6}$/', $config['colors'][$scheme][$key]))) {
                            return 'colors.' . $scheme . '.' . $key . ': Choose a valid six-digit color.';
                        }
                    }
                }
            }
        }
        return '';
    }

    private function validLocalUrl($url) {
        return is_string($url) && preg_match('/^\/(?!\/)/', $url) && !preg_match('~[[:cntrl:] ]~', $url) && !preg_match('~\\\\~', $url);
    }

    private function validTabLabels($tab) {
        if (isset($tab['labels'])) {
            if (!is_array($tab['labels']) || !count($tab['labels']) || count($tab['labels']) > 20) {
                return false;
            }
            foreach ($tab['labels'] as $language_code => $label) {
                if (!is_string($language_code) || (!in_array($language_code, array('default'), true) && !preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $language_code)) || !is_string($label) || !strlen(trim($label)) || strlen($label) > 80) {
                    return false;
                }
            }
            return true;
        }
        return isset($tab['label']) && is_string($tab['label']) && strlen(trim($tab['label'])) && strlen($tab['label']) <= 80;
    }
}
