<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('module_appnuno_status');
delete_option('module_appnuno_config');
