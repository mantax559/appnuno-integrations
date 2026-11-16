<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminAppnunoController extends ModuleAdminController
{
    private $appnunoErrors = array(
        'warning' => '',
        'config' => '',
        'config_path' => '',
    );

    public function __construct()
    {
        parent::__construct();
        $this->bootstrap = true;
        $this->meta_title = 'AppNuno';
    }

    public function postProcess()
    {
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = AppnunoAdmin::persist();
            if (is_array($result)) {
                $this->appnunoErrors = $result;
            }
        }

        return parent::postProcess();
    }

    public function initContent()
    {
        $this->content = AppnunoAdmin::render($this->appnunoErrors);
        parent::initContent();
    }

    public function setMedia($isNewTheme = false)
    {
        if (version_compare(_PS_VERSION_, '1.7.0.0', '<')) {
            parent::setMedia();
        } else {
            parent::setMedia($isNewTheme);
        }
        $version = AppnunoPaths::assetVersion();
        $this->addCSS(AppnunoPaths::assetUrl('settings.css') . '?v=' . $version, 'all', null, false);
        $this->addCSS(AppnunoPaths::assetUrl('adapter.css') . '?v=' . $version, 'all', null, false);
    }

    public function createTemplate($tpl_name)
    {
        $path = _PS_MODULE_DIR_ . 'appnuno/views/templates/admin/content.tpl';
        if ($tpl_name === 'content.tpl' && is_file($path)) {
            return $this->context->smarty->createTemplate($path, $this->context->smarty);
        }

        return parent::createTemplate($tpl_name);
    }
}
