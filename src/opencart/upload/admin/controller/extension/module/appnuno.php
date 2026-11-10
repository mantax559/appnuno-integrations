<?php
if (!class_exists('AppnunoConfig', false)) {
	if (defined('DIR_SYSTEM') && is_file(DIR_SYSTEM . 'library/appnuno-config.php')) {
		require_once DIR_SYSTEM . 'library/appnuno-config.php';
	} else {
		require_once dirname(__FILE__) . '/../../../../../../shared/php/appnuno-config.php';
	}
}

class ControllerExtensionModuleAppnuno extends Controller {
	private $error = array();
	private $available_languages = array();

	public function index() {
		$this->load->language('extension/module/appnuno');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('extension/module/appnuno');
		$this->load->model('localisation/language');

		$this->available_languages = array_values($this->model_localisation_language->getLanguages());

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->load->model('extension/event');

			$this->model_extension_event->deleteEvent('appnuno');
			$this->model_extension_event->addEvent('appnuno', 'catalog/view/common/header/before', 'extension/module/appnuno/inject');
			$this->model_setting_setting->editSetting('module_appnuno', array(
				'module_appnuno_status' => $this->request->post['module_appnuno_status'],
				'module_appnuno_config' => $this->request->post['module_appnuno_config']
			));

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('extension/module/appnuno', 'token=' . $this->session->data['token'], true));
		}

		$data['heading_title'] = $this->language->get('heading_title');
		$data['appnuno_version'] = trim(file_get_contents(DIR_APPLICATION . 'view/javascript/appnuno/version.txt'));
		$data['text_documentation'] = $this->language->get('text_documentation');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['text_default_tabs'] = $this->language->get('text_default_tabs');
		$data['text'] = $this->interfaceText();
		$data['icon_catalog_search'] = $this->language->get('text_icon_catalog_search');
		$data['languages'] = $this->available_languages;
		$data['destinations'] = $this->translatedDestinations($this->model_extension_module_appnuno->getDestinations((int)$this->config->get('config_language_id'), (int)$this->config->get('config_store_id')));
		$data['route_options'] = $this->routeOptions();
		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['error_warning'] = $this->capturedError('warning');

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['error_config'] = $this->capturedError('config');
		$data['error_config_path'] = $this->capturedError('config_path');
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=module', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $data['heading_title'],
			'href' => $this->url->link('extension/module/appnuno', 'token=' . $this->session->data['token'], true)
		);

		$data['action'] = $this->url->link('extension/module/appnuno', 'token=' . $this->session->data['token'], true);
		$data['cancel'] = $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=module', true);
		$data['module_appnuno_status'] = $this->moduleStatus();
		list($data['module_appnuno_config'], $configuration) = $this->configurationState();
		$data['configuration_json'] = $this->json($configuration);
		$data['text_json'] = $this->json($data['text']);
		$data['languages_json'] = $this->json($data['languages']);
		$data['destinations_json'] = $this->json($data['destinations']);
		$data['route_options_json'] = $this->json($data['route_options']);
		$data['error_config_path_json'] = $this->json($data['error_config_path']);
		$data['default_language_code'] = $this->config->get('config_language');
		$data['appnuno_settings'] = $this->settingsMarkup($data);
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/appnuno', $data));
	}

	public function install() {
		$this->load->model('user/user_group');

		$permission_id = method_exists($this->user, 'getGroupId') ? $this->user->getGroupId() : $this->user->getId();

		$this->model_user_user_group->addPermission($permission_id, 'access', 'extension/module/appnuno');
		$this->model_user_user_group->addPermission($permission_id, 'modify', 'extension/module/appnuno');

		$this->load->model('extension/event');

		$this->model_extension_event->addEvent('appnuno', 'catalog/view/common/header/before', 'extension/module/appnuno/inject');
	}

	public function uninstall() {
		$this->load->model('extension/event');

		$this->model_extension_event->deleteEvent('appnuno');

		$this->load->model('setting/setting');

		$this->model_setting_setting->deleteSetting('module_appnuno');
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/appnuno')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->post['module_appnuno_status']) || !in_array($this->request->post['module_appnuno_status'], array('0', '1', 0, 1), true)) {
			$this->error['warning'] = $this->language->get('error_status');
		}

		$posted_config = isset($this->request->post['module_appnuno_config']) && is_string($this->request->post['module_appnuno_config']) ? htmlspecialchars_decode($this->request->post['module_appnuno_config'], ENT_COMPAT) : '';

		if (!strlen(trim($posted_config))) {
			$this->error['config'] = $this->language->get('error_config_missing');
			$this->error['config_path'] = 'module_appnuno_config';

			return false;
		}

		$this->request->post['module_appnuno_config'] = $this->model_extension_module_appnuno->normalizeConfig($posted_config);
		$posted_config = $this->request->post['module_appnuno_config'];
		$config_error = $this->model_extension_module_appnuno->validateConfig($this->request->post['module_appnuno_config'], $this->languageCodes());

		if (strlen($config_error)) {
			$this->error['config'] = AppnunoConfig::readableConfigError(
				$config_error,
				$posted_config,
				function ($key) {
					return $this->language->get($key);
				},
				function ($route) {
					return $this->routeLabel($route);
				},
				function ($code) {
					return $this->languageName($code);
				},
				$this->activeLanguageCode()
			);
			$error_separator = strrpos($config_error, ':');
			$this->error['config_path'] = is_int($error_separator) ? substr($config_error, 0, $error_separator) : '';
		}

		return !$this->error;
	}

	private function settingsMarkup($data) {
		$path = DIR_APPLICATION . 'view/javascript/appnuno/settings.html';
		$html = is_file($path) ? file_get_contents($path) : '';
		$enabled = in_array($data['module_appnuno_status'], array('1', 1), true);
		$replacements = array(
			'appnuno_version' => htmlspecialchars($data['appnuno_version'], ENT_QUOTES, 'UTF-8'),
			'documentation_url' => 'https://appnuno.com/opencart',
			'cancel' => htmlspecialchars($data['cancel'], ENT_QUOTES, 'UTF-8'),
			'form_action' => htmlspecialchars($data['action'], ENT_QUOTES, 'UTF-8'),
			'form_nonce' => '',
			'status_enabled_pressed' => $enabled ? 'true' : 'false',
			'status_disabled_pressed' => $enabled ? 'false' : 'true',
			'status_value' => $enabled ? '1' : '0',
			'alert_success' => AppnunoConfig::alert('success', 'check', $data['success'], $data['button_cancel']),
			'alert_warning' => AppnunoConfig::alert('danger', 'alert', $data['error_warning'], $data['button_cancel']),
			'alert_config' => AppnunoConfig::alert('danger', 'alert', $data['error_config'], $data['button_cancel']),
			'configuration_json' => $data['configuration_json'],
			'text_json' => $data['text_json'],
			'languages_json' => $data['languages_json'],
			'destinations_json' => $data['destinations_json'],
			'route_options_json' => $data['route_options_json'],
			'error_config_path_json' => $data['error_config_path_json'],
			'default_language_json' => $this->json($data['default_language_code']),
			'settings_script' => 'view/javascript/appnuno/settings.js?v=' . $this->assetVersion()
		);

		foreach (array('text_documentation', 'button_cancel', 'button_save', 'text_default_tabs', 'entry_status', 'text_enabled', 'text_disabled', 'icon_catalog_search') as $key) {
			$replacements[$key === 'icon_catalog_search' ? 'text_icon_catalog_search' : $key] = htmlspecialchars($data[$key], ENT_QUOTES, 'UTF-8');
		}

		foreach ($data['text'] as $key => $value) {
			$replacements[$key] = htmlspecialchars(is_string($value) ? $value : '', ENT_QUOTES, 'UTF-8');
		}

		return AppnunoConfig::renderSettings(is_string($html) ? $html : '', $replacements);
	}

	private function assetVersion() {
		$directory = DIR_APPLICATION . 'view/javascript/appnuno/';
		$body = '';

		foreach (array('settings.css', 'adapter.css', 'settings.js', 'settings.html') as $name) {
			$body .= is_file($directory . $name) ? hash_file('sha256', $directory . $name) : '';
		}

		return substr(hash('sha256', $body), 0, 12);
	}

	private function interfaceText() {
		$text = array();

		foreach (array('text_enabled', 'text_disabled', 'text_yes', 'text_no', 'text_visibility_on', 'text_icon_page_previous', 'text_icon_page_next', 'button_add_default_tab', 'button_add_first_tab', 'text_default_tabs_empty', 'text_default_tabs_empty_help', 'entry_cart_tab', 'help_cart_tab', 'help_status', 'text_back_button', 'entry_back_enabled', 'entry_back_placement', 'text_placement_separate', 'text_placement_bar', 'text_glass', 'text_glass_button', 'entry_glass_enabled', 'entry_glass_color_scheme', 'text_color_auto', 'text_color_light', 'text_color_dark', 'entry_glass_style', 'text_style_regular', 'text_style_clear', 'text_style_none', 'entry_glass_animate', 'entry_glass_duration', 'entry_glass_interactive', 'entry_glass_tint', 'entry_glass_opacity', 'text_navigation_colors', 'text_light_mode', 'text_dark_mode', 'entry_background', 'entry_content', 'text_language_labels', 'text_label', 'text_default_language', 'text_destination', 'text_choose_destination', 'text_custom_url', 'text_url_placeholder', 'text_tab_id', 'text_tab_id_placeholder', 'text_icon', 'text_choose_icon', 'text_icon_catalog_search', 'text_icon_catalog_loading', 'text_icon_catalog_empty', 'text_icon_catalog_error', 'text_icon_catalog_clear', 'text_icon_catalog_total', 'button_remove_tab', 'text_shown_on_pages', 'help_shown_on_pages', 'text_all_pages', 'button_manage_pages', 'text_show_on_all_pages', 'text_show_on_selected_pages', 'text_show_on_no_pages', 'button_apply_pages', 'button_cancel_pages', 'button_select_all_pages', 'button_clear_pages', 'text_tab_missing', 'text_tab_visible', 'text_tab_visibility_toggle', 'text_visibility_indicator', 'text_tab_list_empty', 'help_default_tabs', 'help_back_enabled', 'help_back_placement', 'help_glass_enabled', 'help_glass_color_scheme', 'help_glass_style', 'help_glass_animate', 'help_glass_duration', 'help_glass_interactive', 'help_glass_tint', 'help_glass_opacity', 'help_background', 'help_content', 'help_tab_id', 'help_tab_labels', 'help_tab_destination', 'help_icon_catalog', 'help_tab_visibility') as $key) {
			$text[$key] = $this->language->get($key);
		}

		return $text;
	}

	private function translatedDestinations($destinations) {
		$labels = array(
			'Home' => 'text_destination_home',
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
				$destinations[$index]['label'] = $this->language->get($labels[$destination['label']]);

				continue;
			}

			foreach (array('Information: ' => 'text_destination_information', 'Category: ' => 'text_destination_category') as $prefix => $key) {
				if (strncmp($destination['label'], $prefix, strlen($prefix)) === 0) {
					$destinations[$index]['label'] = $this->language->get($key) . substr($destination['label'], strlen($prefix));

					break;
				}
			}
		}

		return $destinations;
	}

	private function languageName($code) {
		if ($code === 'default') {
			return $this->language->get('text_default_language');
		}

		foreach ($this->available_languages as $language) {
			if (isset($language['code'], $language['name']) && $language['code'] === $code && is_string($language['name']) && strlen($language['name'])) {
				return $language['name'];
			}
		}

		return $code;
	}

	private function routeLabel($route) {
		$labels = $this->routeKeys();

		return isset($labels[$route]) ? $this->language->get($labels[$route]) : $route;
	}

	private function routeOptions() {
		$options = array();

		foreach ($this->routeKeys() as $route => $key) {
			$options[] = array(
				'label' => $this->language->get($key),
				'route' => $route
			);
		}

		return $options;
	}

	private function routeKeys() {
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
			'checkout/checkout' => 'text_route_checkout'
		);
	}

	private function moduleStatus() {
		if (isset($this->request->post['module_appnuno_status'])) {
			return $this->request->post['module_appnuno_status'];
		}

		return in_array($this->config->get('module_appnuno_status'), array('1', 1), true) ? '1' : '0';
	}

	private function configurationState() {
		if (isset($this->request->post['module_appnuno_config']) && is_string($this->request->post['module_appnuno_config'])) {
			$json = $this->request->post['module_appnuno_config'];
		} else {
			$json = $this->config->get('module_appnuno_config');
		}

		if (!is_string($json) || !strlen(trim($json))) {
			$json = $this->model_extension_module_appnuno->defaultConfig();
		}

		$configuration = json_decode($json, true);

		if (!isset($this->request->server['REQUEST_METHOD']) || ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			if (!is_array($configuration) || $this->model_extension_module_appnuno->validateConfig($json)) {
				$json = $this->model_extension_module_appnuno->defaultConfig();
				$configuration = json_decode($json, true);
			}
		}

		if (!is_array($configuration)) {
			$configuration = json_decode($this->model_extension_module_appnuno->defaultConfig(), true);
		}

		return array($json, $configuration);
	}

	private function languageCodes() {
		$language_codes = array();

		foreach ($this->available_languages as $language) {
			if (isset($language['code']) && is_string($language['code'])) {
				$language_codes[] = $language['code'];
			}
		}

		return $language_codes;
	}

	private function capturedError($key) {
		return isset($this->error[$key]) ? $this->error[$key] : '';
	}

	private function json($value) {
		return json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
	}

	private function activeLanguageCode() {
		$language_code = $this->config->get('config_language');

		return is_string($language_code) ? $language_code : '';
	}
}
