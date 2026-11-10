<?php
if (!class_exists('AppnunoConfig', false)) {
	if (defined('DIR_SYSTEM') && is_file(DIR_SYSTEM . 'library/appnuno-config.php')) {
		require_once DIR_SYSTEM . 'library/appnuno-config.php';
	} else {
		require_once dirname(__FILE__) . '/../../../../../../shared/php/appnuno-config.php';
	}
}

class ControllerExtensionModuleAppnuno extends Controller {
	public function inject(&$route, &$data, &$code = null) {
		if (!$this->moduleEnabled()) {
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
		$this->responseHeaders('application/javascript; charset=utf-8');

		if (!$this->moduleEnabled()) {
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
		$this->responseHeaders('application/json; charset=utf-8');

		if (!$this->moduleEnabled()) {
			$this->response->addHeader('HTTP/1.1 404 Not Found');
			$this->response->setOutput('{}');

			return;
		}

		$this->load->model('extension/module/appnuno');

		$this->response->setOutput($this->json($this->model_extension_module_appnuno->getState()));
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

		$page = isset($this->request->get['page']) && is_string($this->request->get['page']) ? $this->request->get['page'] : '';
		$route = isset($this->request->get['page_route']) && is_string($this->request->get['page_route']) ? $this->request->get['page_route'] : 'common/home';
		$query = isset($this->request->get) && is_array($this->request->get) ? $this->request->get : array();
		$integration = AppnunoConfig::integration($config, $route, $this->model_extension_module_appnuno->getState(), $this->languageCode(), $page, $query);

		if (!is_array($integration)) {
			return '';
		}

		$integration['stateUrl'] = html_entity_decode($this->url->link('extension/module/appnuno/state', '', true), ENT_QUOTES, 'UTF-8');

		return $this->json($integration);
	}

	private function visibleTabs($tabs) {
		return AppnunoConfig::visibleTabs($tabs);
	}

	private function tabShownOnRoute($tab, $route) {
		return AppnunoConfig::tabShownOnRoute($tab, $route);
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

	private function moduleEnabled() {
		return in_array($this->config->get('module_appnuno_status'), array('1', 1), true);
	}

	private function responseHeaders($content_type) {
		$this->response->addHeader('Content-Type: ' . $content_type);
		$this->response->addHeader('Cache-Control: no-store, private');
		$this->response->addHeader('X-Content-Type-Options: nosniff');
	}

	private function json($value) {
		return json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
	}

	private function languageCode() {
		if (isset($this->session->data['language']) && is_string($this->session->data['language']) && strlen($this->session->data['language'])) {
			return $this->session->data['language'];
		}

		$language_code = $this->config->get('config_language');

		return is_string($language_code) ? $language_code : '';
	}
}
