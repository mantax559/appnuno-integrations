<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/classes/AppnunoPaths.php';
require_once dirname(__FILE__) . '/classes/AppnunoCatalog.php';
require_once dirname(__FILE__) . '/classes/AppnunoAdmin.php';
require_once dirname(__FILE__) . '/classes/AppnunoStorefront.php';

class Appnuno extends Module
{
    public function __construct()
    {
        $this->name = 'appnuno';
        $this->tab = 'front_office_features';
        $this->version = '__APPNUNO_VERSION__';
        $this->author = 'AppNuno';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = array(
            'min' => '__PRESTASHOP_MIN_VERSION__',
            'max' => '__PRESTASHOP_MAX_VERSION__',
        );
        parent::__construct();
        $this->displayName = 'AppNuno';
        $this->description = 'AppNuno native navigation for PrestaShop.';
        $this->confirmUninstall = 'Deleting the module removes the saved AppNuno settings.';
        AppnunoPaths::loadConfig();
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('displayHeader')
            && $this->registerHook('actionFrontControllerInitAfter')
            && $this->installTab()
            && Configuration::updateValue('module_appnuno_status', '0')
            && Configuration::updateValue('module_appnuno_config', AppnunoConfig::defaultConfig(), true);
    }

    public function uninstall()
    {
        $this->uninstallTab();
        Configuration::deleteByName('module_appnuno_status');
        Configuration::deleteByName('module_appnuno_config');

        return parent::uninstall();
    }

    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminAppnuno'));

        return '';
    }

    public function hookDisplayHeader($params)
    {
        return AppnunoStorefront::headerScript();
    }

    public function hookActionFrontControllerInitAfter($params)
    {
        AppnunoStorefront::rememberCustomer();
    }

    private function installTab()
    {
        $id = (int) Db::getInstance()->getValue('SELECT id_tab FROM `' . _DB_PREFIX_ . 'tab` WHERE class_name = "AdminAppnuno"');
        if (!$id) {
            $tab = new Tab();
            $tab->active = 1;
            $tab->class_name = 'AdminAppnuno';
            $tab->module = $this->name;
            $tab->id_parent = version_compare(_PS_VERSION_, '9.0.0', '<')
                ? (int) Db::getInstance()->getValue('SELECT id_tab FROM `' . _DB_PREFIX_ . 'tab` WHERE class_name = "AdminParentModulesSf"')
                : 0;
            $tab->icon = 'explore';
            $tab->enabled = 1;
            $languages = Language::getLanguages(false);
            if (is_array($languages)) {
                foreach ($languages as $language) {
                    if (isset($language['id_lang'])) {
                        $tab->name[(int) $language['id_lang']] = 'AppNuno';
                    }
                }
            }
            if (!$tab->add()) {
                return false;
            }
            $id = (int) $tab->id;
        }
        $this->grantTabAccess();

        return $id > 0;
    }

    private function uninstallTab()
    {
        $id = (int) Db::getInstance()->getValue('SELECT id_tab FROM `' . _DB_PREFIX_ . 'tab` WHERE class_name = "AdminAppnuno"');
        if ($id) {
            $tab = new Tab($id);
            $tab->delete();
        }
        foreach (array('CREATE', 'READ', 'UPDATE', 'DELETE') as $action) {
            $roleId = (int) Db::getInstance()->getValue('SELECT id_authorization_role FROM `' . _DB_PREFIX_ . 'authorization_role` WHERE slug = "' . pSQL('ROLE_MOD_TAB_ADMINAPPNUNO_' . $action) . '"');
            if ($roleId) {
                Db::getInstance()->delete('access', 'id_authorization_role = ' . $roleId);
                Db::getInstance()->delete('authorization_role', 'id_authorization_role = ' . $roleId);
            }
        }
    }

    private function grantTabAccess()
    {
        $profiles = Db::getInstance()->executeS('SELECT id_profile FROM `' . _DB_PREFIX_ . 'profile`');
        if (!is_array($profiles)) {
            return;
        }
        foreach (array('CREATE', 'READ', 'UPDATE', 'DELETE') as $action) {
            $slug = 'ROLE_MOD_TAB_ADMINAPPNUNO_' . $action;
            $roleId = (int) Db::getInstance()->getValue('SELECT id_authorization_role FROM `' . _DB_PREFIX_ . 'authorization_role` WHERE slug = "' . pSQL($slug) . '"');
            if (!$roleId) {
                Db::getInstance()->insert('authorization_role', array('slug' => pSQL($slug)));
                $roleId = (int) Db::getInstance()->Insert_ID();
            }
            if (!$roleId) {
                continue;
            }
            foreach ($profiles as $profile) {
                if (!isset($profile['id_profile'])) {
                    continue;
                }
                $idProfile = (int) $profile['id_profile'];
                $exists = (int) Db::getInstance()->getValue('SELECT id_authorization_role FROM `' . _DB_PREFIX_ . 'access` WHERE id_profile = ' . $idProfile . ' AND id_authorization_role = ' . $roleId);
                if (!$exists) {
                    Db::getInstance()->insert('access', array(
                        'id_profile' => $idProfile,
                        'id_authorization_role' => $roleId,
                    ));
                }
            }
        }
    }
}
