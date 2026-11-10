<?php
/**
 * Plugin Name: AppNuno
 * Description: AppNuno native navigation for WooCommerce.
 * Version: __APPNUNO_VERSION__
 * Author: AppNuno
 * Author URI: https://appnuno.com
 * Plugin URI: https://appnuno.com/wordpress
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Text Domain: appnuno
 */

if (!defined('ABSPATH')) {
    exit;
}

define('APPNUNO_FILE', __FILE__);
define('APPNUNO_VERSION', '__APPNUNO_VERSION__');

require_once dirname(__FILE__) . '/includes/paths.php';
require_once dirname(__FILE__) . '/includes/catalog.php';
require_once dirname(__FILE__) . '/includes/admin.php';
require_once dirname(__FILE__) . '/includes/storefront.php';

add_action('before_woocommerce_init', 'appnuno_declare_feature_compatibility');
add_action('plugins_loaded', 'appnuno_boot', 20);
register_activation_hook(__FILE__, 'appnuno_activate');

function appnuno_declare_feature_compatibility(): void {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', APPNUNO_FILE, true);
    }
}

function appnuno_activate(): void {
    if (!class_exists('WooCommerce')) {
        return;
    }
}

function appnuno_boot(): void {
    Appnuno_Paths::loadConfig();
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'appnuno_woocommerce_missing_notice');
        return;
    }
    Appnuno_Admin::register();
    Appnuno_Storefront::register();
}

function appnuno_woocommerce_missing_notice(): void {
    if (!current_user_can('activate_plugins')) {
        return;
    }
    echo '<div class="notice notice-error"><p>AppNuno requires WooCommerce.</p></div>';
}
