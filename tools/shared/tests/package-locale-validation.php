<?php
$repository = dirname(__DIR__, 3);
$packages = array();

foreach (range(1, 11) as $line) {
    $directory = $line . '.x';
    $packages[] = array(
        $repository . '/downloads/woocommerce/' . $directory . '/appnuno-woocommerce-' . $directory . '.zip',
        'appnuno/includes/appnuno-config.php'
    );
}

foreach (array('8', '9') as $line) {
    $directory = $line . '.x';
    $packages[] = array(
        $repository . '/downloads/prestashop/' . $directory . '/appnuno-prestashop-' . $directory . '.zip',
        'appnuno/includes/appnuno-config.php'
    );
}

foreach (array('2.0' => '2.0.x', '2.1' => '2.1.x', '2.2' => '2.2.x', '2.3' => '2.3.x', '3.0' => '3.x') as $directory) {
    $packages[] = array(
        $repository . '/downloads/opencart/' . $directory . '/appnuno-opencart-' . $directory . '.ocmod.zip',
        'upload/system/library/appnuno-config.php'
    );
}

$packages[] = array(
    $repository . '/downloads/opencart/4.x/appnuno.ocmod.zip',
    'system/library/appnuno-config.php'
);

foreach ($packages as $package) {
    $archive = new ZipArchive();
    $opened = $archive->open($package[0]);
    if (!is_bool($opened) || !$opened) {
        throw new RuntimeException('Unable to open integration package: ' . $package[0]);
    }
    $source = $archive->getFromName($package[1]);
    $archive->close();
    if (!is_string($source) || !strstr($source, "'locale' => \$locale") || !strstr($source, 'normalizeLocale')) {
        throw new RuntimeException('Integration package does not include locale propagation: ' . $package[0]);
    }
}

echo "Generated integration package locale tests passed.\n";
