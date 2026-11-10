<?php
if (!function_exists('pll_languages_list')) {
    function pll_languages_list($args = array())
    {
        $fields = is_array($args) && isset($args['fields']) ? $args['fields'] : 'slug';
        if ($fields === 'locale') {
            return array('en_GB', 'lt_LT');
        }
        if ($fields === 'name') {
            return array('English (United Kingdom)', 'Lietuvių');
        }

        return array('en', 'lt');
    }
}

if (!function_exists('pll_current_language')) {
    function pll_current_language($field = 'slug')
    {
        $locale = isset($_COOKIE['appnuno_test_locale']) ? (string) $_COOKIE['appnuno_test_locale'] : 'en_GB';
        if ($locale !== 'lt_LT' && $locale !== 'en_GB') {
            $locale = 'en_GB';
        }
        if ($field === 'locale') {
            return $locale;
        }

        return $locale === 'lt_LT' ? 'lt' : 'en';
    }
}
