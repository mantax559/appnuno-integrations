<?php
class ControllerExtensionModuleAppnuno extends Controller {
	// OpenCart 3.0.3+ passes the template source as a third view-event argument. PHP 8 rejects that call when the method only declares two parameters.
	public function inject(&$route, &$data, &$code = null) {
		if (!in_array($this->config->get('module_appnuno_status'), array('1', 1), true)) {
			return;
		}

		if (!isset($data['scripts']) || !is_array($data['scripts'])) {
			$data['scripts'] = array();
		}

		$page_route = isset($this->request->get['route']) && is_string($this->request->get['route']) ? $this->request->get['route'] : 'common/home';
		$script_url = html_entity_decode($this->url->link('extension/module/appnuno/bootstrap', $this->bootstrapArgs($page_route), true), ENT_QUOTES, 'UTF-8');

		if (!in_array($script_url, $data['scripts'], true)) {
			$data['scripts'][] = $script_url;
		}
	}

	public function bootstrap() {
		$this->response->addHeader('Content-Type: application/javascript; charset=utf-8');
		$this->response->addHeader('Cache-Control: no-store, private');
		$this->response->addHeader('X-Content-Type-Options: nosniff');

		if (!in_array($this->config->get('module_appnuno_status'), array('1', 1), true)) {
			$this->response->addHeader('HTTP/1.1 404 Not Found');
			$this->response->setOutput('');

			return;
		}

		$json = $this->getIntegrationJson();

		if (!$json) {
			$this->response->setOutput('');

			return;
		}

		$bootstrap = 'window.AppNuno=window.AppNuno||{};window.AppNunoIntegration=' . $json . ';window.AppNuno.navigation=window.AppNunoIntegration.navigation;if(window.AppNunoIntegration.state.identity){window.AppNuno.identity=window.AppNunoIntegration.state.identity;window.AppNuno.identities=[window.AppNunoIntegration.state.identity];}(function(){var s=document.createElement("script");s.src="https://appnuno.com/sdk/v1/appnuno.js";s.async=true;(document.head||document.documentElement).appendChild(s);}());';

		$this->response->setOutput($bootstrap);
	}

	public function state() {
		$this->response->addHeader('Content-Type: application/json; charset=utf-8');
		$this->response->addHeader('Cache-Control: no-store, private');
		$this->response->addHeader('X-Content-Type-Options: nosniff');

		if (!in_array($this->config->get('module_appnuno_status'), array('1', 1), true)) {
			$this->response->addHeader('HTTP/1.1 404 Not Found');
			$this->response->setOutput('{}');

			return;
		}

		$this->load->model('extension/module/appnuno');

		$this->response->setOutput(json_encode($this->model_extension_module_appnuno->getState(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT));
	}

	private function getIntegrationJson() {
		$this->load->model('extension/module/appnuno');

		$config_json = $this->config->get('module_appnuno_config');

		if (!is_string($config_json) || strlen($config_json) > 65535) {
			return '';
		}

		$config = json_decode($config_json, true);

		if (!is_array($config) || json_last_error() || !isset($config['defaultTabs']) || !is_array($config['defaultTabs']) || !isset($config['routes']) || !is_array($config['routes'])) {
			return '';
		}

		$state = $this->model_extension_module_appnuno->getState();
		$route = isset($this->request->get['page_route']) && is_string($this->request->get['page_route']) ? $this->request->get['page_route'] : 'common/home';
		$tabs = $this->tabsForRoute($config, $route);
		$cart_tab_id = isset($config['cartTabId']) ? $config['cartTabId'] : 'cart';
		$public_config = $config;
		$public_config['defaultTabs'] = $this->visibleTabs($config['defaultTabs']);
		foreach ($config['routes'] as $route_key => $route_tabs) {
			$public_config['routes'][$route_key] = $this->visibleTabs($route_tabs);
		}

		foreach ($tabs as &$tab) {
			if (isset($tab['urls']) && is_array($tab['urls']) && count($tab['urls'])) {
				$language_code = $this->languageCode();

				if (isset($tab['urls'][$language_code])) {
					$tab['url'] = $tab['urls'][$language_code];
				} elseif (!isset($tab['url'])) {
					$tab['url'] = reset($tab['urls']);
				}

				unset($tab['urls']);
			}

			if (isset($tab['pages'])) {
				unset($tab['pages']);
			}

			if (isset($tab['labels']) && is_array($tab['labels'])) {
				$language_code = $this->languageCode();

				if (isset($tab['labels'][$language_code])) {
					$tab['label'] = $tab['labels'][$language_code];
				} elseif (count($tab['labels'])) {
					$tab['label'] = reset($tab['labels']);
				}

				unset($tab['labels']);
			}

			if (isset($tab['id']) && in_array($tab['id'], array($cart_tab_id), true) && isset($state['cartCount']) && is_numeric($state['cartCount'])) {
				$tab['badge'] = (int)$state['cartCount'];
			}
		}

		unset($tab);
		$tabs = $this->markActiveTab($tabs, $route);

		$navigation = array('tabs' => $tabs);

		foreach (array('back', 'glass', 'colors') as $key) {
			if (isset($config[$key]) && is_array($config[$key])) {
				$navigation[$key] = $config[$key];
			}
		}

		$integration = array(
			'config' => $public_config,
			'route' => $route,
			'stateUrl' => html_entity_decode($this->url->link('extension/module/appnuno/state', '', true), ENT_QUOTES, 'UTF-8'),
			'state' => $state,
			'navigation' => $navigation
		);

		return json_encode($integration, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
	}

	private function tabsForRoute($config, $route) {
		if (isset($config['routes'][$route]) && is_array($config['routes'][$route]) && count($config['routes'][$route])) {
			return $this->visibleTabs($config['routes'][$route]);
		}

		$tabs = array();

		foreach ($config['defaultTabs'] as $tab) {
			if (!is_array($tab)) {
				continue;
			}

			if ($this->tabVisible($tab) && $this->tabShownOnRoute($tab, $route)) {
				$tabs[] = $tab;
			}
		}

		return $tabs;
	}

	private function visibleTabs($tabs) {
		if (!is_array($tabs)) {
			return array();
		}

		$visible_tabs = array();

		foreach ($tabs as $tab) {
			if (is_array($tab) && $this->tabVisible($tab)) {
				$visible_tabs[] = $tab;
			}
		}

		return $visible_tabs;
	}

	private function tabVisible($tab) {
		return !array_key_exists('visible', $tab) || filter_var($tab['visible'], FILTER_VALIDATE_BOOLEAN);
	}

	private function tabShownOnRoute($tab, $route) {
		if (!isset($tab['pages']) || !is_array($tab['pages']) || !count($tab['pages'])) {
			return true;
		}

		return in_array($route, $tab['pages'], true);
	}

	private function bootstrapArgs($page_route) {
		$page = isset($this->request->server['REQUEST_URI']) && is_string($this->request->server['REQUEST_URI']) ? $this->request->server['REQUEST_URI'] : '/';
		$args = 'page_route=' . rawurlencode($page_route) . '&page=' . rawurlencode($page);

		foreach (array('path', 'product_id', 'information_id', 'manufacturer_id') as $key) {
			if (isset($this->request->get[$key]) && is_string($this->request->get[$key])) {
				$args .= '&' . $key . '=' . rawurlencode($this->request->get[$key]);
			}
		}

		return $args;
	}

	private function markActiveTab($tabs, $route) {
		$page = isset($this->request->get['page']) && is_string($this->request->get['page']) ? $this->request->get['page'] : '';
		$request_path = '/';
		$parsed_path = $page !== '' ? parse_url($page, PHP_URL_PATH) : '/';

		if (is_string($parsed_path) && $parsed_path !== '') {
			$request_path = $parsed_path;
		}

		if (strlen($request_path) > 1 && substr($request_path, -1) === '/') {
			$request_path = substr($request_path, 0, -1);
		}

		$best_index = -1;
		$best_score = 0;

		foreach ($tabs as $index => $tab) {
			$score = $this->tabActiveScore($tab, $route, $request_path);

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

	private function tabActiveScore($tab, $route, $request_path) {
		if (!isset($tab['url']) || !is_string($tab['url']) || $tab['url'] === '') {
			return 0;
		}

		$parts = parse_url($tab['url']);

		if ($parts === false) {
			return 0;
		}

		$path = isset($parts['path']) && is_string($parts['path']) && $parts['path'] !== '' ? $parts['path'] : '/';

		if (strlen($path) > 1 && substr($path, -1) === '/') {
			$path = substr($path, 0, -1);
		}

		$query = array();

		if (isset($parts['query']) && is_string($parts['query'])) {
			parse_str($parts['query'], $query);
		}

		$detail_count = 0;

		foreach ($query as $key => $value) {
			if ($key !== 'route') {
				$detail_count++;
			}
		}

		$route_matches = isset($query['route']) && is_string($query['route']) && $query['route'] === $route;
		$details_match = $this->queryDetailsMatch($query);

		if ($path === $request_path) {
			if (isset($query['route'])) {
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

	private function queryDetailsMatch($query) {
		foreach ($query as $key => $value) {
			if ($key === 'route') {
				continue;
			}

			if (!isset($this->request->get[$key]) || !is_string($this->request->get[$key]) || !is_string($value) || $this->request->get[$key] !== $value) {
				return false;
			}
		}

		return true;
	}

	private function languageCode() {
		if (isset($this->session->data['language']) && is_string($this->session->data['language']) && strlen($this->session->data['language'])) {
			return $this->session->data['language'];
		}

		$language_code = $this->config->get('config_language');

		return is_string($language_code) ? $language_code : '';
	}
}
