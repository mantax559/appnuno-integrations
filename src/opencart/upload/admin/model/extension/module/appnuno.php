<?php
class ModelExtensionModuleAppnuno extends Model {
	public function defaultConfig() {
		return json_encode(array(
			'cartTabId' => 'cart',
			'defaultTabs' => array(),
			'routes' => (object)array(),
			'back' => array(
				'enabled' => true,
				'placement' => 'separate'
			),
			'glass' => array(
				'enabled' => true,
				'colorScheme' => 'auto',
				'style' => 'regular',
				'animate' => true,
				'animationDuration' => 0.3,
				'isInteractive' => true,
				'tintColor' => 'rgba(0, 0, 0, 0.02)'
			),
			'colors' => array(
				'light' => array(
					'backgroundColor' => '#FFFFFF',
					'contentColor' => '#2C3136'
				),
				'dark' => array(
					'backgroundColor' => '#2C3136',
					'contentColor' => '#FFFFFF'
				)
			)
		), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	}

	public function getDestinations($language_id, $store_id) {
		$destinations = array(
			array(
				'label' => 'Home',
				'url' => '/'
			),
			array(
				'label' => 'Special offers',
				'url' => '/index.php?route=product/special'
			),
			array(
				'label' => 'Search',
				'url' => '/index.php?route=product/search'
			),
			array(
				'label' => 'Account',
				'url' => '/index.php?route=account/account'
			),
			array(
				'label' => 'Login',
				'url' => '/index.php?route=account/login'
			),
			array(
				'label' => 'Register',
				'url' => '/index.php?route=account/register'
			),
			array(
				'label' => 'Cart',
				'url' => '/index.php?route=checkout/cart'
			),
			array(
				'label' => 'Checkout',
				'url' => '/index.php?route=checkout/checkout'
			),
			array(
				'label' => 'Contact',
				'url' => '/index.php?route=information/contact'
			)
		);

		$information = $this->db->query("SELECT i.information_id, id.title FROM " . DB_PREFIX . "information i INNER JOIN " . DB_PREFIX . "information_description id ON (i.information_id = id.information_id) INNER JOIN " . DB_PREFIX . "information_to_store i2s ON (i.information_id = i2s.information_id) WHERE id.language_id = '" . (int)$language_id . "' AND i2s.store_id = '" . (int)$store_id . "' AND i.status = '1' ORDER BY id.title ASC");

		foreach ($information->rows as $page) {
			$destinations[] = array(
				'label' => 'Information: ' . $page['title'],
				'url' => '/index.php?route=information/information&information_id=' . (int)$page['information_id']
			);
		}

		$categories = $this->db->query("SELECT c.category_id, cd.name, (SELECT GROUP_CONCAT(cp.path_id ORDER BY cp.level SEPARATOR '_') FROM " . DB_PREFIX . "category_path cp WHERE cp.category_id = c.category_id) AS category_path FROM " . DB_PREFIX . "category c INNER JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id) INNER JOIN " . DB_PREFIX . "category_to_store c2s ON (c.category_id = c2s.category_id) WHERE cd.language_id = '" . (int)$language_id . "' AND c2s.store_id = '" . (int)$store_id . "' AND c.status = '1' ORDER BY cd.name ASC");

		foreach ($categories->rows as $category) {
			$path = isset($category['category_path']) && strlen($category['category_path']) ? $category['category_path'] : (int)$category['category_id'];

			$destinations[] = array(
				'label' => 'Category: ' . $category['name'],
				'url' => '/index.php?route=product/category&path=' . $path
			);
		}

		return $destinations;
	}

	public function validateConfig($json, $language_codes = array()) {
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

		$error = $this->validateTabs($config['defaultTabs'], 'defaultTabs', $language_codes);

		if (strlen($error)) {
			return $error;
		}

		foreach ($config['routes'] as $route => $tabs) {
			if (!$this->validRoute($route)) {
				return $this->reject('routes.' . $route . '.route', 'Each route must be a valid OpenCart route such as checkout/cart.');
			}

			$error = $this->validateTabs($tabs, 'routes.' . $route, $language_codes);

			if (strlen($error)) {
				return $error;
			}
		}

		if (isset($config['cartTabId']) && !$this->validOptionalTabId($config['cartTabId'])) {
			return $this->reject('cartTabId', 'Choose a valid cart badge tab.');
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

	private function validateTabs($tabs, $path, $language_codes) {
		if (!is_array($tabs) || count($tabs) > 8) {
			return $this->reject($path, 'A tab set must contain no more than 8 tabs.');
		}

		$ids = array();

		foreach ($tabs as $index => $tab) {
			$tab_path = $path . '[' . $index . ']';
			$error = $this->tabError($tab, $tab_path, $ids, $language_codes);

			if (strlen($error)) {
				return $error;
			}

			$ids[] = $tab['id'];
		}

		return '';
	}

	private function tabError($tab, $tab_path, $ids, $language_codes) {
		if (!is_array($tab) || !isset($tab['id']) || !$this->validTabId($tab['id'])) {
			return $this->reject($tab_path . '.id', 'Enter a valid, unique tab ID.');
		}

		if (in_array($tab['id'], $ids, true)) {
			return $this->reject($tab_path . '.id', 'Tab IDs must be unique within each tab set.');
		}

		$url_error = $this->tabUrlError($tab, $tab_path, $language_codes);

		if (strlen($url_error)) {
			return $url_error;
		}

		if (!$this->validTabLabels($tab)) {
			return $this->reject($tab_path . '.labels', 'Enter a label for each enabled store language.');
		}

		if (isset($tab['icon']) && !$this->validIcon($tab['icon'])) {
			return $this->reject($tab_path . '.icon', 'Choose an icon from the AppNuno catalog.');
		}

		if (isset($tab['active']) && !is_bool($tab['active'])) {
			return $this->reject($tab_path . '.active', 'The selected state must be true or false.');
		}

		if (array_key_exists('visible', $tab) && !is_bool($tab['visible'])) {
			return $this->reject($tab_path . '.visible', 'The visibility state must be true or false.');
		}

		if (isset($tab['badge']) && (!is_int($tab['badge']) || !is_int(filter_var($tab['badge'], FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)))))) {
			return $this->reject($tab_path . '.badge', 'The badge count must be zero or a positive integer.');
		}

		if (array_key_exists('allPages', $tab) && !is_bool($tab['allPages'])) {
			return $this->reject($tab_path . '.allPages', 'Choose whether the tab appears on every page.');
		}

		if (isset($tab['pages'])) {
			if (!is_array($tab['pages']) || count($tab['pages']) > 30) {
				return $this->reject($tab_path . '.pages', 'Choose no more than 30 store pages.');
			}

			foreach ($tab['pages'] as $page_route) {
				if (!$this->validRoute($page_route)) {
					return $this->reject($tab_path . '.pages', 'Choose a valid store page.');
				}
			}
		}

		return '';
	}

	private function tabUrlError($tab, $tab_path, $language_codes) {
		if (isset($tab['url']) && !$this->validLocalUrl($tab['url'])) {
			return $this->reject($tab_path . '.url', 'Choose a store page or enter a local URL, for example /home.');
		}

		if (isset($tab['urls'])) {
			if (!is_array($tab['urls']) || !count($tab['urls']) || count($tab['urls']) > 20) {
				return $this->reject($tab_path . '.urls', 'Add at least one localized URL.');
			}

			foreach ($tab['urls'] as $language_code => $url) {
				if (!$this->validLanguageCode($language_code)) {
					return $this->reject($tab_path . '.urls', 'Use a valid OpenCart language code.');
				}

				if (!$this->validLocalUrl($url)) {
					return $this->reject($tab_path . '.urls.' . $language_code, 'Enter a local URL, for example /home.');
				}
			}

			foreach ($language_codes as $language_code) {
				if (!isset($tab['urls'][$language_code])) {
					return $this->reject($tab_path . '.urls.' . $language_code, 'Enter a local URL for this language, even if the language is disabled.');
				}
			}

			return '';
		}

		if (!isset($tab['url'])) {
			return $this->reject($tab_path . '.url', 'Choose a store page or enter a local URL, for example /home.');
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

	public function normalizeConfig($json) {
		$config = json_decode($json, true);

		if (!is_array($config)) {
			return $json;
		}

		if (isset($config['defaultTabs']) && is_array($config['defaultTabs'])) {
			$config['defaultTabs'] = $this->normalizeTabs($config['defaultTabs']);
		}

		if (isset($config['routes']) && is_array($config['routes'])) {
			foreach ($config['routes'] as $route => $tabs) {
				if (is_array($tabs)) {
					$config['routes'][$route] = $this->normalizeTabs($tabs);
				}
			}
		}

		$encoded = json_encode($config);

		return is_string($encoded) ? $encoded : $json;
	}

	private function normalizeTabs($tabs) {
		foreach ($tabs as $index => $tab) {
			if (!is_array($tab)) {
				continue;
			}

			if (isset($tab['url'])) {
				$tab['url'] = $this->storedLocalUrl($tab['url']);
			}

			if (isset($tab['urls']) && is_array($tab['urls'])) {
				foreach ($tab['urls'] as $code => $url) {
					$tab['urls'][$code] = $this->storedLocalUrl($url);
				}
			}

			$tabs[$index] = $tab;
		}

		return $tabs;
	}

	private function localUrl($url) {
		if (!is_string($url)) {
			return '';
		}

		$url = trim($url);

		if (!strlen($url) || preg_match('~[[:cntrl:]\\\\ ]~', $url)) {
			return '';
		}

		if (preg_match('#^https?://#i', $url)) {
			$parts = parse_url($url);

			if (!is_array($parts) || !isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
				return '';
			}

			$path = isset($parts['path']) && is_string($parts['path']) && strlen($parts['path']) ? $parts['path'] : '/';

			if ($path[0] !== '/') {
				$path = '/' . $path;
			}

			$local = $path;

			if (isset($parts['query']) && strlen($parts['query'])) {
				$local .= '?' . $parts['query'];
			}

			if (isset($parts['fragment']) && strlen($parts['fragment'])) {
				$local .= '#' . $parts['fragment'];
			}

			return $local;
		}

		if (strpos($url, ':') !== false || strpos($url, '//') === 0) {
			return '';
		}

		if (preg_match('#^/(?!/)#', $url)) {
			return $url;
		}

		if (preg_match('#^[A-Za-z0-9]#', $url)) {
			return '/' . $url;
		}

		return '';
	}

	private function storedLocalUrl($url) {
		$local = $this->localUrl($url);

		return strlen($local) ? $local : $url;
	}

	private function validLocalUrl($url) {
		return strlen($this->localUrl($url)) > 0;
	}

	private function validTabLabels($tab) {
		if (isset($tab['labels'])) {
			if (!is_array($tab['labels']) || !count($tab['labels']) || count($tab['labels']) > 20) {
				return false;
			}

			foreach ($tab['labels'] as $language_code => $label) {
				if (!$this->validLanguageCode($language_code) || !$this->validLabel($label)) {
					return false;
				}
			}

			return true;
		}

		return $this->validLabel(isset($tab['label']) ? $tab['label'] : null);
	}

	private function reject($path, $message) {
		return $path . ': ' . $message;
	}

	private function validRoute($route) {
		return is_string($route) && preg_match('/^[A-Za-z0-9_\/.]+$/', $route);
	}

	private function validTabId($id) {
		return is_string($id) && preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id);
	}

	private function validOptionalTabId($id) {
		return is_string($id) && (!strlen($id) || $this->validTabId($id));
	}

	private function validLanguageCode($code) {
		return is_string($code) && (in_array($code, array('default'), true) || preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $code));
	}

	private function validLabel($label) {
		return is_string($label) && strlen(trim($label)) && strlen($label) <= 80;
	}

	private function validIcon($icon) {
		$asset_match = array();

		if (!is_array($icon) || !$this->validIconParts($icon)) {
			return false;
		}

		$icon_parts = parse_url($icon['uri']);

		return is_array($icon_parts) && isset($icon_parts['scheme'], $icon_parts['host'], $icon_parts['path']) && in_array($icon_parts['scheme'], array('https'), true) && in_array($icon_parts['host'], array('appnuno.com'), true) && !isset($icon_parts['user']) && !isset($icon_parts['pass']) && !isset($icon_parts['port']) && !isset($icon_parts['query']) && !isset($icon_parts['fragment']) && preg_match('#^/icon-assets/(duo|custom)/([a-z0-9]+(?:-[a-z0-9]+)*)/([a-f0-9]{64})\\.svg$#D', $icon_parts['path'], $asset_match) && !strcmp($icon['id'], $asset_match[1] . ':' . $asset_match[2]) && !strcmp($icon['content_hash'], $asset_match[3]);
	}

	private function validIconParts($icon) {
		return isset($icon['id'], $icon['uri'], $icon['content_hash']) && is_string($icon['id']) && is_string($icon['uri']) && is_string($icon['content_hash']) && preg_match('/^[a-f0-9]{64}$/', $icon['content_hash']) && filter_var($icon['uri'], FILTER_VALIDATE_URL);
	}
}
