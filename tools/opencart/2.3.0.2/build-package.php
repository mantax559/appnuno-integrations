<?php
$repository = dirname(__DIR__, 3);
$source = $repository . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'opencart' . DIRECTORY_SEPARATOR . '2.3.0.2';
$release = $repository . DIRECTORY_SEPARATOR . 'downloads' . DIRECTORY_SEPARATOR . 'opencart' . DIRECTORY_SEPARATOR . '2.x';
$package = $release . DIRECTORY_SEPARATOR . 'appnuno-opencart-2.x.ocmod.zip';
$readme = $release . DIRECTORY_SEPARATOR . 'README.md';
if (!is_dir($release) && !mkdir($release, 0775, true) && !is_dir($release)) {
    throw new RuntimeException('Unable to create OpenCart release directory.');
}
$entries = array(
    'install.xml' => $source . DIRECTORY_SEPARATOR . 'install.xml',
    'README.md' => $readme
);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source . DIRECTORY_SEPARATOR . 'upload', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->isFile()) {
        $path = substr($file->getPathname(), strlen($source) + 1);
        $entries[str_replace(DIRECTORY_SEPARATOR, '/', $path)] = $file->getPathname();
    }
}
$shared_controller = file_get_contents($source . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'controller' . DIRECTORY_SEPARATOR . 'extension' . DIRECTORY_SEPARATOR . 'module' . DIRECTORY_SEPARATOR . 'appnuno.php');
$legacy_controller = str_replace(
    array(
        'class ControllerExtensionModuleAppnuno',
        "url->link('extension/module/appnuno'",
        "url->link('extension/extension', 'token=' . \$this->session->data['token'] . '&type=module', true)",
        "addPermission(\$this->user->getGroupId(), 'access', 'extension/module/appnuno')",
        "addPermission(\$this->user->getGroupId(), 'modify', 'extension/module/appnuno')",
        "hasPermission('modify', 'extension/module/appnuno')"
    ),
    array(
        'class ControllerModuleAppnuno',
        "url->link('module/appnuno'",
        "url->link('extension/module', 'token=' . \$this->session->data['token'], true)",
        "addPermission(\$this->user->getGroupId(), 'access', 'module/appnuno')",
        "addPermission(\$this->user->getGroupId(), 'modify', 'module/appnuno')",
        "hasPermission('modify', 'module/appnuno')"
    ),
    $shared_controller
);
$legacy_controller_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'appnuno-legacy-controller-' . bin2hex(random_bytes(8)) . '.php';
if ($legacy_controller === $shared_controller || file_put_contents($legacy_controller_file, $legacy_controller) === false) {
    throw new RuntimeException('Unable to prepare the OpenCart 2.2 module controller.');
}
$entries['upload/admin/controller/module/appnuno.php'] = $legacy_controller_file;
foreach (array('en-gb', 'lt-lt', 'de-de') as $language_code) {
    $language_file = $source . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'language' . DIRECTORY_SEPARATOR . $language_code . DIRECTORY_SEPARATOR . 'extension' . DIRECTORY_SEPARATOR . 'module' . DIRECTORY_SEPARATOR . 'appnuno.php';
    $entries['upload/admin/language/' . $language_code . '/module/appnuno.php'] = $language_file;
}
ksort($entries, SORT_STRING);
$staging = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'appnuno-opencart-package-' . bin2hex(random_bytes(12));
if (!mkdir($staging, 0700)) {
    throw new RuntimeException('Unable to create OpenCart package staging directory.');
}
$archive = new ZipArchive();
$archive_open = false;
try {
    foreach ($entries as $name => $path) {
        $staged = $staging . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
        $directory = dirname($staged);
        if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
            throw new RuntimeException('Unable to create OpenCart package staging path.');
        }
        if (!copy($path, $staged) || !chmod($staged, 0644) || !touch($staged, 946684800)) {
            throw new RuntimeException('Unable to normalize an OpenCart package file.');
        }
    }
    $result = $archive->open($package, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if (!$result) {
        throw new RuntimeException('Unable to create OpenCart extension archive.');
    }
    $archive_open = true;
    foreach ($entries as $name => $path) {
        $staged = $staging . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
        if (!$archive->addFile($staged, $name)
            || !$archive->setCompressionName($name, ZipArchive::CM_DEFLATE, 9)
            || !$archive->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, 0100644 << 16)) {
            throw new RuntimeException('Unable to add a normalized file to the OpenCart extension archive.');
        }
    }
    if (!$archive->close()) {
        throw new RuntimeException('Unable to finish OpenCart extension archive.');
    }
    $archive_open = false;
} finally {
    if ($archive_open) {
        $archive->close();
    }
    $staged_files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($staging, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($staged_files as $staged_file) {
        if ($staged_file->isDir()) {
            rmdir($staged_file->getPathname());
        } else {
            unlink($staged_file->getPathname());
        }
    }
    rmdir($staging);
    if (is_file($legacy_controller_file)) {
        unlink($legacy_controller_file);
    }
}
echo $package . PHP_EOL;
