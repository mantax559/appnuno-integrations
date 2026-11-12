<?php
require '/var/www/html/config/config.inc.php';

if (version_compare(_PS_VERSION_, '9.0.0', '<')) {
    fwrite(STDOUT, "AppNuno PrestaShop module is only installed on PrestaShop 9.\n");
    exit(0);
}

$zip = '/appnuno/package/appnuno-prestashop-9.x.zip';
if (!is_file($zip)) {
    fwrite(STDERR, "AppNuno PrestaShop package is missing.\n");
    exit(1);
}

$archive = new ZipArchive();
if ($archive->open($zip) !== true) {
    fwrite(STDERR, "Unable to open the AppNuno package.\n");
    exit(1);
}
if (!$archive->extractTo(_PS_MODULE_DIR_)) {
    $archive->close();
    fwrite(STDERR, "Unable to extract the AppNuno package.\n");
    exit(1);
}
$archive->close();

$moduleDir = _PS_MODULE_DIR_ . 'appnuno';
if (function_exists('opcache_reset')) {
    opcache_reset();
}
passthru('chown -R www-data:www-data ' . escapeshellarg($moduleDir));
if (method_exists('Tools', 'generateIndex')) {
    Tools::generateIndex();
}

$idShop = (int) Configuration::get('PS_SHOP_DEFAULT');
$idLang = (int) Configuration::get('PS_LANG_DEFAULT');
Shop::setContext(Shop::CONTEXT_SHOP, $idShop);
$context = Context::getContext();
$context->shop = new Shop($idShop);
$context->language = new Language($idLang);
$context->currency = new Currency((int) Configuration::get('PS_CURRENCY_DEFAULT'));

Module::updateTranslationsAfterInstall(false);
$module = Module::getInstanceByName('appnuno');
if (!$module) {
    fwrite(STDERR, "AppNuno module was not found after extraction.\n");
    exit(1);
}
$installed = Module::isInstalled('appnuno');
$headerHookId = (int) Hook::getIdByName('displayHeader');
$hooked = $installed && $headerHookId && (int) Db::getInstance()->getValue('SELECT id_module FROM `' . _DB_PREFIX_ . 'hook_module` WHERE id_module = ' . (int) $module->id . ' AND id_hook = ' . $headerHookId);
if ($installed && !$hooked) {
    if (!$module->uninstall()) {
        fwrite(STDERR, "AppNuno module could not be reset before installation.\n");
        exit(1);
    }
    $installed = false;
}
if (!$installed) {
    if (!$module->install()) {
        $errors = isset($module->_errors) && is_array($module->_errors) ? implode("\n", $module->_errors) : '';
        fwrite(STDERR, "AppNuno module installation failed.\n" . $errors . "\n");
        exit(1);
    }
} elseif (!Module::isEnabled('appnuno')) {
    $module->enable();
}

$defaultLanguage = new Language($idLang);
$defaultLanguage->locale = 'en-GB';
$defaultLanguage->language_code = 'en-gb';
if (!$defaultLanguage->update()) {
    fwrite(STDERR, "Unable to set the English locale.\n");
    exit(1);
}
if (!(int) Language::getIdByIso('lt')) {
    $lithuanian = new Language();
    $lithuanian->name = 'Lietuvių';
    $lithuanian->iso_code = 'lt';
    $lithuanian->language_code = 'lt-lt';
    $lithuanian->locale = 'lt-LT';
    $lithuanian->date_format_lite = 'Y-m-d';
    $lithuanian->date_format_full = 'Y-m-d H:i:s';
    $lithuanian->active = true;
    $lithuanian->is_rtl = false;
    if (!$lithuanian->add()) {
        fwrite(STDERR, "Unable to add Lithuanian.\n");
        $validation = $lithuanian->validateFields(false);
        if (is_string($validation) && $validation !== '') {
            fwrite(STDERR, $validation . "\n");
        }
        exit(1);
    }
}
if (!Employee::employeeExists('limited@example.test')) {
    $adminProfile = (int) Db::getInstance()->getValue('SELECT id_profile FROM `' . _DB_PREFIX_ . 'employee` WHERE email = "admin@example.test"');
    $profile = new Profile();
    $profileNames = array();
    $profileLanguages = Language::getLanguages(false);
    if (is_array($profileLanguages)) {
        foreach ($profileLanguages as $language) {
            if (isset($language['id_lang'])) {
                $profileNames[(int) $language['id_lang']] = 'AppNuno Limited';
            }
        }
    }
    $profile->name = $profileNames;
    if (!$profile->add()) {
        fwrite(STDERR, "Unable to create the limited employee profile.\n");
        exit(1);
    }
    $updateRole = (int) Db::getInstance()->getValue('SELECT id_authorization_role FROM `' . _DB_PREFIX_ . 'authorization_role` WHERE slug = "ROLE_MOD_TAB_ADMINAPPNUNO_UPDATE"');
    $access = Db::getInstance()->executeS('SELECT id_authorization_role FROM `' . _DB_PREFIX_ . 'access` WHERE id_profile = ' . $adminProfile);
    if (is_array($access)) {
        foreach ($access as $row) {
            if (!isset($row['id_authorization_role'])) {
                continue;
            }
            $roleId = (int) $row['id_authorization_role'];
            if ($updateRole && $roleId === $updateRole) {
                continue;
            }
            Db::getInstance()->insert('access', array(
                'id_profile' => (int) $profile->id,
                'id_authorization_role' => $roleId,
            ));
        }
    }
    $limited = new Employee();
    $limited->firstname = 'Limited';
    $limited->lastname = 'User';
    $limited->email = 'limited@example.test';
    $limited->passwd = (new PrestaShop\PrestaShop\Core\Crypto\Hashing())->hash('appnuno-local');
    $limited->id_profile = (int) $profile->id;
    $limited->id_lang = $idLang;
    $limited->active = 1;
    $limited->default_tab = (int) Db::getInstance()->getValue('SELECT id_tab FROM `' . _DB_PREFIX_ . 'tab` WHERE class_name = "AdminDashboard"');
    if (!$limited->add()) {
        fwrite(STDERR, "Unable to create the limited employee.\n");
        $validation = $limited->validateFields(false);
        if (is_string($validation) && $validation !== '') {
            fwrite(STDERR, $validation . "\n");
        }
        exit(1);
    }
}
$updateRole = (int) Db::getInstance()->getValue('SELECT id_authorization_role FROM `' . _DB_PREFIX_ . 'authorization_role` WHERE slug = "ROLE_MOD_TAB_ADMINAPPNUNO_UPDATE"');
$limitedProfile = (int) Db::getInstance()->getValue('SELECT id_profile FROM `' . _DB_PREFIX_ . 'employee` WHERE email = "limited@example.test"');
if ($updateRole && $limitedProfile) {
    Db::getInstance()->delete('access', 'id_profile = ' . (int) $limitedProfile . ' AND id_authorization_role = ' . (int) $updateRole);
}

$domain = getenv('APPNUNO_PUBLIC_DOMAIN');
if (!is_string($domain) || $domain === '') {
    $domain = '127.0.0.1:8088';
}
Configuration::updateValue('PS_SHOP_DOMAIN', $domain);
Configuration::updateValue('PS_SHOP_DOMAIN_SSL', $domain);
Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'shop_url` SET domain = "' . pSQL($domain) . '", domain_ssl = "' . pSQL($domain) . '"');
Configuration::updateValue('PS_REWRITING_SETTINGS', 1);
if (!Tools::generateHtaccess(_PS_ROOT_DIR_ . '/.htaccess') || !is_file(_PS_ROOT_DIR_ . '/.htaccess')) {
    $htaccess = "<IfModule mod_rewrite.c>\n<IfModule mod_env.c>\nSetEnv HTTP_MOD_REWRITE On\n</IfModule>\nRewriteEngine on\nRewriteCond %{HTTP:Authorization} ^(.*)\nRewriteRule . - [E=HTTP_AUTHORIZATION:%1]\nRewriteRule . - [E=REWRITEBASE:/]\nRewriteRule ^api(?:/(.*))?$ %{ENV:REWRITEBASE}webservice/dispatcher.php?url=$1 [QSA,L]\nRewriteRule ^upload/.+$ %{ENV:REWRITEBASE}index.php [QSA,L]\nRewriteCond %{REQUEST_FILENAME} -s [OR]\nRewriteCond %{REQUEST_FILENAME} -l [OR]\nRewriteCond %{REQUEST_FILENAME} -d\nRewriteRule ^.*$ - [NC,L]\nRewriteRule ^.*$ %{ENV:REWRITEBASE}index.php [NC,L]\n</IfModule>\n";
    if (file_put_contents(_PS_ROOT_DIR_ . '/.htaccess', $htaccess) === false) {
        fwrite(STDERR, "Unable to enable PrestaShop friendly URLs.\n");
        exit(1);
    }
}

$existingProduct = (int) Db::getInstance()->getValue('SELECT id_product FROM `' . _DB_PREFIX_ . 'product_lang` WHERE link_rewrite = "appnuno-sample"');
if (!$existingProduct) {
    $names = array();
    $rewrites = array();
    $languages = Language::getLanguages(false);
    if (is_array($languages)) {
        foreach ($languages as $language) {
            if (!isset($language['id_lang'])) {
                continue;
            }
            $languageId = (int) $language['id_lang'];
            $names[$languageId] = 'AppNuno Sample';
            $rewrites[$languageId] = 'appnuno-sample';
        }
    }
    $product = new Product();
    $product->name = $names;
    $product->link_rewrite = $rewrites;
    $product->price = 10;
    $product->active = 1;
    $product->visibility = 'both';
    $product->available_for_order = 1;
    $product->show_price = 1;
    $product->id_category_default = 2;
    $product->id_tax_rules_group = 0;
    $product->minimal_quantity = 1;
    $product->redirect_type = '404';
    $product->condition = 'new';
    $product->indexed = 1;
    $product->id_shop_default = $idShop;
    $product->id_shop_list = array($idShop);
    if (property_exists($product, 'product_type')) {
        $product->product_type = 'standard';
    }
    if (!$product->add()) {
        fwrite(STDERR, "Unable to create the sample product.\n");
        $validation = $product->validateFields(false);
        if (is_string($validation) && $validation !== '') {
            fwrite(STDERR, $validation . "\n");
        }
        exit(1);
    }
    if (method_exists($product, 'updateCategories')) {
        $product->updateCategories(array(2));
    } else {
        $product->addToCategories(array(2));
    }
    StockAvailable::setQuantity((int) $product->id, 0, 10, $idShop);
}

if (!Customer::customerExists('customer@example.test')) {
    $customer = new Customer();
    $customer->firstname = 'App';
    $customer->lastname = 'Nuno';
    $customer->email = 'customer@example.test';
    $customer->passwd = (new PrestaShop\PrestaShop\Core\Crypto\Hashing())->hash('appnuno-local');
    $customer->id_lang = $idLang;
    $customer->id_shop = $idShop;
    $customer->active = 1;
    if (!$customer->add()) {
        fwrite(STDERR, "Unable to create the sample customer.\n");
        $validation = $customer->validateFields(false);
        if (is_string($validation) && $validation !== '') {
            fwrite(STDERR, $validation . "\n");
        }
        exit(1);
    }
}

Configuration::updateValue('module_appnuno_status', '0');
Configuration::updateValue('module_appnuno_config', AppnunoConfig::defaultConfig(), true);
if (method_exists('Tools', 'clearSf2Cache')) {
    Tools::clearSf2Cache();
}
if (method_exists('Tools', 'clearSmartyCache')) {
    Tools::clearSmartyCache();
}
if (class_exists('Cache')) {
    Cache::clean('*');
}
passthru('chown -R www-data:www-data ' . escapeshellarg(_PS_ROOT_DIR_ . '/var') . ' ' . escapeshellarg($moduleDir));
fwrite(STDOUT, "AppNuno PrestaShop module is installed.\n");
