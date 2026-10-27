<?php
$repository = dirname(__DIR__, 4);
$package = $repository . DIRECTORY_SEPARATOR . 'opencart' . DIRECTORY_SEPARATOR . '2.3.0.2' . DIRECTORY_SEPARATOR . 'appnuno-2.3.0.2.ocmod.zip';
$required = array(
    'install.xml',
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
$archive->close();
$missing = array_diff($required, $entries);
if (count($missing)) {
    throw new RuntimeException('Missing OpenCart package files: ' . implode(', ', $missing));
}
echo "OpenCart package structure tests passed.\n";
