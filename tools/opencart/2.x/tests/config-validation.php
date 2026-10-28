<?php
class Model {}
require dirname(__DIR__, 4) . '/src/opencart/2.x/upload/admin/model/extension/module/appnuno.php';
$model = new ModelExtensionModuleAppnuno();
$default = $model->defaultConfig();
if (strlen($model->validateConfig($default))) {
    throw new RuntimeException('Expected default OpenCart navigation configuration to be valid.');
}
$default_values = json_decode($default, true);
if (!in_array($default_values['glass']['colorScheme'], array('auto'), true) || !in_array($default_values['glass']['style'], array('regular'), true) || !in_array($default_values['glass']['animate'], array(true), true) || !in_array($default_values['glass']['animationDuration'], array(0.3), true) || !in_array($default_values['glass']['isInteractive'], array(true), true) || !in_array($default_values['glass']['tintColor'], array('rgba(0, 0, 0, 0.02)'), true)) {
    throw new RuntimeException('Expected native-contract glass defaults.');
}
if (!in_array($default_values['colors']['light']['backgroundColor'], array('#FFFFFF'), true) || !in_array($default_values['colors']['light']['contentColor'], array('#2C3136'), true) || !in_array($default_values['colors']['dark']['backgroundColor'], array('#2C3136'), true) || !in_array($default_values['colors']['dark']['contentColor'], array('#FFFFFF'), true)) {
    throw new RuntimeException('Expected native-contract light and dark colors.');
}
$valid = json_encode(array(
    'cartTabId' => 'cart',
    'defaultTabs' => array(
        array('id' => 'home', 'labels' => array('en-gb' => 'Home', 'lt-lt' => 'Pradžia'), 'url' => '/', 'icon' => 'home'),
        array('id' => 'cart', 'labels' => array('en-gb' => 'Cart', 'lt-lt' => 'Krepšelis'), 'url' => '/index.php?route=checkout/cart')
    ),
    'routes' => array('checkout/cart' => array(array('id' => 'cart', 'labels' => array('en-gb' => 'Bag'), 'url' => '/index.php?route=checkout/cart'))),
    'glass' => array('enabled' => true, 'colorScheme' => 'auto', 'style' => 'regular', 'animate' => true, 'animationDuration' => 0.3, 'isInteractive' => true, 'tintColor' => 'rgba(0, 0, 0, 0.02)'),
    'colors' => array('light' => array('backgroundColor' => '#FFFFFF', 'contentColor' => '#2C3136'), 'dark' => array('backgroundColor' => '#2C3136', 'contentColor' => '#FFFFFF'))
));
if (strlen($model->validateConfig($valid))) {
    throw new RuntimeException('Expected valid multilingual tabs, local destinations, routes and visuals.');
}
$form_config = json_encode(array(
    'defaultTabs' => array(array('id' => 'ci-added-tab', 'labels' => array('en-gb' => 'CI "Added" & Tab'), 'url' => '/index.php?route=information/contact')),
    'routes' => (object)array()
));
$request_cleaned_config = htmlspecialchars($form_config, ENT_COMPAT, 'UTF-8');
$restored_form_config = htmlspecialchars_decode($request_cleaned_config, ENT_COMPAT);
if (strcmp($restored_form_config, $form_config) || strlen($model->validateConfig($restored_form_config))) {
    throw new RuntimeException('Expected OpenCart-cleaned JSON form data to be restored and validated.');
}
$localized = json_encode(array(
    'defaultTabs' => array(array('id' => 'home', 'labels' => array('en-gb' => 'Home', 'lt-lt' => 'Pradžia'), 'url' => '/')),
    'routes' => array()
));
if (strlen($model->validateConfig($localized))) {
    throw new RuntimeException('Expected translated tab labels to be valid.');
}
$localized_urls = json_encode(array(
    'defaultTabs' => array(array('id' => 'catalog', 'labels' => array('en-gb' => 'Catalog', 'lt-lt' => 'Katalogas'), 'url' => '/en/catalog', 'urls' => array('en-gb' => '/en/catalog', 'lt-lt' => '/lt/katalogas'))),
    'routes' => array()
));
if (strlen($model->validateConfig($localized_urls, array('en-gb', 'lt-lt')))) {
    throw new RuntimeException('Expected custom local URLs for every configured language.');
}
if (!strstr($model->validateConfig($localized_urls, array('en-gb', 'lt-lt', 'de-de')), 'defaultTabs[0].urls.de-de')) {
    throw new RuntimeException('Expected a custom URL to be required even for a disabled installed language.');
}
$invalid_localized_url = json_encode(array(
    'defaultTabs' => array(array('id' => 'catalog', 'labels' => array('en-gb' => 'Catalog'), 'url' => '/catalog', 'urls' => array('en-gb' => 'https://external.example/catalog'))),
    'routes' => array()
));
if (!strstr($model->validateConfig($invalid_localized_url), 'defaultTabs[0].urls.en-gb')) {
    throw new RuntimeException('Expected external localized URLs to be rejected with the language path.');
}
$invalid_labels = json_encode(array(
    'defaultTabs' => array(array('id' => 'home', 'labels' => array('en-gb' => ''), 'url' => '/')),
    'routes' => array()
));
if (!strlen($model->validateConfig($invalid_labels)) || !strstr($model->validateConfig($invalid_labels), 'defaultTabs[0].labels')) {
    throw new RuntimeException('Expected an actionable error for invalid tab translations.');
}
$invalid = json_encode(array(
    'defaultTabs' => array(array('id' => 'cart', 'label' => 'Cart', 'url' => '//attacker.example')),
    'routes' => array()
));
if (!strstr($model->validateConfig($invalid), 'defaultTabs[0].url')) {
    throw new RuntimeException('Expected protocol-relative tab URL to be rejected with its field path.');
}
if (!strstr($model->validateConfig('{'), 'module_appnuno_config')) {
    throw new RuntimeException('Expected malformed JSON to return an actionable configuration error.');
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
$invalid_route = json_encode(array('defaultTabs' => array(), 'routes' => array('checkout/cart?redirect=home' => array())));
if (!strstr($model->validateConfig($invalid_route), 'routes')) {
    throw new RuntimeException('Expected malformed OpenCart route names to be rejected.');
}
$invalid_color = json_encode(array('defaultTabs' => array(), 'routes' => array(), 'colors' => array('light' => array('backgroundColor' => 'red'))));
if (!strlen($model->validateConfig($invalid_color))) {
    throw new RuntimeException('Expected invalid color values to be rejected.');
}
$invalid_tint = json_encode(array('defaultTabs' => array(), 'routes' => array(), 'glass' => array('tintColor' => 'rgba(0, 0, 0, 1.5)')));
if (!strlen($model->validateConfig($invalid_tint))) {
    throw new RuntimeException('Expected out-of-range glass tint opacity to be rejected.');
}
$valid_visuals = json_encode(array('defaultTabs' => array(), 'routes' => array(), 'glass' => array('enabled' => true, 'animationDuration' => 0.3, 'tintColor' => 'rgba(0, 0, 0, 0.02)'), 'colors' => array('light' => array('backgroundColor' => '#ffffff'))));
if (strlen($model->validateConfig($valid_visuals))) {
    throw new RuntimeException('Expected valid glass and color settings to be accepted.');
}
$invalid_visuals = json_encode(array('defaultTabs' => array(), 'routes' => array(), 'glass' => array('animationDuration' => -0.3)));
if (!strlen($model->validateConfig($invalid_visuals))) {
    throw new RuntimeException('Expected negative glass duration to be rejected.');
}
$invalid_duration = json_encode(array('defaultTabs' => array(), 'routes' => array(), 'glass' => array('animationDuration' => 10.1)));
if (!strstr($model->validateConfig($invalid_duration), 'glass.animationDuration')) {
    throw new RuntimeException('Expected animation duration above the control limit to be rejected.');
}
$invalid_back = json_encode(array('defaultTabs' => array(), 'routes' => array(), 'back' => array('enabled' => 1)));
if (!strlen($model->validateConfig($invalid_back))) {
    throw new RuntimeException('Expected non-boolean back setting to be rejected.');
}
$visible_pages = json_encode(array('defaultTabs' => array(array('id' => 'cart', 'label' => 'Cart', 'url' => '/', 'pages' => array('checkout/cart'))), 'routes' => array()));
if (strlen($model->validateConfig($visible_pages))) {
    throw new RuntimeException('Expected a tab limited to a store page to be valid.');
}
$invalid_pages = json_encode(array('defaultTabs' => array(array('id' => 'cart', 'label' => 'Cart', 'url' => '/', 'pages' => array('checkout/cart?redirect=home'))), 'routes' => array()));
if (!strstr($model->validateConfig($invalid_pages), 'defaultTabs[0].pages')) {
    throw new RuntimeException('Expected an invalid store page selection to be rejected.');
}
echo "OpenCart navigation configuration tests passed.\n";
