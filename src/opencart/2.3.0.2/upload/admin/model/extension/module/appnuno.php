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
            'back' => array('enabled' => true, 'placement' => 'separate')
        ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function getDestinations($language_id, $store_id) {
        $destinations = array(
            array('label' => 'Home', 'url' => '/'),
            array('label' => 'Special offers', 'url' => '/index.php?route=product/special'),
            array('label' => 'Account', 'url' => '/index.php?route=account/account'),
            array('label' => 'Login', 'url' => '/index.php?route=account/login'),
            array('label' => 'Register', 'url' => '/index.php?route=account/register'),
            array('label' => 'Cart', 'url' => '/index.php?route=checkout/cart'),
            array('label' => 'Checkout', 'url' => '/index.php?route=checkout/checkout')
        );
        $information = $this->db->query("SELECT i.information_id, id.title FROM " . DB_PREFIX . "information i INNER JOIN " . DB_PREFIX . "information_description id ON (i.information_id = id.information_id) INNER JOIN " . DB_PREFIX . "information_to_store i2s ON (i.information_id = i2s.information_id) WHERE id.language_id = '" . (int)$language_id . "' AND i2s.store_id = '" . (int)$store_id . "' AND i.status = '1' ORDER BY id.title ASC");
        foreach ($information->rows as $page) {
            $destinations[] = array('label' => 'Information: ' . $page['title'], 'url' => '/index.php?route=information/information&information_id=' . (int)$page['information_id']);
        }
        $categories = $this->db->query("SELECT c.category_id, cd.name FROM " . DB_PREFIX . "category c INNER JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id) INNER JOIN " . DB_PREFIX . "category_to_store c2s ON (c.category_id = c2s.category_id) WHERE cd.language_id = '" . (int)$language_id . "' AND c2s.store_id = '" . (int)$store_id . "' AND c.status = '1' ORDER BY cd.name ASC");
        foreach ($categories->rows as $category) {
            $destinations[] = array('label' => 'Category: ' . $category['name'], 'url' => '/index.php?route=product/category&path=' . (int)$category['category_id']);
        }
        return $destinations;
    }

    public function validateConfig($json) {
        if (!is_string($json) || strlen($json) > 65535) {
            return 'Configuration must be valid JSON and smaller than 64 KB.';
        }
        $config = json_decode($json, true);
        if (!is_array($config) || json_last_error() || !isset($config['defaultTabs']) || !is_array($config['defaultTabs']) || !isset($config['routes']) || !is_array($config['routes'])) {
            return 'Configuration is incomplete or invalid. Save the tab settings again and check required labels and URLs.';
        }
        if (count($config['routes']) > 30 || !$this->validTabs($config['defaultTabs'])) {
            return 'Default tabs or route count is invalid.';
        }
        foreach ($config['routes'] as $route => $tabs) {
            if (!is_string($route) || !preg_match('/^[A-Za-z0-9_\/.]+$/', $route) || !$this->validTabs($tabs)) {
                return 'Each route needs a valid OpenCart route and tab list.';
            }
        }
        if (isset($config['cartTabId']) && (!is_string($config['cartTabId']) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $config['cartTabId']))) {
            return 'cartTabId must be a stable tab ID.';
        }
        if (isset($config['back']) && (!is_array($config['back']) || (isset($config['back']['placement']) && !in_array($config['back']['placement'], array('separate', 'bar'), true)) || (isset($config['back']['enabled']) && !is_bool($config['back']['enabled'])))) {
            return 'Back-button settings are invalid.';
        }
        if (!$this->validVisuals($config)) {
            return 'Glass and color settings are invalid.';
        }
        return '';
    }

    private function validVisuals($config) {
        if (isset($config['glass']) && !is_null($config['glass'])) {
            if (!is_array($config['glass'])) {
                return false;
            }
            foreach (array('enabled', 'animate', 'isInteractive') as $key) {
                if (isset($config['glass'][$key]) && !is_bool($config['glass'][$key])) {
                    return false;
                }
            }
            if (isset($config['glass']['colorScheme']) && !in_array($config['glass']['colorScheme'], array('auto', 'light', 'dark'), true)) {
                return false;
            }
            if (isset($config['glass']['style']) && !in_array($config['glass']['style'], array('regular', 'clear', 'none'), true)) {
                return false;
            }
            if (isset($config['glass']['animationDuration']) && (!is_numeric($config['glass']['animationDuration']) || !is_finite((float)$config['glass']['animationDuration']) || preg_match('/^-/', (string)$config['glass']['animationDuration']))) {
                return false;
            }
            foreach (array('tintColor', 'contentColor') as $key) {
                if (isset($config['glass'][$key]) && !is_string($config['glass'][$key])) {
                    return false;
                }
            }
        }
        if (isset($config['colors'])) {
            if (!is_array($config['colors'])) {
                return false;
            }
            foreach (array('light', 'dark') as $scheme) {
                if (isset($config['colors'][$scheme])) {
                    if (!is_array($config['colors'][$scheme])) {
                        return false;
                    }
                    foreach (array('backgroundColor', 'contentColor') as $key) {
                        if (isset($config['colors'][$scheme][$key]) && !is_string($config['colors'][$scheme][$key])) {
                            return false;
                        }
                    }
                }
            }
        }
        return true;
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

    private function validTabs($tabs) {
        if (!is_array($tabs) || count($tabs) > 8) {
            return false;
        }
        $ids = array();
        foreach ($tabs as $tab) {
            if (!is_array($tab) || !isset($tab['id'], $tab['url']) || !is_string($tab['id']) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $tab['id']) || in_array($tab['id'], $ids, true) || !is_string($tab['url']) || !preg_match('/^\/(?!\/)/', $tab['url']) || preg_match('~[[:cntrl:] ]~', $tab['url']) || preg_match('~\\\\~', $tab['url']) || !$this->validTabLabels($tab)) {
                return false;
            }
            if (isset($tab['active']) && !is_bool($tab['active'])) {
                return false;
            }
            if (isset($tab['badge']) && (!is_int($tab['badge']) || !is_int(filter_var($tab['badge'], FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)))))) {
                return false;
            }
            foreach (array('icon', 'activeIcon') as $icon_key) {
                if (isset($tab[$icon_key]) && (!is_string($tab[$icon_key]) || !preg_match('/^[a-z0-9-]{1,80}$/', $tab[$icon_key]))) {
                    return false;
                }
            }
            $ids[] = $tab['id'];
        }
        return true;
    }
}
