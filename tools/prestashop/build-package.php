<?php
$package_lines = array(
    '17' => array('directory' => '1.7.x', 'minimum' => '1.7.0.0', 'maximum' => '1.7.99.99', 'requires_php' => '5.4'),
    '8' => array('directory' => '8.x', 'minimum' => '8.0.0', 'maximum' => '8.99.99', 'requires_php' => '7.2.5'),
    '9' => array('directory' => '9.x', 'minimum' => '9.0.0', 'maximum' => '9.99.99', 'requires_php' => '8.1'),
);
$package_line = isset($argv[1]) ? $argv[1] : '9';
if (!isset($package_lines[$package_line])) {
    throw new RuntimeException('The PrestaShop package line must be 17, 8 or 9.');
}
prestashop_build_package($package_lines[$package_line]);

function prestashop_build_package(array $package_line): void {
    $repository = dirname(__DIR__, 2);
    $source = $repository . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'prestashop' . DIRECTORY_SEPARATOR . 'appnuno';
    $shared_admin = $repository . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'admin';
    $release = $repository . DIRECTORY_SEPARATOR . 'downloads' . DIRECTORY_SEPARATOR . 'prestashop' . DIRECTORY_SEPARATOR . $package_line['directory'];
    $platform_versions = json_decode(file_get_contents($repository . DIRECTORY_SEPARATOR . 'platform-versions.json'), true);
    if (!is_array($platform_versions) || empty($platform_versions['prestashop'])) {
        throw new RuntimeException('The PrestaShop integration version is missing from platform-versions.json.');
    }
    $version = $platform_versions['prestashop'];
    if (!is_dir($release) && !mkdir($release, 0775, true) && !is_dir($release)) {
        throw new RuntimeException('Unable to create the PrestaShop release directory.');
    }
    $package = $release . DIRECTORY_SEPARATOR . 'appnuno-prestashop-' . $package_line['directory'] . '.zip';
    $entries = array(
        'appnuno/includes/appnuno-config.php' => $repository . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'appnuno-config.php',
        'appnuno/assets/settings.css' => $shared_admin . DIRECTORY_SEPARATOR . 'settings.css',
        'appnuno/assets/settings.js' => $shared_admin . DIRECTORY_SEPARATOR . 'settings.js',
        'appnuno/assets/settings.html' => $shared_admin . DIRECTORY_SEPARATOR . 'settings.html',
        'appnuno/README.md' => $release . DIRECTORY_SEPARATOR . 'README.md'
    );
    foreach (glob($shared_admin . DIRECTORY_SEPARATOR . 'icons' . DIRECTORY_SEPARATOR . '*.svg') as $icon) {
        $entries['appnuno/assets/icons/' . basename($icon)] = $icon;
    }
    foreach (glob($shared_admin . DIRECTORY_SEPARATOR . 'i18n' . DIRECTORY_SEPARATOR . '*.json') as $catalog) {
        $entries['appnuno/assets/i18n/' . basename($catalog)] = $catalog;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $path = substr($file->getPathname(), strlen($source) + 1);
            $entries['appnuno/' . str_replace(DIRECTORY_SEPARATOR, '/', $path)] = $file->getPathname();
        }
    }
    $prepared = array();
    foreach ($entries as $name => $path) {
        $body = file_get_contents($path);
        if (!is_string($body)) {
            throw new RuntimeException('Unable to read a PrestaShop package file.');
        }
        $body = str_replace(
            array('__APPNUNO_VERSION__', '__PRESTASHOP_MIN_VERSION__', '__PRESTASHOP_MAX_VERSION__'),
            array($version, $package_line['minimum'], $package_line['maximum']),
            $body
        );
        $prepared[$name] = $body;
    }
    if (!isset($prepared['appnuno/appnuno.php'])
        || !strpos($prepared['appnuno/appnuno.php'], $version)
        || !strpos($prepared['appnuno/appnuno.php'], "'min' => '" . $package_line['minimum'] . "'")
        || !strpos($prepared['appnuno/appnuno.php'], "'max' => '" . $package_line['maximum'] . "'")) {
        throw new RuntimeException('The PrestaShop module version was not written.');
    }
    ksort($prepared, SORT_STRING);
    $staging = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'appnuno-prestashop-package-' . bin2hex(random_bytes(12));
    if (!mkdir($staging, 0700)) {
        throw new RuntimeException('Unable to create the PrestaShop package staging directory.');
    }
    $archive = new ZipArchive();
    $archive_open = false;
    try {
        foreach ($prepared as $name => $body) {
            $staged = $staging . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
            $directory = dirname($staged);
            if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
                throw new RuntimeException('Unable to create a PrestaShop package staging path.');
            }
            if (file_put_contents($staged, $body) === false || !chmod($staged, 0644) || !touch($staged, 946684800)) {
                throw new RuntimeException('Unable to normalize a PrestaShop package file.');
            }
        }
        $result = $archive->open($package, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if (!$result) {
            throw new RuntimeException('Unable to create the PrestaShop module archive.');
        }
        $archive_open = true;
        foreach ($prepared as $name => $body) {
            $staged = $staging . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
            if (!$archive->addFile($staged, $name)
                || !$archive->setCompressionName($name, ZipArchive::CM_DEFLATE, 9)
                || !$archive->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, 0100644 << 16)
                || !$archive->setMtimeName($name, 946684800)) {
                throw new RuntimeException('Unable to add a normalized file to the PrestaShop module archive.');
            }
        }
        if (!$archive->close()) {
            throw new RuntimeException('Unable to finish the PrestaShop module archive.');
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
    }
    echo $package . PHP_EOL;
}
