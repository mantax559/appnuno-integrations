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
}
echo $package . PHP_EOL;
