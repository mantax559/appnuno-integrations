<?php

declare(strict_types=1);

try {
    $database = new PDO(
        'mysql:host=' . (string) getenv('DB_SERVER') . ';dbname=' . (string) getenv('DB_NAME') . ';charset=utf8mb4',
        (string) getenv('DB_USER'),
        (string) getenv('DB_PASSWD'),
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );
} catch (PDOException $exception) {
    throw new RuntimeException('Could not connect to the PrestaShop MySQL database.');
}

$tables = $database->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('ps_configuration', 'ps_shop')");
$tablesCount = $tables instanceof PDOStatement ? $tables->fetchColumn() : false;

if (!in_array((int) $tablesCount, array(2), true)) {
    throw new RuntimeException('PrestaShop installation database tables are missing.');
}

$shop = $database->query("SELECT value FROM ps_configuration WHERE name = 'PS_SHOP_NAME' LIMIT 1");
$shopName = $shop instanceof PDOStatement ? $shop->fetchColumn() : false;

if (!is_string($shopName) || !strlen(trim($shopName))) {
    throw new RuntimeException('PrestaShop installation did not create the default shop configuration.');
}

$handle = curl_init('http://prestashop/');
curl_setopt_array($handle, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 30));
$responseBody = curl_exec($handle);
$responseCode = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
curl_close($handle);

if (!is_string($responseBody) || !in_array($responseCode, array(200), true) || !strstr(strtolower($responseBody), '<html')) {
    throw new RuntimeException('Installed PrestaShop storefront did not return an HTML page successfully.');
}

fwrite(STDOUT, 'PrestaShop installation and MySQL-backed storefront smoke test passed.' . PHP_EOL);
