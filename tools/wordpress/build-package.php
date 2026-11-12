<?php
$package_lines = array(
    '5' => array('directory' => '5.x', 'requires_at_least' => '5.4', 'requires_php' => '7.0', 'strip_void' => true),
    '6' => array('directory' => '6.x', 'requires_at_least' => '5.6', 'requires_php' => '7.0', 'strip_void' => true),
    '7' => array('directory' => '7.x', 'requires_at_least' => '5.8', 'requires_php' => '7.2'),
    '8' => array('directory' => '8.x', 'requires_at_least' => '6.2', 'requires_php' => '7.3'),
    '9' => array('directory' => '9.x', 'requires_at_least' => '6.4', 'requires_php' => '7.4'),
    '10' => array('directory' => '10.x', 'requires_at_least' => '6.7', 'requires_php' => '7.4'),
    '11' => array('directory' => '11.x', 'requires_at_least' => '6.9', 'requires_php' => '7.4'),
);
$package_line = '11';
if (isset($argv[1])) {
    if (!is_string($argv[1]) || !isset($package_lines[$argv[1]])) {
        throw new RuntimeException('The WooCommerce package line must be 5, 6, 7, 8, 9, 10, or 11.');
    }
    $package_line = $argv[1];
}
wordpress_build_package($package_lines[$package_line]);

function wordpress_build_package(array $package_line): void {
    $repository = dirname(__DIR__, 2);
    $source = $repository . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'wordpress' . DIRECTORY_SEPARATOR . 'appnuno';
    $shared_admin = $repository . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'admin';
    $release = $repository . DIRECTORY_SEPARATOR . 'downloads' . DIRECTORY_SEPARATOR . 'woocommerce' . DIRECTORY_SEPARATOR . $package_line['directory'];
    $platform_versions = json_decode(file_get_contents($repository . DIRECTORY_SEPARATOR . 'platform-versions.json'), true);
    if (!is_array($platform_versions) || empty($platform_versions['wordpress'])) {
        throw new RuntimeException('The WooCommerce integration version is missing from platform-versions.json.');
    }
    $version = $platform_versions['wordpress'];
    if (!is_dir($release) && !mkdir($release, 0775, true) && !is_dir($release)) {
        throw new RuntimeException('Unable to create the WooCommerce release directory.');
    }
    $package = $release . DIRECTORY_SEPARATOR . 'appnuno-woocommerce-' . $package_line['directory'] . '.zip';
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
            throw new RuntimeException('Unable to read a WooCommerce package file.');
        }
        $body = str_replace('__APPNUNO_VERSION__', $version, $body);
        if (isset($package_line['strip_void']) && substr($name, -4) === '.php') {
            $body = str_replace('): void', ')', $body);
        }
        $prepared[$name] = $body;
    }
    if (!isset($prepared['appnuno/appnuno.php'])) {
        throw new RuntimeException('The WooCommerce plugin header version was not written.');
    }
    $prepared['appnuno/appnuno.php'] = str_replace(
        array('Requires at least: 6.9', 'Requires PHP: 7.4'),
        array('Requires at least: ' . $package_line['requires_at_least'], 'Requires PHP: ' . $package_line['requires_php']),
        $prepared['appnuno/appnuno.php']
    );
    if (strpos($prepared['appnuno/appnuno.php'], 'Version: ' . $version) === false || strpos($prepared['appnuno/appnuno.php'], 'Requires at least: ' . $package_line['requires_at_least']) === false || strpos($prepared['appnuno/appnuno.php'], 'Requires PHP: ' . $package_line['requires_php']) === false) {
        throw new RuntimeException('The WooCommerce plugin header version was not written.');
    }
    ksort($prepared, SORT_STRING);
    $staging = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'appnuno-woocommerce-package-' . bin2hex(random_bytes(12));
    if (!mkdir($staging, 0700)) {
        throw new RuntimeException('Unable to create the WooCommerce package staging directory.');
    }
    $archive = new ZipArchive();
    $archive_open = false;
    try {
        foreach ($prepared as $name => $body) {
            $staged = $staging . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
            $directory = dirname($staged);
            if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
                throw new RuntimeException('Unable to create a WooCommerce package staging path.');
            }
            if (file_put_contents($staged, $body) === false || !chmod($staged, 0644) || !touch($staged, 946684800)) {
                throw new RuntimeException('Unable to normalize a WooCommerce package file.');
            }
        }
        $result = $archive->open($package, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if (!$result) {
            throw new RuntimeException('Unable to create the WooCommerce plugin archive.');
        }
        $archive_open = true;
        foreach ($prepared as $name => $body) {
            $staged = $staging . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
            if (!$archive->addFile($staged, $name)
                || !$archive->setCompressionName($name, ZipArchive::CM_DEFLATE, 9)
                || !$archive->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, 0100644 << 16)
                || !$archive->setMtimeName($name, 946684800)) {
                throw new RuntimeException('Unable to add a normalized file to the WooCommerce plugin archive.');
            }
        }
        if (!$archive->close()) {
            throw new RuntimeException('Unable to finish the WooCommerce plugin archive.');
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
