<?php
class Model {}
require dirname(__DIR__, 4) . '/src/opencart/2.3.0.2/upload/admin/model/extension/module/appnuno.php';
$model = new ModelExtensionModuleAppnuno();
$default = $model->defaultConfig();
if (strlen($model->validateConfig($default))) {
    throw new RuntimeException('Expected default OpenCart navigation configuration to be valid.');
}
$valid = json_encode(array(
    'cartTabId' => 'cart',
    'defaultTabs' => array(
        array('id' => 'home', 'label' => 'Home', 'url' => '/', 'icon' => 'home'),
        array('id' => 'cart', 'label' => 'Cart', 'url' => '/index.php?route=checkout/cart')
    ),
    'routes' => array('checkout/cart' => array(array('id' => 'cart', 'label' => 'Bag', 'url' => '/index.php?route=checkout/cart')))
));
if (strlen($model->validateConfig($valid))) {
    throw new RuntimeException('Expected valid OpenCart navigation configuration.');
}
$invalid = json_encode(array(
    'defaultTabs' => array(array('id' => 'cart', 'label' => 'Cart', 'url' => '//attacker.example')),
    'routes' => array()
));
if (!strlen($model->validateConfig($invalid))) {
    throw new RuntimeException('Expected protocol-relative tab URL to be rejected.');
}
if (!strlen($model->validateConfig('{'))) {
    throw new RuntimeException('Expected malformed JSON to be rejected.');
}
$backslash = json_encode(array('defaultTabs' => array(array('id' => 'home', 'label' => 'Home', 'url' => '/bad\\path')), 'routes' => array()));
if (!strlen($model->validateConfig($backslash))) {
    throw new RuntimeException('Expected backslashes in tab URLs to be rejected.');
}
$invalid_badge = json_encode(array('defaultTabs' => array(array('id' => 'cart', 'label' => 'Cart', 'url' => '/', 'badge' => -1)), 'routes' => array()));
if (!strlen($model->validateConfig($invalid_badge))) {
    throw new RuntimeException('Expected negative badge counts to be rejected.');
}
$invalid_identity = json_encode(array('defaultTabs' => array(array('id' => 'cart', 'label' => 'Cart', 'url' => '/', 'active' => 'true')), 'routes' => array()));
if (!strlen($model->validateConfig($invalid_identity))) {
    throw new RuntimeException('Expected non-boolean active state to be rejected.');
}
$valid_visuals = json_encode(array('defaultTabs' => array(), 'routes' => array(), 'glass' => array('enabled' => true, 'animationDuration' => 0.3), 'colors' => array('light' => array('backgroundColor' => '#ffffff'))));
if (strlen($model->validateConfig($valid_visuals))) {
    throw new RuntimeException('Expected valid glass and color settings to be accepted.');
}
$invalid_visuals = json_encode(array('defaultTabs' => array(), 'routes' => array(), 'glass' => array('animationDuration' => -0.3)));
if (!strlen($model->validateConfig($invalid_visuals))) {
    throw new RuntimeException('Expected negative glass duration to be rejected.');
}
$invalid_back = json_encode(array('defaultTabs' => array(), 'routes' => array(), 'back' => array('enabled' => 1)));
if (!strlen($model->validateConfig($invalid_back))) {
    throw new RuntimeException('Expected non-boolean back setting to be rejected.');
}
echo "OpenCart navigation configuration tests passed.\n";
