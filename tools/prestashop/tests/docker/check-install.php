<?php

declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_OFF);
$database = @mysqli_connect(
    (string) getenv('DB_SERVER'),
    (string) getenv('DB_USER'),
    (string) getenv('DB_PASSWD'),
    (string) getenv('DB_NAME')
);

if (!($database instanceof mysqli)) {
    throw new RuntimeException('Could not connect to the PrestaShop MySQL database.');
}

$tablesResult = mysqli_query($database, "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('ps_configuration', 'ps_shop')");
$tablesRow = $tablesResult instanceof mysqli_result ? mysqli_fetch_row($tablesResult) : false;

if (!is_array($tablesRow) || !in_array((int) $tablesRow[0], array(2), true)) {
    throw new RuntimeException('PrestaShop installation database tables are missing.');
}

$shopResult = mysqli_query($database, "SELECT value FROM ps_configuration WHERE name = 'PS_SHOP_NAME' LIMIT 1");
$shopRow = $shopResult instanceof mysqli_result ? mysqli_fetch_row($shopResult) : false;

if (!is_array($shopRow) || !is_string($shopRow[0]) || !strlen(trim($shopRow[0]))) {
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
