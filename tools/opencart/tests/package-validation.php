<?php
$line = isset($argv[1]) ? $argv[1] : '2.3';
if (!in_array($line, array('2.0', '2.1', '2.2', '2.3', '3.0', '4'), true)) {
    throw new RuntimeException('Unsupported OpenCart package line.');
}
if ($line === '4') {
    opencart_40_package(dirname(__DIR__, 3));
    echo "OpenCart package structure tests passed.\n";
    return;
}
$extension_layout = in_array($line, array('2.3', '3.0'), true);
$module_path = $extension_layout ? 'extension/module' : 'module';
$controller_class = $extension_layout ? 'class ControllerExtensionModuleAppnuno' : 'class ControllerModuleAppnuno';
$view_extension = $line === '3.0' ? 'twig' : 'tpl';
$repository = dirname(__DIR__, 3);
$release_lines = array('3.0' => '3.x');
$release_line = isset($release_lines[$line]) ? $release_lines[$line] : $line . '.x';
$package = $repository . DIRECTORY_SEPARATOR . 'downloads' . DIRECTORY_SEPARATOR . 'opencart' . DIRECTORY_SEPARATOR . $release_line . DIRECTORY_SEPARATOR . 'appnuno-opencart-' . $release_line . '.ocmod.zip';
$required = array(
    'install.xml',
    'README.md',
    'upload/admin/controller/' . $module_path . '/appnuno.php',
    'upload/admin/language/en-gb/' . $module_path . '/appnuno.php',
    'upload/admin/language/english/' . $module_path . '/appnuno.php',
    'upload/admin/language/lt-lt/' . $module_path . '/appnuno.php',
    'upload/admin/language/de-de/' . $module_path . '/appnuno.php',
    'upload/admin/model/' . $module_path . '/appnuno.php',
    'upload/admin/view/template/' . $module_path . '/appnuno.' . $view_extension,
    'upload/catalog/controller/' . $module_path . '/appnuno.php',
    'upload/catalog/model/' . $module_path . '/appnuno.php'
);
if ($line !== '2.1') {
    $required = array_values(array_diff($required, array('upload/admin/language/english/' . $module_path . '/appnuno.php')));
}
$archive = new ZipArchive();
if (!$archive->open($package)) {
    throw new RuntimeException('Unable to open OpenCart extension archive.');
}
$entries = array();
for ($index = 0; $index < $archive->numFiles; $index++) {
    $name = $archive->getNameIndex($index);
    $entries[] = $name;
    $stat = $archive->statIndex($index);
    if (preg_match('~^/|(^|/)\.\.(/|$)|(^|/)tests(/|$)~', $name)) {
        throw new RuntimeException('Unsafe or non-installable archive entry: ' . $name);
    }
    if (!is_array($stat) || !in_array($stat['mtime'], array(946684800), true)) {
        throw new RuntimeException('OpenCart package entries must use the normalized timestamp.');
    }
}
$sorted_entries = $entries;
sort($sorted_entries, SORT_STRING);
if (!in_array($entries, array($sorted_entries), true)) {
    throw new RuntimeException('OpenCart package entries must be sorted deterministically.');
}
$view = $archive->getFromName('upload/admin/view/template/' . $module_path . '/appnuno.' . $view_extension);
$controller = $archive->getFromName('upload/admin/controller/' . $module_path . '/appnuno.php');
$english = $archive->getFromName('upload/admin/language/en-gb/' . $module_path . '/appnuno.php');
$builtin_english = in_array($line, array('2.0', '2.1'), true) ? $archive->getFromName('upload/admin/language/english/' . $module_path . '/appnuno.php') : '';
$lithuanian = $archive->getFromName('upload/admin/language/lt-lt/' . $module_path . '/appnuno.php');
$german = $archive->getFromName('upload/admin/language/de-de/' . $module_path . '/appnuno.php');
$catalog_controller = $archive->getFromName('upload/catalog/controller/' . $module_path . '/appnuno.php');
$modification = $archive->getFromName('install.xml');
$readme = $archive->getFromName('README.md');
$archive->close();
if (!is_string($view) || !strstr($view, "'language/' + language.code + '/' + language.code + '.png'") || !strstr($view, 'data-language-url') || !strstr($view, 'text.text_custom_url') || !strstr($view, 'text.text_shown_on_pages') || !strstr($view, 'data-pages') || !strstr($view, 'data-all-pages') || !strstr($view, 'data-page-choice') || !strstr($view, 'data-page-action') || !strstr($view, 'text.text_all_pages') || !strstr($view, 'type="color"') || !strstr($view, 'rgba(0, 0, 0, 0.02)') || !strstr($view, "adminMarkup = 'bootstrap3'") || !strstr($view, 'well well-sm') || !strstr($view, "className = 'checkbox'") || !strstr($view, 'data-tab-target') || !strstr($view, 'data-tab-visibility') || !strstr($view, 'button_clear_pages') || !strstr($view, 'button_select_all_pages') || !strstr($view, 'data-bs-toggle') || !strstr($view, 'col-sm-3 control-label') || !strstr($view, 'col-sm-9') || !strstr($view, 'nav nav-pills nav-stacked') || !strstr($view, 'col-sm-2') || !strstr($view, 'col-sm-10 tab-content') || !strstr($view, 'data-remove-tab') || !strstr($view, 'fa-minus-circle') || !strstr($view, 'btn btn-primary btn-block') || strstr($view, 'tabRow.style.display') || !strstr($view, 'data-toggle') || strstr($view, 'fa-question-circle') || strstr($view, 'initializeTooltip') || strstr($view, 'col-sm-4') || strstr($view, 'btn btn-danger') || strstr($view, 'text.text_default_tabs_help') || strstr($view, 'alert alert-info')) {
    throw new RuntimeException('OpenCart archive is missing the updated settings controls and defaults.');
}
$cart_label = $line === '3.0' ? '{{ text.entry_cart_tab }}' : "\$text['entry_cart_tab']";
if (!strstr($view, 'id="default-tabs"') || !strstr($view, $cart_label) || strstr($view, 'Cart badge</h3>') || !strstr($view, 'id="input-config-json" value=""') || ($line === '3.0' && (strstr($view, '<?php') || !strstr($view, '{{ configuration_json|raw }}')))) {
    throw new RuntimeException('OpenCart archive is missing the integrated cart badge setting or the empty serialized config field.');
}
if (!is_string($english) || !strstr($english, 'Settings data was not submitted') || !strstr($english, 'Cart badge tab') || !is_string($lithuanian) || !strstr($lithuanian, 'AppNuno vietinė navigacija') || !strstr($lithuanian, 'Numatytieji skirtukai') || !is_string($german) || !strstr($german, 'AppNuno native Navigation') || !strstr($german, 'Standard-Tabs')) {
    throw new RuntimeException('OpenCart archive is missing the English, Lithuanian or German module language.');
}
if (in_array($line, array('2.0', '2.1'), true) && (!is_string($builtin_english) || !strstr($builtin_english, 'AppNuno Native Navigation') || !strstr($builtin_english, "\$_['text_module']"))) {
    throw new RuntimeException('OpenCart archive is missing the English language directory the store uses by default.');
}
if (in_array($line, array('2.0', '2.1', '2.2'), true) && (!is_string($english) || !strstr($english, "\$_['text_module']"))) {
    throw new RuntimeException('OpenCart archive is missing the modules breadcrumb label.');
}
if (in_array($line, array('2.0', '2.1'), true) && (!is_string($controller) || !strstr($controller, "load->view('module/appnuno.tpl', \$data)"))) {
    throw new RuntimeException('OpenCart archive is missing the template extension its loader requires.');
}
if (!is_string($controller) || !strstr($controller, $controller_class) || !strstr($controller, "language->get('error_config_missing')") || strstr($controller, '$stored_config')) {
    throw new RuntimeException('OpenCart archive is missing strict submitted settings handling.');
}
if ($line === '2.0') {
    if (strstr($controller, 'model_extension_event') || strstr($controller, "'appnuno'")) {
        throw new RuntimeException('OpenCart 2.0 archive must not register unsupported controller-view events.');
    }
} elseif ($line === '3.0') {
    if (!strstr($controller, "deleteEventByCode('appnuno')") || !strstr($controller, "addEvent('appnuno', 'view/common/header/before', 'extension/module/appnuno/inject')") || !strstr($controller, "session->data['user_token']") || strstr($controller, "session->data['token']") || strstr($controller, 'model_extension_event')) {
        throw new RuntimeException('OpenCart 3.0 archive is missing user_token links or setting/event repair.');
    }
} elseif (!strstr($controller, "deleteEvent('appnuno')") || !strstr($controller, "addEvent('appnuno', 'catalog/view/common/header/before', '" . $module_path . "/appnuno/inject')")) {
    throw new RuntimeException('OpenCart archive is missing catalog event repair on save.');
}
if (!is_string($catalog_controller) || strstr($catalog_controller, '->validateConfig(') || !strstr($catalog_controller, "['urls']") || !strstr($catalog_controller, "['urls'][\$language_code]") || !strstr($catalog_controller, 'unset($tab[\'urls\'])') || !strstr($catalog_controller, "['defaultTabs']") || !strstr($catalog_controller, 'visibleTabs') || !strstr($catalog_controller, "['visible']") || !strstr($catalog_controller, 'tabShownOnRoute') || !strstr($catalog_controller, "['allPages']")) {
    throw new RuntimeException('OpenCart archive is missing a valid bootstrap configuration path or localized URL resolution.');
}
$modification_code = array('2.0' => 'appnuno_opencart_2000', '2.1' => 'appnuno_opencart_2100', '2.2' => 'appnuno_opencart_2200', '2.3' => 'appnuno_opencart_2302', '3.0' => 'appnuno_opencart_3000');
$script_call = $line === '3.0' ? "\$data['scripts'] = \$this->document->getScripts('header');" : "\$data['scripts'] = \$this->document->getScripts();";
if (!is_string($modification) || !strstr($modification, 'AppNuno OpenCart ' . $release_line) || !strstr($modification, '<code>' . $modification_code[$line] . '</code>') || !strstr($modification, 'catalog/controller/common/header.php') || !strstr($modification, $script_call) || !strstr($modification, $module_path . "/appnuno/bootstrap")) {
    throw new RuntimeException('OpenCart archive is missing its version-specific modification or direct catalog header injection.');
}
if (!is_string($readme) || !strstr($readme, 'Extension Installer') || !strstr($readme, 'Modifications') || !strstr($readme, 'Do not uninstall') || !strstr($readme, 'Access Permission') || !strstr($readme, 'Modify Permission')) {
    throw new RuntimeException('OpenCart archive is missing its installation, permissions and upgrade instructions.');
}
$missing = array_diff($required, $entries);
if (count($missing)) {
    throw new RuntimeException('Missing OpenCart package files: ' . implode(', ', $missing));
}
echo "OpenCart package structure tests passed.\n";

function opencart_40_package($repository) {
    $package = $repository . DIRECTORY_SEPARATOR . 'downloads' . DIRECTORY_SEPARATOR . 'opencart' . DIRECTORY_SEPARATOR . '4.x' . DIRECTORY_SEPARATOR . 'appnuno.ocmod.zip';
    $required = array(
        'install.json',
        'README.md',
        'admin/controller/module/appnuno.php',
        'admin/language/en-gb/module/appnuno.php',
        'admin/language/lt-lt/module/appnuno.php',
        'admin/language/de-de/module/appnuno.php',
        'admin/model/module/appnuno.php',
        'admin/view/template/module/appnuno.twig',
        'catalog/controller/module/appnuno.php',
        'catalog/model/module/appnuno.php'
    );
    $archive = new ZipArchive();
    if (!$archive->open($package)) {
        throw new RuntimeException('Unable to open OpenCart extension archive.');
    }
    $entries = array();
    for ($index = 0; $index < $archive->numFiles; $index++) {
        $name = $archive->getNameIndex($index);
        $entries[] = $name;
        $stat = $archive->statIndex($index);
        if (preg_match('~^/|(^|/)\.\.(/|$)|(^|/)tests(/|$)~', $name)) {
            throw new RuntimeException('Unsafe or non-installable archive entry: ' . $name);
        }
        if (!is_array($stat) || !in_array($stat['mtime'], array(946684800), true)) {
            throw new RuntimeException('OpenCart package entries must use the normalized timestamp.');
        }
    }
    $sorted_entries = $entries;
    sort($sorted_entries, SORT_STRING);
    if (!in_array($entries, array($sorted_entries), true)) {
        throw new RuntimeException('OpenCart package entries must be sorted deterministically.');
    }
    $view = $archive->getFromName('admin/view/template/module/appnuno.twig');
    $controller = $archive->getFromName('admin/controller/module/appnuno.php');
    $catalog_controller = $archive->getFromName('catalog/controller/module/appnuno.php');
    $english = $archive->getFromName('admin/language/en-gb/module/appnuno.php');
    $lithuanian = $archive->getFromName('admin/language/lt-lt/module/appnuno.php');
    $german = $archive->getFromName('admin/language/de-de/module/appnuno.php');
    $manifest = $archive->getFromName('install.json');
    $readme = $archive->getFromName('README.md');
    $archive->close();
    if (in_array('install.xml', $entries, true) || in_array('upload/admin/controller/extension/module/appnuno.php', $entries, true)) {
        throw new RuntimeException('OpenCart 4 archive must not ship the 2.x or 3.x layout.');
    }
    if (!is_string($view) || !strstr($view, "'language/' + language.code + '/' + language.code + '.png'") || !strstr($view, 'data-language-url') || !strstr($view, 'text.text_custom_url') || !strstr($view, 'text.text_shown_on_pages') || !strstr($view, 'data-pages') || !strstr($view, 'data-all-pages') || !strstr($view, 'data-page-choice') || !strstr($view, 'data-page-action') || !strstr($view, 'text.text_all_pages') || !strstr($view, 'button_select_all_pages') || !strstr($view, 'col-sm-3 col-form-label') || !strstr($view, 'col-sm-9') || !strstr($view, 'nav nav-pills flex-column') || !strstr($view, 'col-sm-2') || !strstr($view, 'col-sm-10 tab-content') || !strstr($view, 'data-remove-tab') || !strstr($view, 'fa-circle-minus') || !strstr($view, 'btn btn-primary w-100') || strstr($view, 'tabRow.style.display') || strstr($view, 'fa-question-circle') || strstr($view, 'initializeTooltip') || !strstr($view, 'type="color"') || !strstr($view, 'rgba(0, 0, 0, 0.02)') || !strstr($view, 'id="default-tabs"') || !strstr($view, '{{ text.entry_cart_tab }}') || !strstr($view, 'data-bs-toggle="tab"') || strstr($view, 'data-toggle="tab"') || strstr($view, '<?php') || !strstr($view, '{{ configuration_json|raw }}') || !strstr($view, "adminMarkup = 'bootstrap5'") || !strstr($view, 'form-check') || !strstr($view, 'data-tab-target') || !strstr($view, 'data-tab-visibility') || !strstr($view, 'button_clear_pages') || !strstr($view, 'card-header') || !strstr($view, 'float-end') || strstr($view, 'panel panel-default') || strstr($view, 'class="form-group"')) {
        throw new RuntimeException('OpenCart 4 archive is missing the Twig settings form.');
    }
    if (!is_string($english) || !strstr($english, 'Settings data was not submitted') || !is_string($lithuanian) || !strstr($lithuanian, 'AppNuno vietinė navigacija') || !is_string($german) || !strstr($german, 'AppNuno native Navigation')) {
        throw new RuntimeException('OpenCart 4 archive is missing the English, Lithuanian or German module language.');
    }
    if (!is_string($controller) || !strstr($controller, 'namespace Opencart\\Admin\\Controller\\Extension\\Appnuno\\Module;') || !strstr($controller, 'class Appnuno extends \\Opencart\\System\\Engine\\Controller') || !strstr($controller, 'function repairEvent()') || !strstr($controller, "deleteEventByCode('appnuno')") || !strstr($controller, "session->data['user_token']") || strstr($controller, "session->data['token']") || strstr($controller, 'model_extension_event') || !strstr($controller, 'extension/appnuno/module/appnuno')) {
        throw new RuntimeException('OpenCart 4 archive is missing the extension controller, user_token links or event repair.');
    }
    if (!is_string($catalog_controller) || !strstr($catalog_controller, 'namespace Opencart\\Catalog\\Controller\\Extension\\Appnuno\\Module;') || !strstr($catalog_controller, "version_compare(VERSION, '4.0.2.0', '<')") || !strstr($catalog_controller, "array('href' => \$script_url)") || !strstr($catalog_controller, "['urls'][\$language_code]") || !strstr($catalog_controller, 'unset($tab[\'urls\'])')) {
        throw new RuntimeException('OpenCart 4 archive is missing the catalog bootstrap controller.');
    }
    if (!is_string($manifest) || !strstr($manifest, '"name": "AppNuno OpenCart 4.x"') || !strstr($manifest, '"code": "appnuno"')) {
        throw new RuntimeException('OpenCart 4 archive is missing install.json.');
    }
    if (!is_string($readme) || !strstr($readme, 'appnuno.ocmod.zip') || !strstr($readme, 'Extension Installer') || !strstr($readme, 'Do not uninstall') || !strstr($readme, 'extension/appnuno/module/appnuno')) {
        throw new RuntimeException('OpenCart 4 archive is missing its installation instructions.');
    }
    $missing = array_diff($required, $entries);
    if (count($missing)) {
        throw new RuntimeException('Missing OpenCart package files: ' . implode(', ', $missing));
    }
}
