<?php
class ControllerExtensionModuleAppnuno extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/module/appnuno');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');
        $this->load->model('extension/module/appnuno');
        $this->load->model('localisation/language');

        if (isset($this->request->server['REQUEST_METHOD']) && in_array($this->request->server['REQUEST_METHOD'], array('POST'), true) && $this->validate()) {
            $this->model_setting_setting->editSetting('module_appnuno', array(
                'module_appnuno_status' => $this->request->post['module_appnuno_status'],
                'module_appnuno_config' => $this->request->post['module_appnuno_config']
            ));
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/module/appnuno', 'token=' . $this->session->data['token'], 'SSL'));
        }

        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_edit'] = $this->language->get('text_edit');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');
        $data['entry_status'] = $this->language->get('entry_status');
        $data['text_default_tabs'] = $this->language->get('text_default_tabs');
        $data['languages'] = array_values($this->model_localisation_language->getLanguages());
        $data['destinations'] = $this->model_extension_module_appnuno->getDestinations((int)$this->config->get('config_language_id'), (int)$this->config->get('config_store_id'));
        $data['route_options'] = array(
            array('label' => 'Home', 'route' => 'common/home'),
            array('label' => 'Category page', 'route' => 'product/category'),
            array('label' => 'Product page', 'route' => 'product/product'),
            array('label' => 'Special offers', 'route' => 'product/special'),
            array('label' => 'Search', 'route' => 'product/search'),
            array('label' => 'Manufacturer', 'route' => 'product/manufacturer'),
            array('label' => 'Information page', 'route' => 'information/information'),
            array('label' => 'Contact', 'route' => 'information/contact'),
            array('label' => 'Account', 'route' => 'account/account'),
            array('label' => 'Login', 'route' => 'account/login'),
            array('label' => 'Register', 'route' => 'account/register'),
            array('label' => 'Cart', 'route' => 'checkout/cart'),
            array('label' => 'Checkout', 'route' => 'checkout/checkout')
        );
        $data['help_config'] = $this->language->get('help_config');
        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');
        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
        $data['error_config'] = isset($this->error['config']) ? $this->error['config'] : '';
        $data['error_config_path'] = isset($this->error['config_path']) ? $this->error['config_path'] : '';
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], 'SSL'));
        $data['breadcrumbs'][] = array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=module', 'SSL'));
        $data['breadcrumbs'][] = array('text' => $data['heading_title'], 'href' => $this->url->link('extension/module/appnuno', 'token=' . $this->session->data['token'], 'SSL'));
        $data['action'] = $this->url->link('extension/module/appnuno', 'token=' . $this->session->data['token'], 'SSL');
        $data['cancel'] = $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=module', 'SSL');
        $data['module_appnuno_status'] = isset($this->request->post['module_appnuno_status']) ? $this->request->post['module_appnuno_status'] : (in_array($this->config->get('module_appnuno_status'), array('1', 1), true) ? '1' : '0');
        $data['module_appnuno_config'] = isset($this->request->post['module_appnuno_config']) && is_string($this->request->post['module_appnuno_config']) ? $this->request->post['module_appnuno_config'] : $this->config->get('module_appnuno_config');
        if (!is_string($data['module_appnuno_config']) || !strlen(trim($data['module_appnuno_config']))) {
            $data['module_appnuno_config'] = $this->model_extension_module_appnuno->defaultConfig();
        }
        $configuration = json_decode($data['module_appnuno_config'], true);
        if (!isset($this->request->server['REQUEST_METHOD']) || !in_array($this->request->server['REQUEST_METHOD'], array('POST'), true)) {
            if (!is_array($configuration) || $this->model_extension_module_appnuno->validateConfig($data['module_appnuno_config'])) {
                $data['module_appnuno_config'] = $this->model_extension_module_appnuno->defaultConfig();
                $configuration = json_decode($data['module_appnuno_config'], true);
            }
        }
        if (!is_array($configuration)) {
            $configuration = json_decode($this->model_extension_module_appnuno->defaultConfig(), true);
        }
        $data['configuration_json'] = json_encode($configuration, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $data['languages_json'] = json_encode($data['languages'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $data['destinations_json'] = json_encode($data['destinations'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $data['route_options_json'] = json_encode($data['route_options'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $data['error_config_path_json'] = json_encode($data['error_config_path'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $data['default_language_code'] = $this->config->get('config_language');
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/module/appnuno.tpl', $data));
    }

    public function install() {
        $this->load->model('user/user_group');
        $this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/module/appnuno');
        $this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/module/appnuno');
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
        if (!isset($this->request->post['module_appnuno_config']) || !is_string($this->request->post['module_appnuno_config']) || !strlen(trim($this->request->post['module_appnuno_config']))) {
            $stored_config = $this->config->get('module_appnuno_config');
            if (is_string($stored_config) && !strlen($this->model_extension_module_appnuno->validateConfig($stored_config))) {
                $this->request->post['module_appnuno_config'] = $stored_config;
            } else {
                $this->request->post['module_appnuno_config'] = $this->model_extension_module_appnuno->defaultConfig();
            }
        }
        $config_error = $this->model_extension_module_appnuno->validateConfig($this->request->post['module_appnuno_config']);
        if (strlen($config_error)) {
            $this->error['config'] = $config_error;
            $error_separator = strrpos($config_error, ':');
            $this->error['config_path'] = is_int($error_separator) ? substr($config_error, 0, $error_separator) : '';
        }
        return !$this->error;
    }
}
