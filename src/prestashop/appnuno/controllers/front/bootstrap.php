<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class AppnunoBootstrapModuleFrontController extends ModuleFrontController
{
    public $ajax = true;

    public $content_only = true;

    public $auth = false;

    public function postProcess()
    {
        AppnunoStorefront::bootstrap();
        exit;
    }
}
