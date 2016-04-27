<?php

if (!function_exists('dd')) {

    function dd()
    {
        array_map(function ($x) {var_dump($x);}, func_get_args());
        die;
    }
}

if (!function_exists('stringEndsWith')) {
    function stringEndsWith($haystack, $needle)
    {
        // search forward starting from end minus needle length characters
        return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== false);
    }
}
