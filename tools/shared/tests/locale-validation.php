<?php
require dirname(__DIR__, 3) . '/src/shared/php/appnuno-config.php';

$configuration = array(
    'defaultTabs' => array(
        array(
            'id' => 'home',
            'labels' => array(
                'en-gb' => 'Home',
                'fr-fr' => 'Accueil',
                'lt-lt' => 'Pradžia'
            ),
            'urls' => array(
                'en-gb' => '/en',
                'fr-fr' => '/fr',
                'lt-lt' => '/lt'
            )
        )
    ),
    'routes' => array()
);

$french = AppnunoConfig::integration($configuration, 'common/home', array(), 'fr_FR', '/', array());
$french_tab = $french['navigation']['tabs'][0];
if (!is_string($french['locale']) || !in_array($french['locale'], array('fr-fr'), true) || !in_array($french_tab['label'], array('Accueil'), true) || !in_array($french_tab['url'], array('/fr'), true)) {
    throw new RuntimeException('Expected the normalized locale to resolve its matching tab label and URL.');
}

$lithuanian = AppnunoConfig::integration($configuration, 'common/home', array(), 'lt_LT', '/', array());
$lithuanian_tab = $lithuanian['navigation']['tabs'][0];
if (!in_array($lithuanian['locale'], array('lt-lt'), true) || !in_array($lithuanian_tab['label'], array('Pradžia'), true) || !in_array($lithuanian_tab['url'], array('/lt'), true)) {
    throw new RuntimeException('Expected locale normalization to preserve localized tab labels and URLs.');
}

$fallback = AppnunoConfig::integration($configuration, 'common/home', array(), 'invalid_locale', '/', array());
if (!in_array($fallback['locale'], array('en-gb'), true) || !in_array($fallback['navigation']['tabs'][0]['label'], array('Home'), true) || !in_array($fallback['navigation']['tabs'][0]['url'], array('/en'), true)) {
    throw new RuntimeException('Expected an invalid locale to use the English locale and its existing tab behavior.');
}

echo "Integration locale propagation tests passed.\n";
