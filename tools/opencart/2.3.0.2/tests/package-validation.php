<?php
$repository = dirname(__DIR__, 4);
$package = $repository . DIRECTORY_SEPARATOR . 'downloads' . DIRECTORY_SEPARATOR . 'opencart' . DIRECTORY_SEPARATOR . '2.3.x' . DIRECTORY_SEPARATOR . 'appnuno-opencart-2.3.x.ocmod.zip';
$required = array(
    'install.xml',
    'README.md',
    'upload/admin/controller/extension/module/appnuno.php',
    'upload/admin/language/english/extension/module/appnuno.php',
    'upload/admin/model/extension/module/appnuno.php',
    'upload/admin/view/template/extension/module/appnuno.tpl',
    'upload/catalog/controller/extension/module/appnuno.php',
    'upload/catalog/model/extension/module/appnuno.php'
);
$archive = new ZipArchive();
if (!$archive->open($package)) {
    throw new RuntimeException('Unable to open OpenCart extension archive.');
}
$entries = array();
for ($index = 0; $index < $archive->numFiles; $index++) {
    $name = $archive->getNameIndex($index);
    $entries[] = $name;
    if (preg_match('~^/|(^|/)\.\.(/|$)|(^|/)tests(/|$)~', $name)) {
        throw new RuntimeException('Unsafe or non-installable archive entry: ' . $name);
    }
}
$view = $archive->getFromName('upload/admin/view/template/extension/module/appnuno.tpl');
$controller = $archive->getFromName('upload/admin/controller/extension/module/appnuno.php');
$catalog_controller = $archive->getFromName('upload/catalog/controller/extension/module/appnuno.php');
$modification = $archive->getFromName('install.xml');
$readme = $archive->getFromName('README.md');
$archive->close();
if (!is_string($view) || !strstr($view, "'language/' + language.code + '/' + language.code + '.png'") || !strstr($view, 'data-language-url') || !strstr($view, 'Custom URL') || !strstr($view, 'Custom route') || !strstr($view, 'type="color"') || !strstr($view, 'rgba(0, 0, 0, 0.02)')) {
    throw new RuntimeException('OpenCart archive is missing the updated settings controls and defaults.');
}
if (!is_string($controller) || !strstr($controller, '$posted_configuration') || !strstr($controller, "['defaultTabs']") || !strstr($controller, "['routes']") || !strstr($controller, "deleteEvent('appnuno')") || !strstr($controller, "addEvent('appnuno', 'catalog/view/common/header/before', 'extension/module/appnuno/inject')")) {
    throw new RuntimeException('OpenCart archive is missing safe configuration handling or catalog event repair on save.');
}
if (!is_string($catalog_controller) || strstr($catalog_controller, '->validateConfig(') || !strstr($catalog_controller, "['urls']") || !strstr($catalog_controller, "['urls'][\$language_code]") || !strstr($catalog_controller, 'unset($tab[\'urls\'])') || !strstr($catalog_controller, "['defaultTabs']")) {
    throw new RuntimeException('OpenCart archive is missing a valid bootstrap configuration path or localized URL resolution.');
}
if (!is_string($modification) || !strstr($modification, 'catalog/controller/common/header.php') || !strstr($modification, '$data[\'scripts\'] = $this->document->getScripts();') || !strstr($modification, "extension/module/appnuno/bootstrap")) {
    throw new RuntimeException('OpenCart archive is missing direct injection through the shared catalog header.');
}
if (!is_string($readme) || !strstr($readme, 'Extension Installer') || !strstr($readme, '2.3.0.2')) {
    throw new RuntimeException('OpenCart archive is missing its installation and compatibility guide.');
}
$missing = array_diff($required, $entries);
if (count($missing)) {
    throw new RuntimeException('Missing OpenCart package files: ' . implode(', ', $missing));
}
echo "OpenCart package structure tests passed.\n";
