<?php
$repository = dirname(__DIR__, 3);
$source = $repository . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'opencart' . DIRECTORY_SEPARATOR . '2.3.0.2';
$release = $repository . DIRECTORY_SEPARATOR . 'downloads' . DIRECTORY_SEPARATOR . 'opencart' . DIRECTORY_SEPARATOR . '2.3.x';
$package = $release . DIRECTORY_SEPARATOR . 'appnuno-opencart-2.3.x.ocmod.zip';
$readme = $release . DIRECTORY_SEPARATOR . 'README.md';
if (!is_dir($release) && !mkdir($release, 0775, true) && !is_dir($release)) {
    throw new RuntimeException('Unable to create OpenCart release directory.');
}
$archive = new ZipArchive();
$result = $archive->open($package, ZipArchive::CREATE | ZipArchive::OVERWRITE);
if (is_int($result) || !is_bool($result) || !$result) {
    throw new RuntimeException('Unable to create OpenCart extension archive.');
}
$archive->addFile($source . DIRECTORY_SEPARATOR . 'install.xml', 'install.xml');
$archive->addFile($readme, 'README.md');
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source . DIRECTORY_SEPARATOR . 'upload', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->isFile()) {
        $path = substr($file->getPathname(), strlen($source) + 1);
        $archive->addFile($file->getPathname(), str_replace(DIRECTORY_SEPARATOR, '/', $path));
    }
}
if (!$archive->close()) {
    throw new RuntimeException('Unable to finish OpenCart extension archive.');
}
echo $package . PHP_EOL;
