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
		$data['appnuno_version'] = trim(file_get_contents(DIR_APPLICATION . 'view/javascript/appnuno/version.txt'));
		$data['appnuno_settings_css_version'] = filemtime(DIR_APPLICATION . 'view/javascript/appnuno/settings.css');

		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_documentation'] = $this->language->get('text_documentation');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['text_default_tabs'] = $this->language->get('text_default_tabs');
		$data['text'] = $this->interfaceText();
		$data['icon_catalog_search'] = $this->language->get('text_icon_catalog_search');
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

		$this->request->post['module_appnuno_config'] = $this->model_extension_module_appnuno->normalizeConfig($posted_config);
		$posted_config = $this->request->post['module_appnuno_config'];

		$language_codes = array();

		foreach ($this->available_languages as $language) {
			if (isset($language['code']) && is_string($language['code'])) {
				$language_codes[] = $language['code'];
			}
		}

		$config_error = $this->model_extension_module_appnuno->validateConfig($this->request->post['module_appnuno_config'], $language_codes);

		if (strlen($config_error)) {
			$this->error['config'] = $this->readableConfigError($config_error, $posted_config);
			$error_separator = strrpos($config_error, ':');
			$this->error['config_path'] = is_int($error_separator) ? substr($config_error, 0, $error_separator) : '';
		}

		return !$this->error;
	}

	private function interfaceText() {
		$text = array();

		foreach (array('text_enabled', 'text_disabled', 'text_yes', 'text_no', 'text_visibility_on', 'text_icon_page_previous', 'text_icon_page_next', 'button_add_default_tab', 'button_add_first_tab', 'text_default_tabs_empty', 'text_default_tabs_empty_help', 'entry_cart_tab', 'help_cart_tab', 'help_status', 'text_back_button', 'entry_back_enabled', 'entry_back_placement', 'text_placement_separate', 'text_placement_bar', 'text_glass', 'text_glass_button', 'entry_glass_enabled', 'entry_glass_color_scheme', 'text_color_auto', 'text_color_light', 'text_color_dark', 'entry_glass_style', 'text_style_regular', 'text_style_clear', 'text_style_none', 'entry_glass_animate', 'entry_glass_duration', 'entry_glass_interactive', 'entry_glass_tint', 'entry_glass_opacity', 'text_navigation_colors', 'text_light_mode', 'text_dark_mode', 'entry_background', 'entry_content', 'text_language_labels', 'text_label', 'text_default_language', 'text_destination', 'text_choose_destination', 'text_custom_url', 'text_url_placeholder', 'text_tab_id', 'text_tab_id_placeholder', 'text_icon', 'text_choose_icon', 'text_icon_catalog_search', 'text_icon_catalog_loading', 'text_icon_catalog_empty', 'text_icon_catalog_error', 'text_icon_catalog_clear', 'text_icon_catalog_total', 'button_remove_tab', 'text_shown_on_pages', 'help_shown_on_pages', 'text_all_pages', 'button_manage_pages', 'text_show_on_all_pages', 'text_show_on_selected_pages', 'text_show_on_no_pages', 'button_apply_pages', 'button_cancel_pages', 'button_select_all_pages', 'button_clear_pages', 'text_tab_missing', 'text_tab_visible', 'text_tab_visibility_toggle', 'text_visibility_indicator', 'text_tab_list_empty', 'help_default_tabs', 'help_back_enabled', 'help_back_placement', 'help_glass_enabled', 'help_glass_color_scheme', 'help_glass_style', 'help_glass_animate', 'help_glass_duration', 'help_glass_interactive', 'help_glass_tint', 'help_glass_opacity', 'help_background', 'help_content', 'help_tab_id', 'help_tab_labels', 'help_tab_destination', 'help_icon_catalog', 'help_tab_visibility', 'help_cart_tab') as $key) {
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

	private function readableConfigError($config_error, $posted_config) {
		$separator = strrpos($config_error, ': ');

		if (!is_int($separator)) {
			return $config_error;
		}

		$message = substr($config_error, $separator + 2);
		$key = $this->configErrorKey($message);
		$sentence = strlen($key) ? $this->language->get($key) : $message;

		if (!strlen($sentence) || $sentence === $key) {
			$sentence = $message;
		}

		if (!strlen($key)) {
			$place = $this->readableConfigPlace(substr($config_error, 0, $separator), $posted_config);

			return strlen($place) ? $place . ': ' . $sentence : $sentence;
		}

		return $this->composeConfigError($key, substr($config_error, 0, $separator), $posted_config);
	}

	private function composeConfigError($key, $path, $posted_config) {
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

		$context = $this->errorContext($path, $posted_config);
		$template_key = $key;

		if (strlen($context['page']) && $this->language->get($key . '_page') !== $key . '_page') {
			$template_key = $key . '_page';
		}

		$sentence = $this->language->get($template_key);

		if (!strlen($sentence) || $sentence === $template_key) {
			$sentence = $this->language->get($key);
		}

		return strtr($sentence, array(
			'{tab}' => $context['tab'],
			'{page}' => $context['page'],
			'{language}' => $context['language'],
			'{mode}' => $context['mode'],
			'{field}' => $context['field']
		));
	}

	private function errorContext($path, $posted_config) {
		$config = json_decode($posted_config, true);

		if (!is_array($config)) {
			$config = array();
		}

		$context = array('tab' => '', 'page' => '', 'language' => '', 'mode' => '', 'field' => '');

		if (preg_match('/^(defaultTabs|routes\.[^\[]+)\[(\d+)\](?:\.(.*))?$/', $path, $match)) {
			if (strpos($match[1], 'routes.') === 0) {
				$context['page'] = $this->routeLabel(substr($match[1], 7));
			}

			$context['tab'] = $this->tabTitle($this->tabsForPath($config, $match[1]), (int)$match[2]);
			$field = isset($match[3]) ? $match[3] : '';

			if (strpos($field, 'urls.') === 0) {
				$context['language'] = $this->languageName(substr($field, 5));
			}
		} elseif (preg_match('/^routes\.(.+)\.route$/', $path, $match)) {
			$context['page'] = $match[1];
		} elseif (preg_match('/^routes\.(.+)$/', $path, $match)) {
			$context['page'] = $this->routeLabel($match[1]);
		} elseif (preg_match('/^colors\.(light|dark)(?:\.(backgroundColor|contentColor))?$/', $path, $match)) {
			$context['mode'] = $this->language->get($match[1] === 'light' ? 'text_light_mode' : 'text_dark_mode');

			if (isset($match[2]) && strlen($match[2])) {
				$context['field'] = $this->language->get($match[2] === 'backgroundColor' ? 'entry_background' : 'entry_content');
			}
		}

		return $context;
	}

	private function configErrorKey($message) {
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

	private function readableConfigPlace($path, $posted_config) {
		$config = json_decode($posted_config, true);

		if (!is_array($config)) {
			$config = array();
		}

		if ($path === 'module_appnuno_config') {
			return '';
		}

		if ($path === 'cartTabId') {
			return $this->language->get('entry_cart_tab');
		}

		if ($path === 'back') {
			return $this->language->get('text_back_button');
		}

		if ($path === 'glass') {
			return $this->language->get('text_glass');
		}

		if ($path === 'colors') {
			return $this->language->get('text_navigation_colors');
		}

		if ($path === 'defaultTabs') {
			return $this->language->get('text_default_tabs');
		}

		if ($path === 'routes') {
			return $this->language->get('text_shown_on_pages');
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

			return $this->joinPlace(array($this->language->get('text_glass'), $this->language->get($fields[$match[1]])));
		}

		if (preg_match('/^colors\.(light|dark)$/', $path, $match)) {
			return $this->language->get($match[1] === 'light' ? 'text_light_mode' : 'text_dark_mode');
		}

		if (preg_match('/^colors\.(light|dark)\.(backgroundColor|contentColor)$/', $path, $match)) {
			return $this->joinPlace(array(
				$this->language->get($match[1] === 'light' ? 'text_light_mode' : 'text_dark_mode'),
				$this->language->get($match[2] === 'backgroundColor' ? 'entry_background' : 'entry_content')
			));
		}

		if (preg_match('/^routes\.(.+)\.route$/', $path, $match)) {
			return $this->joinPlace(array($this->language->get('text_shown_on_pages'), $this->routeLabel($match[1])));
		}

		if (preg_match('/^(defaultTabs|routes\.[^\[]+)\[(\d+)\](?:\.(.*))?$/', $path, $match)) {
			$tabs = $this->tabsForPath($config, $match[1]);
			$parts = array($this->tabTitle($tabs, (int)$match[2]));
			$field = $this->tabFieldLabel(isset($match[3]) ? $match[3] : '');

			if (strlen($field)) {
				$parts[] = $field;
			}

			if (strpos($match[1], 'routes.') === 0) {
				array_unshift($parts, $this->routeLabel(substr($match[1], 7)));
			}

			return $this->joinPlace($parts);
		}

		return '';
	}

	private function tabsForPath($config, $collection) {
		if ($collection === 'defaultTabs') {
			return isset($config['defaultTabs']) && is_array($config['defaultTabs']) ? array_values($config['defaultTabs']) : array();
		}

		$route = substr($collection, 7);

		return isset($config['routes'][$route]) && is_array($config['routes'][$route]) ? array_values($config['routes'][$route]) : array();
	}

	private function tabTitle($tabs, $index) {
		$titles = array();

		foreach ($tabs as $tab_index => $tab) {
			$titles[$tab_index] = $this->singleTabTitle(is_array($tab) ? $tab : array());
		}

		$title = isset($titles[$index]) ? $titles[$index] : $this->language->get('text_tab_list_empty');
		$count = 0;

		foreach ($titles as $candidate) {
			if ($candidate === $title) {
				$count++;
			}
		}

		if ($count > 1) {
			$title .= ' (' . ($index + 1) . ')';
		}

		return $title;
	}

	private function singleTabTitle($tab) {
		$language_code = $this->config->get('config_language');

		if (isset($tab['labels']) && is_array($tab['labels'])) {
			if (is_string($language_code) && isset($tab['labels'][$language_code]) && is_string($tab['labels'][$language_code]) && strlen(trim($tab['labels'][$language_code]))) {
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

		return $this->language->get('text_tab_list_empty');
	}

	private function tabFieldLabel($field) {
		if ($field === 'id') {
			return $this->language->get('text_tab_id');
		}

		if ($field === 'url' || $field === 'urls') {
			return $this->language->get('text_destination');
		}

		if ($field === 'labels') {
			return $this->language->get('text_language_labels');
		}

		if ($field === 'icon') {
			return $this->language->get('text_icon');
		}

		if ($field === 'visible') {
			return $this->language->get('text_tab_visible');
		}

		if ($field === 'badge') {
			return $this->language->get('entry_cart_tab');
		}

		if ($field === 'allPages' || $field === 'pages') {
			return $this->language->get('text_shown_on_pages');
		}

		if (strpos($field, 'urls.') === 0) {
			return $this->joinPlace(array($this->languageName(substr($field, 5)), $this->language->get('text_custom_url')));
		}

		if (strpos($field, 'labels.') === 0) {
			return $this->joinPlace(array($this->languageName(substr($field, 7)), $this->language->get('text_label')));
		}

		return '';
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
		$labels = array(
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

		return isset($labels[$route]) ? $this->language->get($labels[$route]) : $route;
	}

	private function joinPlace($parts) {
		$labels = array();

		foreach ($parts as $part) {
			if (is_string($part) && strlen($part)) {
				$labels[] = $part;
			}
		}

		return implode(' > ', $labels);
	}
}
