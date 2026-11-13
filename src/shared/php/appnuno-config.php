<?php
class AppnunoConfig {
	private static $translate = null;
	private static $routeLabel = null;
	private static $languageName = null;
	private static $activeLanguage = '';

	public static function defaultConfig() {
		return json_encode(array(
			'cartTabId' => 'cart',
			'badgeSources' => (object)array(),
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

	public static function validateConfig($json, $language_codes = array()) {
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

		$error = self::validateTabs($config['defaultTabs'], 'defaultTabs', $language_codes);

		if (strlen($error)) {
			return $error;
		}

		foreach ($config['routes'] as $route => $tabs) {
			if (!self::validRoute($route)) {
				return self::reject('routes.' . $route . '.route', 'Each route must be a valid OpenCart route such as checkout/cart.');
			}

			$error = self::validateTabs($tabs, 'routes.' . $route, $language_codes);

			if (strlen($error)) {
				return $error;
			}
		}

		if (array_key_exists('badgeSources', $config) && !is_array($config['badgeSources'])) {
			return self::reject('badgeSources', 'Choose a supported badge source for an existing tab.');
		}

		if (array_key_exists('badgeSources', $config)) {
			foreach ($config['badgeSources'] as $tab_id => $source) {
				$tab_id = (string)$tab_id;

				if (!self::validTabId($tab_id) || !in_array($source, array('cart'), true) || !self::configHasTabId($config, $tab_id)) {
					return self::reject('badgeSources', 'Choose a supported badge source for an existing tab.');
				}
			}
		}

		if (isset($config['cartTabId']) && !self::validOptionalTabId($config['cartTabId'])) {
			return self::reject('cartTabId', 'Choose a valid cart badge tab.');
		}

		if (isset($config['back']) && (!is_array($config['back']) || (isset($config['back']['placement']) && !in_array($config['back']['placement'], array('separate', 'bar'), true)) || (isset($config['back']['enabled']) && !is_bool($config['back']['enabled'])))) {
			return 'back: Choose a valid back-button configuration.';
		}

		$visual_error = self::visualError($config);

		if (strlen($visual_error)) {
			return $visual_error;
		}

		return '';
	}

	private static function validateTabs($tabs, $path, $language_codes) {
		if (!is_array($tabs) || count($tabs) > 8) {
			return self::reject($path, 'A tab set must contain no more than 8 tabs.');
		}

		$ids = array();

		foreach ($tabs as $index => $tab) {
			$tab_path = $path . '[' . $index . ']';
			$error = self::tabError($tab, $tab_path, $ids, $language_codes);

			if (strlen($error)) {
				return $error;
			}

			$ids[] = $tab['id'];
		}

		return '';
	}

	private static function tabError($tab, $tab_path, $ids, $language_codes) {
		if (!is_array($tab) || !isset($tab['id']) || !self::validTabId($tab['id'])) {
			return self::reject($tab_path . '.id', 'Enter a valid, unique tab ID.');
		}

		if (in_array($tab['id'], $ids, true)) {
			return self::reject($tab_path . '.id', 'Tab IDs must be unique within each tab set.');
		}

		$url_error = self::tabUrlError($tab, $tab_path, $language_codes);

		if (strlen($url_error)) {
			return $url_error;
		}

		if (!self::validTabLabels($tab)) {
			return self::reject($tab_path . '.labels', 'Enter a label for each enabled store language.');
		}

		if (isset($tab['icon']) && !self::validIcon($tab['icon'])) {
			return self::reject($tab_path . '.icon', 'Choose an icon from the AppNuno catalog.');
		}

		if (isset($tab['active']) && !is_bool($tab['active'])) {
			return self::reject($tab_path . '.active', 'The selected state must be true or false.');
		}

		if (array_key_exists('visible', $tab) && !is_bool($tab['visible'])) {
			return self::reject($tab_path . '.visible', 'The visibility state must be true or false.');
		}

		if (isset($tab['badge']) && (!is_int($tab['badge']) || !is_int(filter_var($tab['badge'], FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)))))) {
			return self::reject($tab_path . '.badge', 'The badge count must be zero or a positive integer.');
		}

		if (array_key_exists('allPages', $tab) && !is_bool($tab['allPages'])) {
			return self::reject($tab_path . '.allPages', 'Choose whether the tab appears on every page.');
		}

		if (isset($tab['pages'])) {
			if (!is_array($tab['pages']) || count($tab['pages']) > 30) {
				return self::reject($tab_path . '.pages', 'Choose no more than 30 store pages.');
			}

			foreach ($tab['pages'] as $page_route) {
				if (!self::validRoute($page_route)) {
					return self::reject($tab_path . '.pages', 'Choose a valid store page.');
				}
			}
		}

		return '';
	}

	private static function tabUrlError($tab, $tab_path, $language_codes) {
		if (isset($tab['url']) && !self::validLocalUrl($tab['url'])) {
			return self::reject($tab_path . '.url', 'Choose a store page or enter a local URL, for example /home.');
		}

		if (isset($tab['urls'])) {
			if (!is_array($tab['urls']) || !count($tab['urls']) || count($tab['urls']) > 20) {
				return self::reject($tab_path . '.urls', 'Add at least one localized URL.');
			}

			foreach ($tab['urls'] as $language_code => $url) {
				if (!self::validLanguageCode($language_code)) {
					return self::reject($tab_path . '.urls', 'Use a valid OpenCart language code.');
				}

				if (!self::validLocalUrl($url)) {
					return self::reject($tab_path . '.urls.' . $language_code, 'Enter a local URL, for example /home.');
				}
			}

			foreach ($language_codes as $language_code) {
				if (!isset($tab['urls'][$language_code])) {
					return self::reject($tab_path . '.urls.' . $language_code, 'Enter a local URL for this language, even if the language is disabled.');
				}
			}

			return '';
		}

		if (!isset($tab['url'])) {
			return self::reject($tab_path . '.url', 'Choose a store page or enter a local URL, for example /home.');
		}

		return '';
	}

	private static function visualError($config) {
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

	public static function normalizeConfig($json) {
		$config = json_decode($json, true);

		if (!is_array($config)) {
			return $json;
		}

		if (isset($config['defaultTabs']) && is_array($config['defaultTabs'])) {
			$config['defaultTabs'] = self::normalizeTabs($config['defaultTabs']);
		}

		if (isset($config['routes']) && is_array($config['routes'])) {
			foreach ($config['routes'] as $route => $tabs) {
				if (is_array($tabs)) {
					$config['routes'][$route] = self::normalizeTabs($tabs);
				}
			}
		}

		if (!array_key_exists('badgeSources', $config)) {
			$config['badgeSources'] = self::legacyBadgeSources($config);
		}

		$encoded = json_encode($config);

		return is_string($encoded) ? $encoded : $json;
	}

	private static function normalizeTabs($tabs) {
		foreach ($tabs as $index => $tab) {
			if (!is_array($tab)) {
				continue;
			}

			if (isset($tab['url'])) {
				$tab['url'] = self::storedLocalUrl($tab['url']);
			}

			if (isset($tab['urls']) && is_array($tab['urls'])) {
				foreach ($tab['urls'] as $code => $url) {
					$tab['urls'][$code] = self::storedLocalUrl($url);
				}
			}

			$tabs[$index] = $tab;
		}

		return $tabs;
	}

	private static function localUrl($url) {
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

	private static function storedLocalUrl($url) {
		$local = self::localUrl($url);

		return strlen($local) ? $local : $url;
	}

	private static function validLocalUrl($url) {
		return strlen(self::localUrl($url)) > 0;
	}

	private static function validTabLabels($tab) {
		if (isset($tab['labels'])) {
			if (!is_array($tab['labels']) || !count($tab['labels']) || count($tab['labels']) > 20) {
				return false;
			}

			foreach ($tab['labels'] as $language_code => $label) {
				if (!self::validLanguageCode($language_code) || !self::validLabel($label)) {
					return false;
				}
			}

			return true;
		}

		return self::validLabel(isset($tab['label']) ? $tab['label'] : null);
	}

	private static function reject($path, $message) {
		return $path . ': ' . $message;
	}

	private static function validRoute($route) {
		return is_string($route) && preg_match('/^[A-Za-z0-9_\/.]+$/', $route);
	}

	private static function validTabId($id) {
		return is_string($id) && preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id);
	}

	private static function validOptionalTabId($id) {
		return is_string($id) && (!strlen($id) || self::validTabId($id));
	}

	private static function validLanguageCode($code) {
		return is_string($code) && (in_array($code, array('default'), true) || preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $code));
	}

	private static function validLabel($label) {
		return is_string($label) && strlen(trim($label)) && strlen($label) <= 80;
	}

	private static function validIcon($icon) {
		$asset_match = array();

		if (!is_array($icon) || !self::validIconParts($icon)) {
			return false;
		}

		$icon_parts = parse_url($icon['uri']);

		return is_array($icon_parts) && isset($icon_parts['scheme'], $icon_parts['host'], $icon_parts['path']) && in_array($icon_parts['scheme'], array('https'), true) && in_array($icon_parts['host'], array('appnuno.com'), true) && !isset($icon_parts['user']) && !isset($icon_parts['pass']) && !isset($icon_parts['port']) && !isset($icon_parts['query']) && !isset($icon_parts['fragment']) && preg_match('#^/icon-assets/(duo|custom)/([a-z0-9]+(?:-[a-z0-9]+)*)/([a-f0-9]{64})\\.svg$#D', $icon_parts['path'], $asset_match) && !strcmp($icon['id'], $asset_match[1] . ':' . $asset_match[2]) && !strcmp($icon['content_hash'], $asset_match[3]);
	}

	private static function validIconParts($icon) {
		return isset($icon['id'], $icon['uri'], $icon['content_hash']) && is_string($icon['id']) && is_string($icon['uri']) && is_string($icon['content_hash']) && preg_match('/^[a-f0-9]{64}$/', $icon['content_hash']) && filter_var($icon['uri'], FILTER_VALIDATE_URL);
	}

	public static function integration($config, $route, $state, $language_code, $page, $query) {
		if (!is_array($config) || !isset($config['defaultTabs']) || !is_array($config['defaultTabs']) || !isset($config['routes']) || !is_array($config['routes'])) {
			return null;
		}

		$locale = self::normalizeLocale(is_string($language_code) ? $language_code : '');

		if (!is_array($query)) {
			$query = array();
		}

		$tabs = self::tabsForRoute($config, $route);
		$public_config = $config;
		$badge_sources = self::resolvedBadgeSources($config);
		$public_config['badgeSources'] = $badge_sources;
		$public_config['defaultTabs'] = self::visibleTabs($config['defaultTabs']);

		foreach ($config['routes'] as $route_key => $route_tabs) {
			$public_config['routes'][$route_key] = self::visibleTabs($route_tabs);
		}

		foreach ($tabs as $index => $tab) {
			self::applyStorefrontTab($tabs[$index], $badge_sources, $state, $locale);
		}

		$navigation = array('tabs' => self::markActiveTab($tabs, $route, $page, $query));

		foreach (array('back', 'glass', 'colors') as $key) {
			if (isset($config[$key]) && is_array($config[$key])) {
				$navigation[$key] = $config[$key];
			}
		}

		return array(
			'config' => $public_config,
			'route' => $route,
			'locale' => $locale,
			'state' => $state,
			'navigation' => $navigation
		);
	}

	public static function normalizeLocale($locale) {
		$code = strtolower(str_replace('_', '-', $locale));

		return preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/', $code) ? $code : 'en-gb';
	}

	public static function visibleTabs($tabs) {
		if (!is_array($tabs)) {
			return array();
		}

		$visible_tabs = array();

		foreach ($tabs as $tab) {
			if (is_array($tab) && self::tabVisible($tab)) {
				if (!self::publishableIcon(isset($tab['icon']) ? $tab['icon'] : null)) {
					unset($tab['icon']);
				}

				unset($tab['activeIcon']);
				$visible_tabs[] = $tab;
			}
		}

		return $visible_tabs;
	}

	public static function tabShownOnRoute($tab, $route) {
		if (array_key_exists('allPages', $tab) && is_bool($tab['allPages'])) {
			if ($tab['allPages']) {
				return true;
			}

			return isset($tab['pages']) && is_array($tab['pages']) && in_array($route, $tab['pages'], true);
		}

		if (!isset($tab['pages']) || !is_array($tab['pages']) || !count($tab['pages'])) {
			return true;
		}

		return in_array($route, $tab['pages'], true);
	}

	public static function alert($modifier, $icon, $text, $close_label) {
		if (!is_string($text) || !strlen($text)) {
			return '';
		}

		return '<div class="appnuno-alert appnuno-alert-' . $modifier . '"><span class="appnuno-alert-icon" aria-hidden="true"><span class="appnuno-icon appnuno-icon-' . $icon . '"></span></span><span class="appnuno-alert-text">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span><button type="button" class="appnuno-alert-close" data-appnuno-dismiss="alert" aria-label="' . htmlspecialchars(is_string($close_label) ? $close_label : '', ENT_QUOTES, 'UTF-8') . '"><span class="appnuno-icon appnuno-icon-trash" aria-hidden="true"></span></button></div>';
	}

	public static function renderSettings($html, $replacements) {
		if (!is_string($html)) {
			return '';
		}

		foreach ($replacements as $key => $value) {
			$html = str_replace('{{' . $key . '}}', is_string($value) ? $value : '', $html);
		}

		return $html;
	}

	public static function readableConfigError($config_error, $posted_config, $translate, $route_label, $language_name, $language_code) {
		self::$translate = $translate;
		self::$routeLabel = $route_label;
		self::$languageName = $language_name;
		self::$activeLanguage = is_string($language_code) ? $language_code : '';
		$result = self::readableConfigErrorBody($config_error, $posted_config);
		self::$translate = null;
		self::$routeLabel = null;
		self::$languageName = null;
		self::$activeLanguage = '';

		return $result;
	}

	private static function tabsForRoute($config, $route) {
		if (isset($config['routes'][$route]) && is_array($config['routes'][$route]) && count($config['routes'][$route])) {
			return self::visibleTabs($config['routes'][$route]);
		}

		$tabs = array();

		foreach ($config['defaultTabs'] as $tab) {
			if (!is_array($tab)) {
				continue;
			}

			if (self::tabVisible($tab) && self::tabShownOnRoute($tab, $route)) {
				$tabs[] = $tab;
			}
		}

		return $tabs;
	}

	private static function tabVisible($tab) {
		return !array_key_exists('visible', $tab) || filter_var($tab['visible'], FILTER_VALIDATE_BOOLEAN);
	}

	private static function markActiveTab($tabs, $route, $page, $query) {
		$page = is_string($page) ? $page : '';
		$request_path = self::normalizedPath($page !== '' ? parse_url($page, PHP_URL_PATH) : '/');
		$best_index = -1;
		$best_score = 0;

		foreach ($tabs as $index => $tab) {
			$score = self::tabActiveScore($tab, $route, $request_path, $query);

			if ($score > $best_score) {
				$best_score = $score;
				$best_index = $index;
			}
		}

		foreach ($tabs as $index => &$tab) {
			$tab['active'] = $best_index === $index;
		}

		unset($tab);

		return $tabs;
	}

	private static function tabActiveScore($tab, $route, $request_path, $query) {
		if (!isset($tab['url']) || !is_string($tab['url']) || $tab['url'] === '') {
			return 0;
		}

		$parts = parse_url($tab['url']);

		if ($parts === false) {
			return 0;
		}

		$path = self::normalizedPath(isset($parts['path']) ? $parts['path'] : '/');
		$tab_query = array();

		if (isset($parts['query']) && is_string($parts['query'])) {
			parse_str($parts['query'], $tab_query);
		}

		$detail_count = 0;

		foreach ($tab_query as $key => $value) {
			if ($key !== 'route') {
				$detail_count++;
			}
		}

		$route_matches = isset($tab_query['route']) && is_string($tab_query['route']) && $tab_query['route'] === $route;
		$details_match = self::queryDetailsMatch($tab_query, $query);

		if ($path === $request_path) {
			if (isset($tab_query['route'])) {
				if ($route_matches && $details_match) {
					return 100 + $detail_count;
				}

				return 0;
			}

			return 90;
		}

		if ($route_matches && $details_match) {
			return 70 + $detail_count;
		}

		if ($path === '/' && $route === 'common/home') {
			return 80;
		}

		return 0;
	}

	private static function queryDetailsMatch($tab_query, $query) {
		foreach ($tab_query as $key => $value) {
			if ($key === 'route') {
				continue;
			}

			if (!isset($query[$key]) || !is_string($query[$key]) || !is_string($value) || $query[$key] !== $value) {
				return false;
			}
		}

		return true;
	}

	private static function applyStorefrontTab(&$tab, $badge_sources, $state, $language_code) {
		self::applyLocalizedUrl($tab, $language_code);
		unset($tab['allPages'], $tab['pages']);
		self::applyLocalizedLabel($tab, $language_code);

		if (!isset($tab['id']) || !isset($badge_sources[$tab['id']])) {
			return;
		}

		$source = $badge_sources[$tab['id']];
		if (!in_array($source, array('cart'), true)) {
			return;
		}

		$counts = isset($state['badgeCounts']) && is_array($state['badgeCounts']) ? $state['badgeCounts'] : array();

		if (isset($counts[$source]) && is_numeric($counts[$source])) {
			$tab['badge'] = (int)$counts[$source];
		} elseif (in_array($source, array('cart'), true) && isset($state['cartCount']) && is_numeric($state['cartCount'])) {
			$tab['badge'] = (int)$state['cartCount'];
		}
	}

	private static function resolvedBadgeSources($config) {
		if (array_key_exists('badgeSources', $config) && is_array($config['badgeSources'])) {
			return $config['badgeSources'];
		}

		return self::legacyBadgeSources($config);
	}

	private static function legacyBadgeSources($config) {
		$tab_id = isset($config['cartTabId']) ? $config['cartTabId'] : 'cart';

		return is_string($tab_id) && strlen($tab_id) ? array($tab_id => 'cart') : array();
	}

	private static function configHasTabId($config, $tab_id) {
		foreach ($config['defaultTabs'] as $tab) {
			if (isset($tab['id']) && in_array($tab['id'], array($tab_id), true)) {
				return true;
			}
		}

		foreach ($config['routes'] as $tabs) {
			foreach ($tabs as $tab) {
				if (isset($tab['id']) && in_array($tab['id'], array($tab_id), true)) {
					return true;
				}
			}
		}

		return false;
	}

	private static function applyLocalizedUrl(&$tab, $language_code) {
		if (!isset($tab['urls']) || !is_array($tab['urls']) || !count($tab['urls'])) {
			return;
		}

		if (isset($tab['urls'][$language_code])) {
			$tab['url'] = $tab['urls'][$language_code];
		} elseif (!isset($tab['url'])) {
			$tab['url'] = reset($tab['urls']);
		}

		unset($tab['urls']);
	}

	private static function applyLocalizedLabel(&$tab, $language_code) {
		if (!isset($tab['labels']) || !is_array($tab['labels'])) {
			return;
		}

		if (isset($tab['labels'][$language_code])) {
			$tab['label'] = $tab['labels'][$language_code];
		} elseif (count($tab['labels'])) {
			$tab['label'] = reset($tab['labels']);
		}

		unset($tab['labels']);
	}

	private static function publishableIcon($icon) {
		return is_array($icon) && isset($icon['id'], $icon['uri']) && is_string($icon['id']) && is_string($icon['uri']);
	}

	private static function normalizedPath($path) {
		if (!is_string($path) || $path === '') {
			$path = '/';
		}

		if (strlen($path) > 1 && substr($path, -1) === '/') {
			return substr($path, 0, -1);
		}

		return $path;
	}

	private static function readableConfigErrorBody($config_error, $posted_config) {
		$separator = strrpos($config_error, ': ');

		if (!is_int($separator)) {
			return $config_error;
		}

		$message = substr($config_error, $separator + 2);
		$key = self::configErrorKey($message);
		$sentence = strlen($key) ? self::text($key) : $message;

		if (!strlen($sentence) || $sentence === $key) {
			$sentence = $message;
		}

		if (!strlen($key)) {
			$place = self::readableConfigPlace(substr($config_error, 0, $separator), $posted_config);

			return strlen($place) ? $place . ': ' . $sentence : $sentence;
		}

		return self::composeConfigError($key, substr($config_error, 0, $separator), $posted_config);
	}

	private static function composeConfigError($key, $path, $posted_config) {
		if ($key === 'error_glass_setting') {
			$choices = array(
				'glass.enabled' => 'error_glass_enabled_choice',
				'glass.animate' => 'error_glass_animate_choice',
				'glass.isInteractive' => 'error_glass_interactive_choice'
			);

			if (isset($choices[$path])) {
				$key = $choices[$path];
			}
		}

		$context = self::errorContext($path, $posted_config);
		$template_key = $key;

		if (strlen($context['page']) && self::text($key . '_page') !== $key . '_page') {
			$template_key = $key . '_page';
		}

		$sentence = self::text($template_key);

		if (!strlen($sentence) || $sentence === $template_key) {
			$sentence = self::text($key);
		}

		return strtr($sentence, array(
			'{tab}' => $context['tab'],
			'{page}' => $context['page'],
			'{language}' => $context['language'],
			'{mode}' => $context['mode'],
			'{field}' => $context['field']
		));
	}

	private static function errorContext($path, $posted_config) {
		$config = json_decode($posted_config, true);

		if (!is_array($config)) {
			$config = array();
		}

		$context = array('tab' => '', 'page' => '', 'language' => '', 'mode' => '', 'field' => '');

		if (preg_match('/^(defaultTabs|routes\.[^\[]+)\[(\d+)\](?:\.(.*))?$/', $path, $match)) {
			if (strpos($match[1], 'routes.') === 0) {
				$context['page'] = self::namedRoute(substr($match[1], 7));
			}

			$context['tab'] = self::tabTitle(self::tabsForPath($config, $match[1]), (int)$match[2]);
			$field = isset($match[3]) ? $match[3] : '';

			if (strpos($field, 'urls.') === 0) {
				$context['language'] = self::namedLanguage(substr($field, 5));
			}
		} elseif (preg_match('/^routes\.(.+)\.route$/', $path, $match)) {
			$context['page'] = $match[1];
		} elseif (preg_match('/^routes\.(.+)$/', $path, $match)) {
			$context['page'] = self::namedRoute($match[1]);
		} elseif (preg_match('/^colors\.(light|dark)(?:\.(backgroundColor|contentColor))?$/', $path, $match)) {
			$context['mode'] = self::colorModeLabel($match[1]);

			if (isset($match[2]) && strlen($match[2])) {
				$context['field'] = self::colorFieldLabel($match[2]);
			}
		}

		return $context;
	}

	private static function configErrorKey($message) {
		$keys = array(
			'Configuration must be valid JSON smaller than 64 KB.' => 'error_json',
			'Configuration is missing its tab sets. Submit the settings page again.' => 'error_config_shape',
			'A maximum of 30 route-specific tab sets is allowed.' => 'error_routes_limit',
			'Each route must be a valid OpenCart route such as checkout/cart.' => 'error_route',
			'A tab set must contain no more than 8 tabs.' => 'error_tab_limit',
			'Enter a valid, unique tab ID.' => 'error_tab_id',
			'Tab IDs must be unique within each tab set.' => 'error_tab_id_unique',
			'Choose a store page or enter a local URL, for example /home.' => 'error_tab_url',
			'Add at least one localized URL.' => 'error_tab_urls',
			'Use a valid OpenCart language code.' => 'error_tab_url_language',
			'Enter a local URL, for example /home.' => 'error_tab_url_local',
			'Enter a local URL for this language, even if the language is disabled.' => 'error_tab_url_required',
			'Enter a label for each enabled store language.' => 'error_tab_labels',
			'Choose an icon from the AppNuno catalog.' => 'error_tab_icon',
			'The selected state must be true or false.' => 'error_tab_active',
			'The visibility state must be true or false.' => 'error_tab_visible',
			'The badge count must be zero or a positive integer.' => 'error_tab_badge',
			'Choose whether the tab appears on every page.' => 'error_tab_all_pages',
			'Choose no more than 30 store pages.' => 'error_tab_pages_limit',
			'Choose a valid store page.' => 'error_tab_pages',
			'Choose a valid cart badge tab.' => 'error_cart_tab',
			'Choose a valid back-button configuration.' => 'error_back',
			'Glass settings must be an object.' => 'error_glass',
			'Choose a valid glass setting.' => 'error_glass_setting',
			'Choose Automatic, Light or Dark.' => 'error_glass_color_scheme',
			'Choose a valid glass style.' => 'error_glass_style',
			'Enter a duration from 0 to 10 seconds.' => 'error_glass_duration',
			'Enter a valid tint color and opacity.' => 'error_glass_tint',
			'Navigation colors must be an object.' => 'error_colors',
			'Navigation color values must be an object.' => 'error_color_scheme',
			'Choose a valid six-digit color.' => 'error_color'
		);

		return isset($keys[$message]) ? $keys[$message] : '';
	}

	private static function readableConfigPlace($path, $posted_config) {
		$config = json_decode($posted_config, true);

		if (!is_array($config)) {
			$config = array();
		}

		if ($path === 'module_appnuno_config') {
			return '';
		}

		if ($path === 'cartTabId') {
			return self::text('entry_cart_tab');
		}

		if ($path === 'back') {
			return self::text('text_back_button');
		}

		if ($path === 'glass') {
			return self::text('text_glass');
		}

		if ($path === 'colors') {
			return self::text('text_navigation_colors');
		}

		if ($path === 'defaultTabs') {
			return self::text('text_default_tabs');
		}

		if ($path === 'routes') {
			return self::text('text_shown_on_pages');
		}

		if (preg_match('/^glass\.(enabled|animate|isInteractive|colorScheme|style|animationDuration|tintColor|contentColor)$/', $path, $match)) {
			$fields = array(
				'enabled' => 'entry_glass_enabled',
				'animate' => 'entry_glass_animate',
				'isInteractive' => 'entry_glass_interactive',
				'colorScheme' => 'entry_glass_color_scheme',
				'style' => 'entry_glass_style',
				'animationDuration' => 'entry_glass_duration',
				'tintColor' => 'entry_glass_tint',
				'contentColor' => 'entry_content'
			);

			return self::joinPlace(array(self::text('text_glass'), self::text($fields[$match[1]])));
		}

		if (preg_match('/^colors\.(light|dark)$/', $path, $match)) {
			return self::colorModeLabel($match[1]);
		}

		if (preg_match('/^colors\.(light|dark)\.(backgroundColor|contentColor)$/', $path, $match)) {
			return self::joinPlace(array(self::colorModeLabel($match[1]), self::colorFieldLabel($match[2])));
		}

		if (preg_match('/^routes\.(.+)\.route$/', $path, $match)) {
			return self::joinPlace(array(self::text('text_shown_on_pages'), self::namedRoute($match[1])));
		}

		if (preg_match('/^(defaultTabs|routes\.[^\[]+)\[(\d+)\](?:\.(.*))?$/', $path, $match)) {
			$tabs = self::tabsForPath($config, $match[1]);
			$parts = array(self::tabTitle($tabs, (int)$match[2]));
			$field = self::tabFieldLabel(isset($match[3]) ? $match[3] : '');

			if (strlen($field)) {
				$parts[] = $field;
			}

			if (strpos($match[1], 'routes.') === 0) {
				array_unshift($parts, self::namedRoute(substr($match[1], 7)));
			}

			return self::joinPlace($parts);
		}

		return '';
	}

	private static function tabsForPath($config, $collection) {
		if ($collection === 'defaultTabs') {
			return isset($config['defaultTabs']) && is_array($config['defaultTabs']) ? array_values($config['defaultTabs']) : array();
		}

		$route = substr($collection, 7);

		return isset($config['routes'][$route]) && is_array($config['routes'][$route]) ? array_values($config['routes'][$route]) : array();
	}

	private static function tabTitle($tabs, $index) {
		if (!isset($tabs[$index]) || !is_array($tabs[$index])) {
			return self::text('text_tab_list_empty');
		}

		return self::singleTabTitle($tabs[$index]);
	}

	private static function singleTabTitle($tab) {
		$language_code = self::$activeLanguage;

		if (isset($tab['labels']) && is_array($tab['labels'])) {
			if (strlen($language_code) && isset($tab['labels'][$language_code]) && is_string($tab['labels'][$language_code]) && strlen(trim($tab['labels'][$language_code]))) {
				return trim($tab['labels'][$language_code]);
			}

			foreach ($tab['labels'] as $label) {
				if (is_string($label) && strlen(trim($label))) {
					return trim($label);
				}
			}
		}

		if (isset($tab['label']) && is_string($tab['label']) && strlen(trim($tab['label']))) {
			return trim($tab['label']);
		}

		if (isset($tab['id']) && is_string($tab['id']) && preg_match('/^[A-Za-z0-9_-]{1,64}$/', $tab['id'])) {
			return trim($tab['id']);
		}

		return self::text('text_tab_list_empty');
	}

	private static function tabFieldLabel($field) {
		if ($field === 'id') {
			return self::text('text_tab_id');
		}

		if ($field === 'url' || $field === 'urls') {
			return self::text('text_destination');
		}

		if ($field === 'labels') {
			return self::text('text_language_labels');
		}

		if ($field === 'icon') {
			return self::text('text_icon');
		}

		if ($field === 'visible') {
			return self::text('text_tab_visible');
		}

		if ($field === 'badge') {
			return self::text('entry_cart_tab');
		}

		if ($field === 'allPages' || $field === 'pages') {
			return self::text('text_shown_on_pages');
		}

		if (strpos($field, 'urls.') === 0) {
			return self::joinPlace(array(self::namedLanguage(substr($field, 5)), self::text('text_custom_url')));
		}

		if (strpos($field, 'labels.') === 0) {
			return self::joinPlace(array(self::namedLanguage(substr($field, 7)), self::text('text_label')));
		}

		return '';
	}

	private static function colorModeLabel($mode) {
		return self::text($mode === 'light' ? 'text_light_mode' : 'text_dark_mode');
	}

	private static function colorFieldLabel($field) {
		return self::text($field === 'backgroundColor' ? 'entry_background' : 'entry_content');
	}

	private static function joinPlace($parts) {
		$labels = array();

		foreach ($parts as $part) {
			if (is_string($part) && strlen($part)) {
				$labels[] = $part;
			}
		}

		return implode(' > ', $labels);
	}

	private static function text($key) {
		$value = call_user_func(self::$translate, $key);

		return is_string($value) ? $value : '';
	}

	private static function namedRoute($route) {
		$value = call_user_func(self::$routeLabel, $route);

		return is_string($value) ? $value : $route;
	}

	private static function namedLanguage($code) {
		$value = call_user_func(self::$languageName, $code);

		return is_string($value) ? $value : $code;
	}
}
