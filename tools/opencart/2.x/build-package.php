<?php
$line = isset($argv[1]) ? $argv[1] : '2.3';
if (!in_array($line, array('2.0', '2.1', '2.2', '2.3', '3.0', '4'), true)) {
    throw new RuntimeException('Unsupported OpenCart package line.');
}
$repository = dirname(__DIR__, 3);
$source = $repository . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'opencart' . DIRECTORY_SEPARATOR . '2.x';
$release_line = $line === '4' ? '4.x' : $line . '.x';
$release = $repository . DIRECTORY_SEPARATOR . 'downloads' . DIRECTORY_SEPARATOR . 'opencart' . DIRECTORY_SEPARATOR . $release_line;
$package_file = $line === '4' ? 'appnuno.ocmod.zip' : 'appnuno-opencart-' . $release_line . '.ocmod.zip';
$package = $release . DIRECTORY_SEPARATOR . $package_file;
$readme = $release . DIRECTORY_SEPARATOR . 'README.md';
if (!is_dir($release) && !mkdir($release, 0775, true) && !is_dir($release)) {
    throw new RuntimeException('Unable to create OpenCart release directory.');
}
$entries = array(
    'install.xml' => $source . DIRECTORY_SEPARATOR . 'install.xml',
    'README.md' => $readme
);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source . DIRECTORY_SEPARATOR . 'upload', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->isFile()) {
        $path = substr($file->getPathname(), strlen($source) + 1);
        $entries[str_replace(DIRECTORY_SEPARATOR, '/', $path)] = $file->getPathname();
    }
}
$prepared = array();
foreach ($entries as $name => $path) {
    $body = file_get_contents($path);
    if ($body === false) {
        throw new RuntimeException('Unable to read an OpenCart package file.');
    }
    if (in_array($line, array('2.0', '2.1', '2.2'), true) && $name !== 'README.md') {
        $name = str_replace('/extension/module/', '/module/', $name);
        $body = opencart_module_layout($line, $name, $body);
    }
    if ($line === '3.0' && $name !== 'README.md') {
        $body = opencart_30_layout($name, $body);
        if (substr($name, -4) === '.tpl') {
            $name = substr($name, 0, -4) . '.twig';
        }
    }
    if ($line === '4' && $name !== 'README.md') {
        if ($name === 'install.xml') {
            continue;
        }
        if (strpos($name, 'upload/') === 0) {
            $name = substr($name, strlen('upload/'));
        }
        $name = str_replace('/extension/module/', '/module/', $name);
        $body = opencart_40_layout($name, $body);
        if (substr($name, -4) === '.tpl') {
            $body = opencart_twig_template($body);
            $name = substr($name, 0, -4) . '.twig';
        }
    }
    $prepared[$name] = $body;
}
if ($line === '4') {
    $prepared['install.json'] = "{\n    \"name\": \"AppNuno OpenCart 4.x\",\n    \"description\": \"AppNuno native navigation for OpenCart 4.\",\n    \"code\": \"appnuno\",\n    \"version\": \"1.2.0\",\n    \"author\": \"AppNuno\",\n    \"link\": \"https://appnuno.com\"\n}\n";
}
if (in_array($line, array('2.0', '2.1'), true)) {
    $prepared['upload/admin/language/english/module/appnuno.php'] = $prepared['upload/admin/language/en-gb/module/appnuno.php'];
}
ksort($prepared, SORT_STRING);
$staging = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'appnuno-opencart-package-' . bin2hex(random_bytes(12));
if (!mkdir($staging, 0700)) {
    throw new RuntimeException('Unable to create OpenCart package staging directory.');
}
$archive = new ZipArchive();
$archive_open = false;
try {
    foreach ($prepared as $name => $body) {
        $staged = $staging . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
        $directory = dirname($staged);
        if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
            throw new RuntimeException('Unable to create OpenCart package staging path.');
        }
        if (file_put_contents($staged, $body) === false || !chmod($staged, 0644) || !touch($staged, 946684800)) {
            throw new RuntimeException('Unable to normalize an OpenCart package file.');
        }
    }
    $result = $archive->open($package, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if (!$result) {
        throw new RuntimeException('Unable to create OpenCart extension archive.');
    }
    $archive_open = true;
    foreach ($prepared as $name => $body) {
        $staged = $staging . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
        if (!$archive->addFile($staged, $name)
            || !$archive->setCompressionName($name, ZipArchive::CM_DEFLATE, 9)
            || !$archive->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, 0100644 << 16)) {
            throw new RuntimeException('Unable to add a normalized file to the OpenCart extension archive.');
        }
    }
    if (!$archive->close()) {
        throw new RuntimeException('Unable to finish OpenCart extension archive.');
    }
    $archive_open = false;
} finally {
    if ($archive_open) {
        $archive->close();
    }
    $staged_files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($staging, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($staged_files as $staged_file) {
        if ($staged_file->isDir()) {
            rmdir($staged_file->getPathname());
        } else {
            unlink($staged_file->getPathname());
        }
    }
    rmdir($staging);
}
echo $package . PHP_EOL;

function opencart_module_layout($line, $name, $content) {
    $newline = strpos($content, "\r\n") !== false ? "\r\n" : "\n";
    if (substr($name, -4) === '.php' || $name === 'install.xml') {
        $content = str_replace(
            array(
                'ControllerExtensionModuleAppnuno',
                'ModelExtensionModuleAppnuno',
                'model_extension_module_appnuno',
                'extension/module/appnuno',
                "url->link('extension/extension', 'token=' . \$this->session->data['token'] . '&type=module', true)",
                "language->get('text_extension')"
            ),
            array(
                'ControllerModuleAppnuno',
                'ModelModuleAppnuno',
                'model_module_appnuno',
                'module/appnuno',
                "url->link('extension/module', 'token=' . \$this->session->data['token'], true)",
                "language->get('text_module')"
            ),
            $content
        );
    }
    if (in_array($line, array('2.0', '2.1'), true) && substr($name, -4) === '.php') {
        $content = str_replace("load->view('module/appnuno', \$data)", "load->view('module/appnuno.tpl', \$data)", $content);
    }
    if ($line === '2.0' && $name === 'upload/admin/controller/module/appnuno.php') {
        $content = str_replace(
            array(
                "\$this->load->model('extension/event');",
                "\$this->model_extension_event->deleteEvent('appnuno');",
                "\$this->model_extension_event->addEvent('appnuno', 'catalog/view/common/header/before', 'module/appnuno/inject');"
            ),
            '',
            $content
        );
    }
    if ($name === 'install.xml') {
        $replacements = array(
            '2.0' => array('AppNuno OpenCart 2.0.x', 'appnuno_opencart_2000'),
            '2.1' => array('AppNuno OpenCart 2.1.x', 'appnuno_opencart_2100'),
            '2.2' => array('AppNuno OpenCart 2.2.x', 'appnuno_opencart_2200')
        );
        $content = str_replace(array('AppNuno OpenCart 2.3.x', 'appnuno_opencart_2302'), $replacements[$line], $content);
        if ($line === '2.2') {
            $content = str_replace(", 'SSL')", ', true)', $content);
        }
    }
    $labels = array(
        'en-gb' => 'Modules',
        'lt-lt' => 'Moduliai',
        'de-de' => 'Module'
    );
    if (preg_match('#language/([a-z]{2}-[a-z]{2})/module/appnuno\\.php$#', $name, $match) && isset($labels[$match[1]])) {
        $content = str_replace("\$_['text_extension']", "\$_['text_module']                 = '" . $labels[$match[1]] . "';" . $newline . "\$_['text_extension']", $content);
    }
    return $content;
}

function opencart_30_layout($name, $content) {
    // OpenCart 3 keeps the extension/module layout, but its admin uses Twig, user_token, and setting/event with a view/ trigger.
    if (substr($name, -4) === '.php' || $name === 'install.xml') {
        $content = str_replace(
            array(
                "\$this->load->model('extension/event');",
                "\$this->model_extension_event->deleteEvent('appnuno');",
                "\$this->model_extension_event->addEvent('appnuno', 'catalog/view/common/header/before', 'extension/module/appnuno/inject');",
                "token=' . \$this->session->data['token']"
            ),
            array(
                "\$this->load->model('setting/event');",
                "\$this->model_setting_event->deleteEventByCode('appnuno');",
                "\$this->model_setting_event->addEvent('appnuno', 'view/common/header/before', 'extension/module/appnuno/inject');",
                "user_token=' . \$this->session->data['user_token']"
            ),
            $content
        );
    }
    if ($name === 'install.xml') {
        $content = str_replace(
            array('AppNuno OpenCart 2.3.x', 'appnuno_opencart_2302', "\$data['scripts'] = \$this->document->getScripts();"),
            array('AppNuno OpenCart 3.0.x', 'appnuno_opencart_3000', "\$data['scripts'] = \$this->document->getScripts('header');"),
            $content
        );
    }
    if (substr($name, -4) === '.tpl') {
        $content = opencart_twig_template($content);
    }
    return $content;
}

function opencart_twig_template($content) {
    $stack = array();
    $converted = preg_replace_callback('/<\?php\s*(.*?)\s*\?>/s', function ($match) use (&$stack) {
        $code = trim($match[1]);
        if (preg_match('/^echo\s+htmlspecialchars\(\$([A-Za-z_][A-Za-z0-9_]*)\s*,/', $code, $variable)) {
            return '{{ ' . $variable[1] . ' }}';
        }
        if (preg_match('/^echo\s+json_encode\(\$([A-Za-z_][A-Za-z0-9_]*)\s*,/', $code, $variable)) {
            return '{{ ' . $variable[1] . '|json_encode|raw }}';
        }
        if (preg_match('/^echo\s+\$([A-Za-z_][A-Za-z0-9_]*)\[\'([A-Za-z0-9_]+)\'\];$/', $code, $variable)) {
            return '{{ ' . $variable[1] . '.' . $variable[2] . ' }}';
        }
        if (preg_match('/^echo\s+\$([A-Za-z_][A-Za-z0-9_]*);$/', $code, $variable)) {
            if (substr($variable[1], -5) === '_json') {
                return '{{ ' . $variable[1] . '|raw }}';
            }
            return '{{ ' . $variable[1] . ' }}';
        }
        if (preg_match('/^foreach\s+\(\$([A-Za-z_][A-Za-z0-9_]*)\s+as\s+\$([A-Za-z_][A-Za-z0-9_]*)\)\s*\{$/', $code, $variable)) {
            $stack[] = 'for';
            return '{% for ' . $variable[2] . ' in ' . $variable[1] . ' %}';
        }
        if (preg_match('/^if\s+\(\$([A-Za-z_][A-Za-z0-9_]*)\)\s*\{$/', $code, $variable)) {
            $stack[] = 'if';
            return '{% if ' . $variable[1] . ' %}';
        }
        if ($code === '} else {') {
            return '{% else %}';
        }
        if ($code === '}') {
            $kind = array_pop($stack);
            if ($kind === 'for') {
                return '{% endfor %}';
            }
            return '{% endif %}';
        }
        throw new RuntimeException('Unsupported OpenCart template tag: ' . $code);
    }, $content);
    if (!is_string($converted) || count($stack) || strpos($converted, '<?php') !== false) {
        throw new RuntimeException('OpenCart 3 template was not converted to Twig.');
    }
    return $converted;
}

function opencart_40_layout($name, $content) {
    // OpenCart 4 installs the zip into extension/{filename} and autoloads Opencart\{Application}\Controller\Extension\Appnuno.
    $content = str_replace("\r\n", "\n", $content);
    if (substr($name, -4) !== '.php') {
        return $content;
    }
    if ($name === 'admin/controller/module/appnuno.php') {
        $content = str_replace(
            "\t\t\t\$this->load->model('extension/event');\n\n\t\t\t\$this->model_extension_event->deleteEvent('appnuno');\n\t\t\t\$this->model_extension_event->addEvent('appnuno', 'catalog/view/common/header/before', 'extension/module/appnuno/inject');\n",
            "\t\t\t\$this->repairEvent();\n",
            $content
        );
        $content = str_replace(
            "\t\t\$this->load->model('extension/event');\n\n\t\t\$this->model_extension_event->addEvent('appnuno', 'catalog/view/common/header/before', 'extension/module/appnuno/inject');\n",
            "\t\t\$this->repairEvent();\n",
            $content
        );
        $content = str_replace(
            "\t\t\$this->load->model('extension/event');\n\n\t\t\$this->model_extension_event->deleteEvent('appnuno');\n",
            "\t\t\$this->load->model('setting/event');\n\n\t\t\$this->model_setting_event->deleteEventByCode('appnuno');\n",
            $content
        );
        $method = "\n\tprivate function repairEvent() {\n\t\t// OpenCart 4.0.0.0 takes positional event arguments. 4.0.1.0+ takes one array, and 4.0.2.0+ separates the action method with a dot.\n\t\t\$this->load->model('setting/event');\n\t\t\$this->model_setting_event->deleteEventByCode('appnuno');\n\t\t\$action = 'extension/appnuno/module/appnuno' . (version_compare(VERSION, '4.0.2.0', '<') ? '|' : '.') . 'inject';\n\t\tif (version_compare(VERSION, '4.0.1.0', '<')) {\n\t\t\t\$this->model_setting_event->addEvent('appnuno', 'AppNuno header script', 'catalog/view/common/header/before', \$action, true, 0);\n\n\t\t\treturn;\n\t\t}\n\t\t\$this->model_setting_event->addEvent(array(\n\t\t\t'code' => 'appnuno',\n\t\t\t'description' => 'AppNuno header script',\n\t\t\t'trigger' => 'catalog/view/common/header/before',\n\t\t\t'action' => \$action,\n\t\t\t'status' => true,\n\t\t\t'sort_order' => 0\n\t\t));\n\t}\n";
        $content = preg_replace('/\}\s*$/', $method . "}\n", $content, 1);
        $content = str_replace('class ControllerExtensionModuleAppnuno extends Controller {', "namespace Opencart\\Admin\\Controller\\Extension\\Appnuno\\Module;\nclass Appnuno extends \\Opencart\\System\\Engine\\Controller {", $content);
    }
    if ($name === 'catalog/controller/module/appnuno.php') {
        $content = str_replace(
            "url->link('extension/module/appnuno/bootstrap', \$this->bootstrapArgs(\$page_route), true)",
            "url->link('extension/appnuno/module/appnuno' . (version_compare(VERSION, '4.0.2.0', '<') ? '|' : '.') . 'bootstrap', \$this->bootstrapArgs(\$page_route))",
            $content
        );
        $content = str_replace(
            "url->link('extension/module/appnuno/state', '', true)",
            "url->link('extension/appnuno/module/appnuno' . (version_compare(VERSION, '4.0.2.0', '<') ? '|' : '.') . 'state', '')",
            $content
        );
        $content = str_replace(
            "\tprivate function languageCode() {\n\t\tif (isset(\$this->session->data['language']) && is_string(\$this->session->data['language']) && strlen(\$this->session->data['language'])) {\n\t\t\treturn \$this->session->data['language'];\n\t\t}\n\n\t\t\$language_code = \$this->config->get('config_language');\n\n\t\treturn is_string(\$language_code) ? \$language_code : '';\n\t}",
            "\tprivate function languageCode() {\n\t\t// OpenCart 4 startup writes the active request language into config_language. The session can still hold the previous code.\n\t\t\$language_code = \$this->config->get('config_language');\n\n\t\tif (is_string(\$language_code) && strlen(\$language_code)) {\n\t\t\treturn \$language_code;\n\t\t}\n\n\t\tif (isset(\$this->session->data['language']) && is_string(\$this->session->data['language']) && strlen(\$this->session->data['language'])) {\n\t\t\treturn \$this->session->data['language'];\n\t\t}\n\n\t\treturn '';\n\t}",
            $content
        );
        $content = str_replace('class ControllerExtensionModuleAppnuno extends Controller {', "namespace Opencart\\Catalog\\Controller\\Extension\\Appnuno\\Module;\nclass Appnuno extends \\Opencart\\System\\Engine\\Controller {", $content);
    }
    if ($name === 'admin/model/module/appnuno.php') {
        $content = str_replace('class ModelExtensionModuleAppnuno extends Model {', "namespace Opencart\\Admin\\Model\\Extension\\Appnuno\\Module;\nclass Appnuno extends \\Opencart\\System\\Engine\\Model {", $content);
    }
    if ($name === 'catalog/model/module/appnuno.php') {
        $content = str_replace('class ModelExtensionModuleAppnuno extends Model {', "namespace Opencart\\Catalog\\Model\\Extension\\Appnuno\\Module;\nclass Appnuno extends \\Opencart\\System\\Engine\\Model {", $content);
    }
    $content = str_replace(
        array(
            'model_extension_module_appnuno',
            'extension/module/appnuno',
            "token=' . \$this->session->data['token']"
        ),
        array(
            'model_extension_appnuno_module_appnuno',
            'extension/appnuno/module/appnuno',
            "user_token=' . \$this->session->data['user_token']"
        ),
        $content
    );
    $content = preg_replace('/->link\(([^;]*?),\s*true\)/', '->link($1)', $content);
    return $content;
}
