<?php
require '/var/www/html/config/config.inc.php';

$iso = isset($argv[1]) ? $argv[1] : '';
$idLang = (int) Language::getIdByIso($iso);
if (!$idLang) {
    fwrite(STDERR, "Unknown PrestaShop language {$iso}.\n");
    exit(1);
}
$updated = Db::getInstance()->update('employee', array('id_lang' => $idLang), 'email = "admin@example.test"');
if (!$updated) {
    fwrite(STDERR, "Unable to change the employee language.\n");
    exit(1);
}
fwrite(STDOUT, "employee-language={$iso}\n");
