<?php
class ControllerExtensionModuleAppnuno extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/module/appnuno');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');
        $this->load->model('extension/module/appnuno');

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
        $data['help_config'] = $this->language->get('help_config');
        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');
        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
        $data['error_config'] = isset($this->error['config']) ? $this->error['config'] : '';
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], 'SSL'));
        $data['breadcrumbs'][] = array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=module', 'SSL'));
        $data['breadcrumbs'][] = array('text' => $data['heading_title'], 'href' => $this->url->link('extension/module/appnuno', 'token=' . $this->session->data['token'], 'SSL'));
        $data['action'] = $this->url->link('extension/module/appnuno', 'token=' . $this->session->data['token'], 'SSL');
        $data['cancel'] = $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=module', 'SSL');
        $data['module_appnuno_status'] = isset($this->request->post['module_appnuno_status']) ? $this->request->post['module_appnuno_status'] : (in_array($this->config->get('module_appnuno_status'), array('1', 1), true) ? '1' : '0');
        $data['module_appnuno_config'] = isset($this->request->post['module_appnuno_config']) && is_string($this->request->post['module_appnuno_config']) ? $this->request->post['module_appnuno_config'] : $this->model_extension_module_appnuno->defaultConfig();
        $configuration = json_decode($data['module_appnuno_config'], true);
        if (!is_array($configuration) || json_last_error()) {
            $configuration = json_decode($this->model_extension_module_appnuno->defaultConfig(), true);
        }
        $data['configuration_json'] = json_encode($configuration, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
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
        $config_error = $this->model_extension_module_appnuno->validateConfig(isset($this->request->post['module_appnuno_config']) ? $this->request->post['module_appnuno_config'] : '');
        if ($config_error) {
            $this->error['config'] = $config_error;
        }
        return !$this->error;
    }
}
