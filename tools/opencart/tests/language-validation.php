<?php
function language_entries(string $path): array {
    $contents = file_get_contents($path);
    if (!is_string($contents)) {
        throw new RuntimeException('Unable to read language file: ' . $path);
    }

    $pattern = <<<'REGEX'
~\$_\['([^']+)'\]\s*=\s*('(?:\\\\.|[^'\\\\])*'|"(?:\\\\.|[^"\\\\])*")\s*;~
REGEX;
    if (!preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER)) {
        throw new RuntimeException('No translation entries were found in: ' . $path);
    }

    $entries = array();
    foreach ($matches as $match) {
        if (isset($entries[$match[1]])) {
            throw new RuntimeException('Duplicate translation key ' . $match[1] . ' in: ' . $path);
        }

        if (!strlen(trim(substr($match[2], 1, -1)))) {
            throw new RuntimeException('Empty translation for key ' . $match[1] . ' in: ' . $path);
        }

        $entries[$match[1]] = true;
    }

    $keys = array_keys($entries);
    sort($keys, SORT_STRING);
    return $keys;
}

$repository = dirname(__DIR__, 3);
$language_root = $repository . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'opencart' . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'language';
$english_path = $language_root . DIRECTORY_SEPARATOR . 'en-gb' . DIRECTORY_SEPARATOR . 'extension' . DIRECTORY_SEPARATOR . 'module' . DIRECTORY_SEPARATOR . 'appnuno.php';
$english_keys = language_entries($english_path);
$language_paths = glob($language_root . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'extension' . DIRECTORY_SEPARATOR . 'module' . DIRECTORY_SEPARATOR . 'appnuno.php');
if (!is_array($language_paths) || !count($language_paths)) {
    throw new RuntimeException('No OpenCart module language files were found.');
}

$errors = array();
foreach ($language_paths as $path) {
    $locale = basename(dirname(dirname(dirname($path))));
    $keys = language_entries($path);
    $missing = array_values(array_diff($english_keys, $keys));
    $extra = array_values(array_diff($keys, $english_keys));
    if (count($missing)) {
        $errors[] = $locale . ' is missing translation keys: ' . implode(', ', $missing);
    }
    if (count($extra)) {
        $errors[] = $locale . ' has keys absent from en-gb: ' . implode(', ', $extra);
    }
}

if (count($errors)) {
    throw new RuntimeException(implode(PHP_EOL, $errors));
}

echo 'Validated translation key coverage for ' . count($language_paths) . ' OpenCart admin languages against en-gb.' . PHP_EOL;
