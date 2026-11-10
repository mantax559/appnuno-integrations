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
        $this->show_page_header_toolbar = false;
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        $version = AppnunoPaths::assetVersion();
        $this->addCSS(AppnunoPaths::assetUrl('settings.css') . '?v=' . $version, 'all', PHP_INT_MAX, false);
        $this->addCSS(AppnunoPaths::assetUrl('adapter.css') . '?v=' . $version, 'all', PHP_INT_MAX, false);
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
