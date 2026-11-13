<?php
require '/var/www/html/config/config.inc.php';

$idShop = (int) Configuration::get('PS_SHOP_DEFAULT');
$idLang = (int) Configuration::get('PS_LANG_DEFAULT');
Shop::setContext(Shop::CONTEXT_SHOP, $idShop);
$context = Context::getContext();
$context->shop = new Shop($idShop);
$context->language = new Language($idLang);
$context->currency = new Currency((int) Configuration::get('PS_CURRENCY_DEFAULT'));
Module::updateTranslationsAfterInstall(false);

$action = isset($argv[1]) ? $argv[1] : '';
$module = Module::getInstanceByName('appnuno');
if (!$module) {
    fwrite(STDERR, "AppNuno module was not found.\n");
    exit(1);
}
if ($action === 'uninstall') {
    if (Module::isInstalled('appnuno') && !$module->uninstall()) {
        fwrite(STDERR, "AppNuno module could not be uninstalled.\n");
        exit(1);
    }
    clearModuleCaches();
    fwrite(STDOUT, "uninstalled\n");
    exit(0);
}
if ($action === 'install') {
    if (!Module::isInstalled('appnuno') && !$module->install()) {
        fwrite(STDERR, "AppNuno module could not be installed.\n");
        exit(1);
    }
    $updateRole = (int) Db::getInstance()->getValue('SELECT id_authorization_role FROM `' . _DB_PREFIX_ . 'authorization_role` WHERE slug = "ROLE_MOD_TAB_ADMINAPPNUNO_UPDATE"');
    $limitedProfile = (int) Db::getInstance()->getValue('SELECT id_profile FROM `' . _DB_PREFIX_ . 'employee` WHERE email = "limited@example.test"');
    if ($updateRole && $limitedProfile) {
        Db::getInstance()->delete('access', 'id_profile = ' . $limitedProfile . ' AND id_authorization_role = ' . $updateRole);
    }
    clearModuleCaches();
    fwrite(STDOUT, "installed\n");
    exit(0);
}
fwrite(STDERR, "Use uninstall or install.\n");
exit(1);

function clearModuleCaches()
{
    if (class_exists('Cache')) {
        Cache::clean('*');
    }
    if (method_exists('Tools', 'clearSf2Cache')) {
        Tools::clearSf2Cache();
    }
    if (method_exists('Tools', 'clearSmartyCache')) {
        Tools::clearSmartyCache();
    }
}
