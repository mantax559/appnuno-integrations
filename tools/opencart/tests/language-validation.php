<?php
function language_tokens($value) {
    preg_match_all('/\{[a-z]+\}|%s/', $value, $tokens);
    $found = $tokens[0];
    sort($found, SORT_STRING);

    return $found;
}

function language_entries($path) {
    $catalog = json_decode(file_get_contents($path), true);
    if (!is_array($catalog) || !count($catalog)) {
        throw new RuntimeException('No translation entries were found in: ' . $path);
    }

    $keys = array();
    foreach ($catalog as $key => $value) {
        if (!is_string($key) || !is_string($value) || !strlen(trim($value))) {
            throw new RuntimeException('Empty translation for key ' . $key . ' in: ' . $path);
        }
        if (isset($keys[$key])) {
            throw new RuntimeException('Duplicate translation key ' . $key . ' in: ' . $path);
        }
        $keys[$key] = true;
    }

    $sorted = array_keys($keys);
    sort($sorted, SORT_STRING);
    return $sorted;
}

$repository = dirname(__DIR__, 3);
$language_root = $repository . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'i18n';
$english_path = $language_root . DIRECTORY_SEPARATOR . 'en-gb.json';
$english_keys = language_entries($english_path);
$language_paths = glob($language_root . DIRECTORY_SEPARATOR . '*.json');
if (!is_array($language_paths) || !count($language_paths)) {
    throw new RuntimeException('No shared admin translations were found.');
}

$errors = array();
foreach ($language_paths as $path) {
    $locale = basename($path, '.json');
    $keys = language_entries($path);
    $missing = array_values(array_diff($english_keys, $keys));
    $extra = array_values(array_diff($keys, $english_keys));
    if (count($missing)) {
        $errors[] = $locale . ' is missing translation keys: ' . implode(', ', $missing);
    }
    if (count($extra)) {
        $errors[] = $locale . ' has keys absent from en-gb: ' . implode(', ', $extra);
    }
    $catalog = json_decode(file_get_contents($path), true);
    $english_catalog = json_decode(file_get_contents($english_path), true);
    foreach ($english_keys as $key) {
        if (!isset($catalog[$key], $english_catalog[$key]) || !is_string($catalog[$key]) || !is_string($english_catalog[$key])) {
            continue;
        }
        if (language_tokens($catalog[$key]) !== language_tokens($english_catalog[$key])) {
            $errors[] = $locale . ' changes placeholders in ' . $key;
        }
        if (in_array($key, array('text_destination_category', 'text_destination_information', 'text_destination_page'), true) && substr($catalog[$key], -1) !== ' ') {
            $errors[] = $locale . ' must keep a trailing space in ' . $key;
        }
    }
}

if (count($errors)) {
    throw new RuntimeException(implode(PHP_EOL, $errors));
}

echo 'Validated translation key coverage for ' . count($language_paths) . ' shared admin languages against en-gb.' . PHP_EOL;
