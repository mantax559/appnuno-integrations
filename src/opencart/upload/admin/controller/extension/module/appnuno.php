<?php
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

		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['text_default_tabs'] = $this->language->get('text_default_tabs');
		$data['text'] = $this->interfaceText();
		$data['languages'] = $this->available_languages;
		$data['destinations'] = $this->translatedDestinations($this->model_extension_module_appnuno->getDestinations((int)$this->config->get('config_language_id'), (int)$this->config->get('config_store_id')));
		$data['route_options'] = array(
			array(
				'label' => $this->language->get('text_route_home'),
				'route' => 'common/home'
			),
			array(
				'label' => $this->language->get('text_route_category'),
				'route' => 'product/category'
			),
			array(
				'label' => $this->language->get('text_route_product'),
				'route' => 'product/product'
			),
			array(
				'label' => $this->language->get('text_route_special'),
				'route' => 'product/special'
			),
			array(
				'label' => $this->language->get('text_route_search'),
				'route' => 'product/search'
			),
			array(
				'label' => $this->language->get('text_route_manufacturer'),
				'route' => 'product/manufacturer'
			),
			array(
				'label' => $this->language->get('text_route_information'),
				'route' => 'information/information'
			),
			array(
				'label' => $this->language->get('text_route_contact'),
				'route' => 'information/contact'
			),
			array(
				'label' => $this->language->get('text_route_account'),
				'route' => 'account/account'
			),
			array(
				'label' => $this->language->get('text_route_login'),
				'route' => 'account/login'
			),
			array(
				'label' => $this->language->get('text_route_register'),
				'route' => 'account/register'
			),
			array(
				'label' => $this->language->get('text_route_cart'),
				'route' => 'checkout/cart'
			),
			array(
				'label' => $this->language->get('text_route_checkout'),
				'route' => 'checkout/checkout'
			)
		);
		$data['help_config'] = $this->language->get('help_config');

		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if (isset($this->error['config'])) {
			$data['error_config'] = $this->error['config'];
		} else {
			$data['error_config'] = '';
		}

		if (isset($this->error['config_path'])) {
			$data['error_config_path'] = $this->error['config_path'];
		} else {
			$data['error_config_path'] = '';
		}

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

		if (isset($this->request->post['module_appnuno_status'])) {
			$data['module_appnuno_status'] = $this->request->post['module_appnuno_status'];
		} elseif (in_array($this->config->get('module_appnuno_status'), array('1', 1), true)) {
			$data['module_appnuno_status'] = '1';
		} else {
			$data['module_appnuno_status'] = '0';
		}

		if (isset($this->request->post['module_appnuno_config']) && is_string($this->request->post['module_appnuno_config'])) {
			$data['module_appnuno_config'] = $this->request->post['module_appnuno_config'];
		} else {
			$data['module_appnuno_config'] = $this->config->get('module_appnuno_config');
		}

		if (!is_string($data['module_appnuno_config']) || !strlen(trim($data['module_appnuno_config']))) {
			$data['module_appnuno_config'] = $this->model_extension_module_appnuno->defaultConfig();
		}

		$configuration = json_decode($data['module_appnuno_config'], true);

		if (!isset($this->request->server['REQUEST_METHOD']) || ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			if (!is_array($configuration) || $this->model_extension_module_appnuno->validateConfig($data['module_appnuno_config'])) {
				$data['module_appnuno_config'] = $this->model_extension_module_appnuno->defaultConfig();
				$configuration = json_decode($data['module_appnuno_config'], true);
			}
		}

		if (!is_array($configuration)) {
			$configuration = json_decode($this->model_extension_module_appnuno->defaultConfig(), true);
		}

		$data['configuration_json'] = json_encode($configuration, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
		$data['text_json'] = json_encode($data['text'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
		$data['languages_json'] = json_encode($data['languages'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
		$data['destinations_json'] = json_encode($data['destinations'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
		$data['route_options_json'] = json_encode($data['route_options'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
		$data['error_config_path_json'] = json_encode($data['error_config_path'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
		$data['default_language_code'] = $this->config->get('config_language');

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

		$this->request->post['module_appnuno_config'] = $posted_config;

		$language_codes = array();

		foreach ($this->available_languages as $language) {
			if (isset($language['code']) && is_string($language['code'])) {
				$language_codes[] = $language['code'];
			}
		}

		$config_error = $this->model_extension_module_appnuno->validateConfig($this->request->post['module_appnuno_config'], $language_codes);

		if (strlen($config_error)) {
			$this->error['config'] = $config_error;
			$error_separator = strrpos($config_error, ':');
			$this->error['config_path'] = is_int($error_separator) ? substr($config_error, 0, $error_separator) : '';
		}

		return !$this->error;
	}

	private function interfaceText() {
		$text = array();

		foreach (array('text_default_tabs_help', 'button_add_default_tab', 'entry_cart_tab', 'help_cart_tab', 'help_status', 'text_back_button', 'entry_back_enabled', 'entry_back_placement', 'text_placement_separate', 'text_placement_bar', 'text_glass', 'entry_glass_enabled', 'entry_glass_color_scheme', 'text_color_auto', 'text_color_light', 'text_color_dark', 'entry_glass_style', 'text_style_regular', 'text_style_clear', 'text_style_none', 'entry_glass_animate', 'entry_glass_duration', 'entry_glass_interactive', 'entry_glass_tint', 'entry_glass_opacity', 'text_navigation_colors', 'text_light_mode', 'text_dark_mode', 'entry_background', 'entry_content', 'text_language_labels', 'text_label', 'text_default_language', 'text_destination', 'text_choose_destination', 'text_custom_url', 'text_url_placeholder', 'text_tab_id', 'text_icon', 'text_active_icon', 'button_remove_tab', 'text_shown_on_pages', 'help_shown_on_pages', 'help_shown_on_pages_lead', 'button_clear_pages', 'text_tab_missing', 'text_tab_visible', 'text_tab_visibility_toggle', 'text_visibility_indicator', 'text_tab_list_empty', 'help_default_tabs', 'help_back_enabled', 'help_back_placement', 'help_glass_enabled', 'help_glass_color_scheme', 'help_glass_style', 'help_glass_animate', 'help_glass_duration', 'help_glass_interactive', 'help_glass_tint', 'help_glass_opacity', 'help_background', 'help_content', 'help_tab_id', 'help_tab_labels', 'help_tab_destination', 'help_tab_icon', 'help_tab_active_icon', 'help_tab_visibility', 'help_cart_tab') as $key) {
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
}
