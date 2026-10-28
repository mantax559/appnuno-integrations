<?php
$line = isset($argv[1]) ? $argv[1] : '2.3';
if (!in_array($line, array('2.0', '2.1', '2.2', '2.3'), true)) {
    throw new RuntimeException('Unsupported OpenCart package line.');
}
$repository = dirname(__DIR__, 3);
$source = $repository . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'opencart' . DIRECTORY_SEPARATOR . '2.3.0.2';
$release = $repository . DIRECTORY_SEPARATOR . 'downloads' . DIRECTORY_SEPARATOR . 'opencart' . DIRECTORY_SEPARATOR . $line;
$package = $release . DIRECTORY_SEPARATOR . 'appnuno-opencart-' . $line . '.ocmod.zip';
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
    $prepared[$name] = $body;
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
            '2.0' => array('AppNuno OpenCart 2.0', 'appnuno_opencart_2000'),
            '2.1' => array('AppNuno OpenCart 2.1', 'appnuno_opencart_2100'),
            '2.2' => array('AppNuno OpenCart 2.2', 'appnuno_opencart_2200')
        );
        $content = str_replace(array('AppNuno OpenCart 2.3', 'appnuno_opencart_2302'), $replacements[$line], $content);
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
