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

    public function validateConfig($json) {
        if (!is_string($json) || strlen($json) > 65535) {
            return 'Configuration must be valid JSON and smaller than 64 KB.';
        }
        $config = json_decode($json, true);
        if (!is_array($config) || json_last_error() || !isset($config['defaultTabs']) || !is_array($config['defaultTabs']) || !isset($config['routes']) || !is_array($config['routes'])) {
            return 'Configuration must contain defaultTabs and routes arrays.';
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

    private function validTabs($tabs) {
        if (!is_array($tabs) || count($tabs) > 8) {
            return false;
        }
        $ids = array();
        foreach ($tabs as $tab) {
            if (!is_array($tab) || !isset($tab['id'], $tab['label'], $tab['url']) || !is_string($tab['id']) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $tab['id']) || in_array($tab['id'], $ids, true) || !is_string($tab['label']) || !strlen(trim($tab['label'])) || strlen($tab['label']) > 80 || !is_string($tab['url']) || !preg_match('/^\/(?!\/)/', $tab['url']) || preg_match('~[[:cntrl:] ]~', $tab['url']) || preg_match('~\\\\~', $tab['url'])) {
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
