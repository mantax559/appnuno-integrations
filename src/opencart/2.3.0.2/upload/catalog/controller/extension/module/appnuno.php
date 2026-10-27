<?php
class ControllerExtensionModuleAppnuno extends Controller {
    public function inject(&$route, &$data) {
        if (!in_array($this->config->get('module_appnuno_status'), array('1', 1), true)) {
            return;
        }
        if (!isset($data['scripts']) || !is_array($data['scripts'])) {
            $data['scripts'] = array();
        }
        $page_route = isset($this->request->get['route']) && is_string($this->request->get['route']) ? $this->request->get['route'] : 'common/home';
        $data['scripts'][] = html_entity_decode($this->url->link('extension/module/appnuno/bootstrap', 'page_route=' . rawurlencode($page_route), 'SSL'), ENT_QUOTES, 'UTF-8');
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
        $tabs = isset($config['routes'][$route]) && is_array($config['routes'][$route]) ? $config['routes'][$route] : $config['defaultTabs'];
        $cart_tab_id = isset($config['cartTabId']) ? $config['cartTabId'] : 'cart';
        foreach ($tabs as &$tab) {
            if (isset($tab['urls']) && is_array($tab['urls']) && count($tab['urls'])) {
                $language_code = $this->config->get('config_language');
                if (isset($tab['urls'][$language_code])) {
                    $tab['url'] = $tab['urls'][$language_code];
                } elseif (!isset($tab['url'])) {
                    $tab['url'] = reset($tab['urls']);
                }
                unset($tab['urls']);
            }
            if (isset($tab['labels']) && is_array($tab['labels'])) {
                $language_code = $this->config->get('config_language');
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
        $navigation = array('tabs' => $tabs);
        foreach (array('back', 'glass', 'colors') as $key) {
            if (isset($config[$key]) && is_array($config[$key])) {
                $navigation[$key] = $config[$key];
            }
        }
        $integration = array(
            'config' => $config,
            'route' => $route,
            'stateUrl' => html_entity_decode($this->url->link('extension/module/appnuno/state', '', 'SSL'), ENT_QUOTES, 'UTF-8'),
            'state' => $state,
            'navigation' => $navigation
        );
        return json_encode($integration, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    }
}
