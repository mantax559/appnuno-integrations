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
$platform_versions = json_decode(file_get_contents($repository . DIRECTORY_SEPARATOR . 'platform-versions.json'), true);
$opencart_version = is_array($platform_versions) ? $platform_versions['opencart'] : '';
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
    'upload/admin/view/javascript/appnuno/settings.css',
    'upload/admin/view/javascript/appnuno/adapter.css',
    'upload/admin/view/javascript/appnuno/settings.js',
    'upload/admin/view/javascript/appnuno/settings.html',
    'upload/admin/view/javascript/appnuno/version.txt',
    'upload/system/library/appnuno-config.php',
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
$settings_css = $archive->getFromName('upload/admin/view/javascript/appnuno/settings.css');
$adapter_css = $archive->getFromName('upload/admin/view/javascript/appnuno/adapter.css');
$settings_js = $archive->getFromName('upload/admin/view/javascript/appnuno/settings.js');
$settings_html = $archive->getFromName('upload/admin/view/javascript/appnuno/settings.html');
$library = $archive->getFromName('upload/system/library/appnuno-config.php');
$controller = $archive->getFromName('upload/admin/controller/' . $module_path . '/appnuno.php');
$english = $archive->getFromName('upload/admin/language/en-gb/' . $module_path . '/appnuno.php');
$builtin_english = in_array($line, array('2.0', '2.1'), true) ? $archive->getFromName('upload/admin/language/english/' . $module_path . '/appnuno.php') : '';
$lithuanian = $archive->getFromName('upload/admin/language/lt-lt/' . $module_path . '/appnuno.php');
$german = $archive->getFromName('upload/admin/language/de-de/' . $module_path . '/appnuno.php');
$catalog_controller = $archive->getFromName('upload/catalog/controller/' . $module_path . '/appnuno.php');
$modification = $archive->getFromName('install.xml');
$version_file = $archive->getFromName('upload/admin/view/javascript/appnuno/version.txt');
$readme = $archive->getFromName('README.md');
$archive->close();
if (!is_string($version_file) || !strstr(trim($version_file), $opencart_version) || !is_string($controller) || !strstr($controller, "view/javascript/appnuno/version.txt")) {
    throw new RuntimeException('OpenCart package version is not shared between the editor and the central platform version file.');
}
if (!strstr($controller, "'text_default_tabs_empty'") || !strstr($controller, "'text_default_tabs_empty_help'") || !strstr($controller, "'button_add_first_tab'") || !strstr($settings_js, 'appnuno-default-empty-state') || !strstr($settings_js, 'text.text_default_tabs_empty') || !strstr($settings_js, 'text.button_add_first_tab') || !strstr($settings_css, '.appnuno-default-empty-state')) {
    throw new RuntimeException('OpenCart package is missing the empty menu prompt and its first-item action.');
}
$view = (is_string($view) ? $view : '') . (is_string($settings_js) ? $settings_js : '') . (is_string($settings_html) ? $settings_html : '');
if (!preg_match('/<div id="content">.{0,120}view\\/javascript\\/appnuno\\/settings\\.css/s', $view)) {
    throw new RuntimeException('OpenCart settings assets must remain inside the content wrapper so the admin sidebar layout remains intact.');
}
if (!is_string($settings_css) || !strstr($settings_css, '.appnuno-settings') || !strstr($settings_css, '--appnuno-primary: #18181b;') || !strstr($settings_css, '--appnuno-muted-surface: #f3f3f3;') || !strstr($settings_css, ':focus-visible') || !strstr($settings_css, '#icon-picker-dialog .appnuno-dialog-panel') || !is_string($adapter_css) || !strstr($adapter_css, '#column-left.active ~ #content') || !is_string($settings_js) || !strstr($settings_js, 'readSettingsData') || !is_string($view) || !strstr($view, 'view/javascript/appnuno/settings.css') || !strstr($view, 'view/javascript/appnuno/adapter.css') || !strstr($view, 'view/javascript/appnuno/settings.js') || strstr($view, '<script type="text/javascript">') || !strstr($view, "'language/' + language.code + '/' + language.code + '.png'") || !strstr($view, 'data-language-url') || !strstr($view, 'text.text_custom_url') || !strstr($view, 'text.text_shown_on_pages') || !strstr($view, 'data-pages') || !strstr($view, 'data-all-pages') || !strstr($view, 'data-page-choice') || !strstr($view, 'page-picker-dialog') || !strstr($view, 'text.text_show_on_all_pages') || !strstr($view, 'text.text_show_on_selected_pages') || !strstr($view, 'text.text_show_on_no_pages') || !strstr($view, 'text.text_all_pages') || !strstr($view, 'type="color"') || !strstr($view, 'rgba(0, 0, 0, 0.02)') || !strstr($settings_js, 'function showDialog(dialog)') || !strstr($view, 'appnuno-page-list') || !strstr($view, "className = 'appnuno-check'") || !strstr($view, 'data-tab-target') || !strstr($view, 'data-tab-visibility') || !strstr($view, 'button_clear_pages') || !strstr($view, 'button_select_all_pages') || !strstr($view, 'appnuno-field-label') || !strstr($view, 'appnuno-field-control') || !strstr($view, 'appnuno-tab-list') || !strstr($view, 'appnuno-status-field') || !strstr($view, 'appnuno-tab-panels') || !strstr($view, 'data-remove-tab') || !strstr($view, 'appnunoIcon(\'trash\')') || !strstr($view, 'appnuno-button appnuno-button-primary') || strstr($view, 'tabRow.style.display') || !strstr($view, 'data-appnuno-dismiss') || strstr($view, 'fa-question-circle') || strstr($view, 'initializeTooltip') || strstr($view, 'col-sm-4') || strstr($view, 'btn btn-danger') || strstr($view, 'text.text_default_tabs_help') || strstr($view, 'alert alert-info')) {
    throw new RuntimeException('OpenCart archive is missing the updated settings controls and defaults.');
}
if (!strstr($view, 'id="default-tabs"') || !strstr($view, 'text.entry_cart_tab') || !strstr($view, 'data-cart-switch') || strstr($view, 'id="cart-tab-id"') || strstr($view, 'Cart badge</h3>') || !strstr($view, 'logo-dark.png') || !strstr($view, 'alt="AppNuno"') || !strstr($view, 'id="input-config-json" value=""') || ($line === '3.0' && (strstr($view, '<?php') || !strstr($view, '{{ appnuno_settings|raw }}')))) {
    throw new RuntimeException('OpenCart archive is missing the integrated cart badge switch, AppNuno logo or empty serialized config field.');
}
if (strstr($view, '<fieldset') || !strstr($view, 'data-page-manage') || !strstr($view, 'page-picker-dialog') || !strstr($view, 'function showPagePicker(group)') || !strstr($view, 'text.text_show_on_selected_pages') || !strstr($controller, "'button_manage_pages'") || !strstr($controller, "'button_apply_pages'") || !strstr($controller, "'button_cancel_pages'")) {
    throw new RuntimeException('OpenCart archive is missing panel sections, the page manager dialog or its interface text.');
}
if (!is_string($english) || !strstr($english, "\$_['heading_title']                = 'AppNuno'") || !strstr($english, 'Settings data was not submitted') || !strstr($english, 'Cart badge tab') || !is_string($lithuanian) || !strstr($lithuanian, "\$_['heading_title']                = 'AppNuno'") || !strstr($lithuanian, 'Navigacija') || !is_string($german) || !strstr($german, "\$_['heading_title']                = 'AppNuno'") || !strstr($german, "\$_['text_default_tabs']            = 'Navigation'") || !strstr($english, 'Showing on %s pages') || !strstr($english, 'Showing on no pages') || !strstr($lithuanian, 'Nerodoma jokiuose puslapiuose') || !strstr($german, 'Wird auf keiner Seite angezeigt')) {
    throw new RuntimeException('OpenCart archive is missing the English, Lithuanian or German module language.');
}
if (!strstr($view, 'https://appnuno.com/opencart') || !strstr($view, 'appnuno-version-group') || !strstr($view, 'text_documentation') || !strstr($view, 'function enhanceSelectSwitch(id)') || !strstr($view, "['back-enabled', 'back-placement', 'glass-enabled', 'glass-color-scheme', 'glass-style', 'glass-animate', 'glass-interactive'].forEach(enhanceSelectSwitch)") || !strstr($view, 'function appendInlineHelp(label, message)') || !strstr($view, 'appnuno-field-description') || !strstr($english, "\$_['text_documentation']") || !strstr($lithuanian, "\$_['text_documentation']") || !strstr($german, "\$_['text_documentation']")) {
    throw new RuntimeException('OpenCart editor must include the translated documentation link to the platform guide.');
}
if (strstr($settings_css, '.appnuno-fieldset') || !strstr($settings_css, '#page-picker-dialog .appnuno-dialog-panel') || !strstr($settings_css, '.appnuno-settings .appnuno-setting-title { margin: 0; padding: 16px var(--appnuno-gap); border-bottom: 1px solid var(--appnuno-line); color: #18181b; font: 600 18px/1.5') || !strstr($settings_css, 'font-size: 16px; font-weight: 550;') || !strstr($settings_css, '.appnuno-settings .appnuno-field-description { margin: 4px 0 0; color: #71717a; font-size: 13px;') || !strstr($settings_css, '.appnuno-settings #default-tabs ul.appnuno-tab-list { display: grid; gap: 0; margin: 0 0 var(--appnuno-space); padding: 0; overflow: hidden; border: 1px solid var(--appnuno-line); border-radius: var(--appnuno-radius); background: #fff; list-style: none; }') || !strstr($settings_css, '.appnuno-settings #default-tabs ul.appnuno-tab-list > li > a { display: flex; align-items: center; gap: 10px; width: 100%; min-height: 44px; padding: 11px 12px; border: 0; border-bottom: 1px solid var(--appnuno-line);') || !strstr($settings_css, '.appnuno-settings .appnuno-tab.active > [data-field-group] { display: grid; grid-template-columns: minmax(0, 1fr) minmax(240px, 360px);') || !strstr($settings_css, '.appnuno-settings .appnuno-setting-card-body > .appnuno-field { display: grid; grid-template-columns: minmax(0, 1fr) minmax(240px, 320px);') || !strstr($settings_css, '.appnuno-settings .appnuno-setting-title { margin: 0; padding: 16px var(--appnuno-gap); border-bottom: 1px solid var(--appnuno-line);') || !strstr($settings_css, '.appnuno-settings #default-tabs ul.appnuno-tab-list > li.active > a:hover') || !strstr($settings_css, 'background: #f9f9f9; color: #000; box-shadow: none;') || !strstr($settings_css, '.appnuno-settings .appnuno-section-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;') || !strstr($settings_css, '.appnuno-settings .appnuno-section-toolbar .appnuno-status-field { flex: 0 0 auto; flex-direction: row; align-items: center; gap: 16px; margin: 0 0 0 auto;') || !strstr($settings_css, '.appnuno-settings .appnuno-section-toolbar .appnuno-status-field { margin-left: auto; }') || !strstr($settings_css, '.appnuno-settings .appnuno-field-description { margin: 4px 0 0; color: #71717a;') || !strstr($settings_css, '.appnuno-settings .appnuno-setting-card-body input[type="color"] { max-width: 112px; margin-left: auto; }') || !strstr($settings_css, '.appnuno-settings :is(input, select, textarea):focus-visible { outline: 2px solid #71717a; outline-offset: 2px; box-shadow: none; }') || !strstr($settings_css, '.appnuno-settings input[type="checkbox"]:focus-visible { outline: 2px solid #71717a; outline-offset: 2px; }') || !strstr($settings_css, '.appnuno-switch-indicator { position: absolute; top: 0; left: 0; z-index: 0; border-radius: 7px; background: #fff; pointer-events: none; box-shadow: 0 1px 3px rgb(0 0 0 / 12%); }') || !strstr($settings_css, '.appnuno-switch { position: relative; display: inline-flex; flex-wrap: wrap; align-items: center; width: min(100%, max-content); max-width: 100%; padding: 4px; border: 0; border-radius: 11px; background: #f4f4f5; }') || !strstr($settings_css, '.appnuno-settings :is(label, .appnuno-field-label, .appnuno-check-label) { color: #000 !important; }') || !strstr($settings_css, 'color: #000;') || !strstr($settings_css, '.appnuno-settings #default-tabs .appnuno-button[data-add-tab] { width: 100%; min-height: 40px; border: 1px solid') || !strstr($settings_css, 'input[type="color"].appnuno-input { width: 100%; height: 44px; padding: 3px; border-radius: 12px;') || !strstr($settings_css, '.appnuno-settings .appnuno-version-group { display: inline-flex; align-items: stretch; gap: 0; flex: 0 0 auto; overflow: hidden; border: 1px solid var(--appnuno-line); border-radius: var(--appnuno-radius-control);') || !strstr($settings_css, '.appnuno-settings .appnuno-version { display: inline-flex; align-items: center; flex: 0 0 auto; padding: 5px 10px; border: 0; border-right: 1px solid var(--appnuno-line);') || !strstr($settings_css, '.appnuno-settings .appnuno-documentation { display: inline-flex; align-items: center; flex: 0 0 auto; padding: 5px 10px; border: 0; border-radius: 0;') || !strstr($settings_css, '::-webkit-color-swatch { border: 1px solid var(--appnuno-line); border-radius: 8px; }')) {
    throw new RuntimeException('OpenCart editor is missing the requested input, label, menu button or color-picker styling.');
}
if (in_array($line, array('2.0', '2.1'), true) && (!is_string($builtin_english) || !strstr($builtin_english, "\$_['heading_title']                = 'AppNuno'") || !strstr($builtin_english, "\$_['text_module']"))) {
    throw new RuntimeException('OpenCart archive is missing the English language directory the store uses by default.');
}
if ($line === '2.3' && (!strstr($modification, 'https://appnuno.com/logo-dark.png') || !strstr($modification, 'alt="AppNuno"') || !strstr($modification, '<span>v' . $opencart_version . '</span>') || strstr($modification, '<span style="color:#000000">'))) {
    throw new RuntimeException('OpenCart 2.3 extension-list branding must display the AppNuno logo and central version.');
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
if (!is_string($catalog_controller) || !is_string($library) || strstr($catalog_controller, '->validateConfig(') || !strstr($library, "['urls']") || !strstr($library, "['urls'][\$language_code]") || !strstr($library, 'unset($tab[\'urls\'])') || !strstr($catalog_controller, "['defaultTabs']") || !strstr($catalog_controller, 'visibleTabs') || !strstr($library, "['visible']") || !strstr($catalog_controller, 'tabShownOnRoute') || !strstr($library, "['allPages']")) {
    throw new RuntimeException('OpenCart archive is missing a valid bootstrap configuration path or localized URL resolution.');
}
$modification_code = array('2.0' => 'appnuno_opencart_2000', '2.1' => 'appnuno_opencart_2100', '2.2' => 'appnuno_opencart_2200', '2.3' => 'appnuno_opencart_2302', '3.0' => 'appnuno_opencart_3000');
$script_call = $line === '3.0' ? "\$data['scripts'] = \$this->document->getScripts('header');" : "\$data['scripts'] = \$this->document->getScripts();";
if (!is_string($modification) || !strstr($modification, '<version>' . $opencart_version . '</version>') || !strstr($modification, 'AppNuno OpenCart ' . $release_line) || !strstr($modification, '<code>' . $modification_code[$line] . '</code>') || !strstr($modification, 'catalog/controller/common/header.php') || !strstr($modification, $script_call) || !strstr($modification, $module_path . "/appnuno/bootstrap")) {
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
    $platform_versions = json_decode(file_get_contents($repository . DIRECTORY_SEPARATOR . 'platform-versions.json'), true);
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
        'admin/view/javascript/appnuno/settings.css',
        'admin/view/javascript/appnuno/adapter.css',
        'admin/view/javascript/appnuno/settings.js',
        'admin/view/javascript/appnuno/settings.html',
        'admin/view/javascript/appnuno/version.txt',
        'system/library/appnuno-config.php',
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
    $settings_css = $archive->getFromName('admin/view/javascript/appnuno/settings.css');
    $adapter_css = $archive->getFromName('admin/view/javascript/appnuno/adapter.css');
    $settings_js = $archive->getFromName('admin/view/javascript/appnuno/settings.js');
    $settings_html = $archive->getFromName('admin/view/javascript/appnuno/settings.html');
    $library = $archive->getFromName('system/library/appnuno-config.php');
    $controller = $archive->getFromName('admin/controller/module/appnuno.php');
    $catalog_controller = $archive->getFromName('catalog/controller/module/appnuno.php');
    $admin_model = $archive->getFromName('admin/model/module/appnuno.php');
    $catalog_model = $archive->getFromName('catalog/model/module/appnuno.php');
    $english = $archive->getFromName('admin/language/en-gb/module/appnuno.php');
    $lithuanian = $archive->getFromName('admin/language/lt-lt/module/appnuno.php');
    $german = $archive->getFromName('admin/language/de-de/module/appnuno.php');
    $manifest = $archive->getFromName('install.json');
    $version_file = $archive->getFromName('admin/view/javascript/appnuno/version.txt');
    $readme = $archive->getFromName('README.md');
    $archive->close();
    $view = (is_string($view) ? $view : '') . (is_string($settings_js) ? $settings_js : '') . (is_string($settings_html) ? $settings_html : '');
    if (!preg_match('/<div id="content">.{0,120}view\\/javascript\\/appnuno\\/settings\\.css/s', $view)) {
        throw new RuntimeException('OpenCart settings assets must remain inside the content wrapper so the admin sidebar layout remains intact.');
    }
    if (in_array('install.xml', $entries, true) || in_array('upload/admin/controller/extension/module/appnuno.php', $entries, true)) {
        throw new RuntimeException('OpenCart 4 archive must not ship the 2.x or 3.x layout.');
    }
    if (!is_string($settings_css) || !strstr($settings_css, '.appnuno-settings') || !strstr($settings_css, '--appnuno-primary: #18181b;') || !strstr($settings_css, '--appnuno-muted-surface: #f3f3f3;') || !strstr($settings_css, ':focus-visible') || !strstr($settings_css, '#icon-picker-dialog .appnuno-dialog-panel') || !is_string($adapter_css) || !strstr($adapter_css, '#column-left.active ~ #content') || !is_string($settings_js) || !strstr($settings_js, 'readSettingsData') || !is_string($view) || !strstr($view, 'view/javascript/appnuno/settings.css') || !strstr($view, 'view/javascript/appnuno/adapter.css') || !strstr($view, 'view/javascript/appnuno/settings.js') || strstr($view, '<script type="text/javascript">') || !strstr($view, "'language/' + language.code + '/' + language.code + '.png'") || !strstr($view, 'data-language-url') || !strstr($view, 'text.text_custom_url') || !strstr($view, 'text.text_shown_on_pages') || !strstr($view, 'data-pages') || !strstr($view, 'data-all-pages') || !strstr($view, 'data-page-choice') || !strstr($view, 'page-picker-dialog') || !strstr($view, 'text.text_show_on_all_pages') || !strstr($view, 'text.text_show_on_selected_pages') || !strstr($view, 'text.text_show_on_no_pages') || !strstr($view, 'text.text_all_pages') || !strstr($view, 'button_select_all_pages') || !strstr($view, 'appnuno-field-label') || !strstr($view, 'appnuno-field-control') || !strstr($view, 'appnuno-tab-list') || !strstr($view, 'appnuno-status-field') || !strstr($view, 'appnuno-tab-panels') || !strstr($view, 'data-remove-tab') || !strstr($view, 'appnunoIcon(\'trash\')') || !strstr($view, 'appnuno-button appnuno-button-primary') || strstr($view, 'tabRow.style.display') || strstr($view, 'fa-question-circle') || strstr($view, 'initializeTooltip') || !strstr($view, 'type="color"') || !strstr($view, 'rgba(0, 0, 0, 0.02)') || !strstr($view, 'id="default-tabs"') || !strstr($view, 'text.entry_cart_tab') || !strstr($view, 'data-cart-switch') || strstr($view, 'id="cart-tab-id"') || !strstr($view, 'logo-dark.png') || !strstr($view, 'alt="AppNuno"') || !strstr($view, 'appnuno-section-list') || strstr($view, 'data-toggle="tab"') || strstr($view, '<?php') || !strstr($view, '{{ appnuno_settings|raw }}') || !strstr($settings_js, 'function showDialog(dialog)') || !strstr($view, 'appnuno-check') || !strstr($view, 'data-tab-target') || !strstr($view, 'data-tab-visibility') || !strstr($view, 'button_clear_pages') || !strstr($view, 'appnuno-toolbar') || !strstr($view, 'appnunoIcon(\'eyeOff\')') || strstr($view, 'panel panel-default') || strstr($view, 'class="form-group"')) {
        throw new RuntimeException('OpenCart 4 archive is missing the Twig settings form.');
    }
    if (!is_string($view) || !strstr($view, '../extension/appnuno/admin/view/javascript/appnuno/settings.css?v=') || !strstr($view, '../extension/appnuno/admin/view/javascript/appnuno/adapter.css?v=') || !is_string($settings_html) || !strstr($settings_html, '../extension/appnuno/admin/view/javascript/appnuno/settings.js?v=') || !is_string($controller) || !strstr($controller, "DIR_EXTENSION . 'appnuno/admin/view/javascript/appnuno/settings.html'") || !strstr($controller, '../extension/appnuno/admin/view/javascript/appnuno/settings.js?v=')) {
        throw new RuntimeException('OpenCart 4 archive is missing extension-scoped settings asset paths.');
    }
    if (!is_string($english) || !strstr($english, "\$_['heading_title']                = 'AppNuno'") || !strstr($english, 'Settings data was not submitted') || !is_string($lithuanian) || !strstr($lithuanian, "\$_['heading_title']                = 'AppNuno'") || !is_string($german) || !strstr($german, "\$_['heading_title']                = 'AppNuno'")) {
        throw new RuntimeException('OpenCart 4 archive is missing the English, Lithuanian or German module language.');
    }
    if (!is_string($controller) || !opencart_40_namespace_is_first($controller, 'Opencart\\Admin\\Controller\\Extension\\Appnuno\\Module') || !strstr($controller, "dirname(__FILE__) . '/../../../system/library/appnuno-config.php'") || !strstr($controller, "DIR_EXTENSION . 'appnuno/admin/view/javascript/appnuno/version.txt'") || !strstr($controller, '\AppnunoConfig::') || !strstr($controller, 'class Appnuno extends \\Opencart\\System\\Engine\\Controller') || !strstr($controller, 'function repairEvent()') || !strstr($controller, "deleteEventByCode('appnuno')") || !strstr($controller, "session->data['user_token']") || strstr($controller, "session->data['token']") || strstr($controller, 'model_extension_event') || !strstr($controller, 'extension/appnuno/module/appnuno')) {
        throw new RuntimeException('OpenCart 4 archive is missing the extension controller, user_token links or event repair.');
    }
    if (!is_string($catalog_controller) || !opencart_40_namespace_is_first($catalog_controller, 'Opencart\\Catalog\\Controller\\Extension\\Appnuno\\Module') || !strstr($catalog_controller, "dirname(__FILE__) . '/../../../system/library/appnuno-config.php'") || !strstr($catalog_controller, '\AppnunoConfig::') || !is_string($library) || !opencart_40_namespace_is_first($admin_model, 'Opencart\\Admin\\Model\\Extension\\Appnuno\\Module') || !strstr($admin_model, "dirname(__FILE__) . '/../../../system/library/appnuno-config.php'") || !strstr($admin_model, '\AppnunoConfig::') || !opencart_40_namespace_is_first($catalog_model, 'Opencart\\Catalog\\Model\\Extension\\Appnuno\\Module') || !strstr($catalog_controller, "version_compare(VERSION, '4.0.2.0', '<')") || !strstr($catalog_controller, "array('href' => \$script_url)") || !strstr($library, "['urls'][\$language_code]") || !strstr($library, 'unset($tab[\'urls\'])')) {
        throw new RuntimeException('OpenCart 4 archive is missing the catalog bootstrap controller.');
    }
    if (!is_string($manifest) || !strstr($manifest, '"name": "AppNuno OpenCart 4.x"') || !strstr($manifest, '"code": "appnuno"') || !strstr($manifest, '"version": "' . $platform_versions['opencart'] . '"') || !is_string($version_file) || !strstr(trim($version_file), $platform_versions['opencart'])) {
        throw new RuntimeException('OpenCart 4 archive is missing install.json.');
    }
    if (!is_string($readme) || !strstr($readme, 'appnuno.ocmod.zip') || !strstr($readme, 'extension/{filename}') || !strstr($readme, 'Extension Installer') || !strstr($readme, 'Do not uninstall') || !strstr($readme, 'extension/appnuno/module/appnuno')) {
        throw new RuntimeException('OpenCart 4 archive is missing its installation instructions.');
    }
    $missing = array_diff($required, $entries);
    if (count($missing)) {
        throw new RuntimeException('Missing OpenCart package files: ' . implode(', ', $missing));
    }
}

function opencart_40_namespace_is_first(string $content, string $namespace): bool {
    $prefix = "<?php\nnamespace " . $namespace . ";";
    return strstr(substr($content, 0, strlen($prefix)), $prefix);
}
