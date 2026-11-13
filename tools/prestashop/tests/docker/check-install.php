<?php

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
$domain = $database->query("SELECT value FROM ps_configuration WHERE name = 'PS_SHOP_DOMAIN' LIMIT 1");
$shopDomain = $domain instanceof PDOStatement ? $domain->fetchColumn() : false;

if (!is_string($shopName) || !strlen(trim($shopName)) || !is_string($shopDomain) || !strlen(trim($shopDomain))) {
    throw new RuntimeException('PrestaShop installation did not create the default shop configuration.');
}

$responseLocation = '';
$response = appnunoTestStorefrontRequest('http://prestashop/', $shopDomain, $responseLocation);
$responseBody = $response[0];
$responseCode = $response[1];
if (in_array($responseCode, array(301, 302, 303, 307, 308), true) && is_string($responseLocation)) {
    $redirectPath = parse_url($responseLocation, PHP_URL_PATH);
    $redirectQuery = parse_url($responseLocation, PHP_URL_QUERY);
    $redirectUrl = 'http://prestashop' . (is_string($redirectPath) && strlen($redirectPath) ? $redirectPath : '/');
    if (is_string($redirectQuery) && strlen($redirectQuery)) {
        $redirectUrl .= '?' . $redirectQuery;
    }
    $responseLocation = '';
    $response = appnunoTestStorefrontRequest($redirectUrl, $shopDomain, $responseLocation);
    $responseBody = $response[0];
    $responseCode = $response[1];
}

if (!is_string($responseBody) || !in_array($responseCode, array(200), true) || !strstr(strtolower($responseBody), '<html')) {
    throw new RuntimeException('Installed PrestaShop storefront did not return an HTML page successfully.');
}

fwrite(STDOUT, "PrestaShop installation and MySQL-backed storefront smoke test passed." . PHP_EOL);

function appnunoTestStorefrontRequest($url, $shopDomain, &$responseLocation)
{
    $handle = curl_init($url);
    curl_setopt_array($handle, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => array('Host: ' . $shopDomain),
        CURLOPT_HEADERFUNCTION => function ($handle, $header) use (&$responseLocation) {
            if (in_array(substr(strtolower($header), 0, 9), array('location:'), true)) {
                $responseLocation = trim(substr($header, 9));
            }

            return strlen($header);
        },
        CURLOPT_TIMEOUT => 30,
    ));
    $body = curl_exec($handle);
    $code = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);

    return array($body, $code);
}
