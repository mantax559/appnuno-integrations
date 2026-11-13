<?php
require '/var/www/html/config/config.inc.php';

$idLanguage = (int) Configuration::get('PS_LANG_DEFAULT');
$idProduct = (int) Db::getInstance()->getValue(
    'SELECT id_product FROM `' . _DB_PREFIX_ . 'product_lang` WHERE link_rewrite = "appnuno-sample" AND id_lang = ' . $idLanguage
);
if (!$idProduct) {
    fwrite(STDERR, "The AppNuno sample product was not found.\n");
    exit(1);
}

$idShop = (int) Configuration::get('PS_SHOP_DEFAULT');
Shop::setContext(Shop::CONTEXT_SHOP, $idShop);
$context = Context::getContext();
$context->shop = new Shop($idShop);
$context->language = new Language($idLanguage);
$context->currency = new Currency((int) Configuration::get('PS_CURRENCY_DEFAULT'));
fwrite(STDOUT, Context::getContext()->link->getProductLink($idProduct));
