<?php

if (!function_exists('stringEndsWith')) {
    function stringEndsWith($haystack, $needle)
    {
        // search forward starting from end minus needle length characters
        return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== false);
    }
}

if (!function_exists('fuzzyMatch')) {
    function fuzzyMatch($pattern, $items)
    {
        $fm = new TeamTNT\TNTSearch\TNTFuzzyMatch;
        return $fm->fuzzyMatch($pattern, $items);
    }
}

if (!function_exists('fuzzyMatchFromFile')) {
    function fuzzyMatchFromFile($pattern, $path)
    {
        $fm = new TeamTNT\TNTSearch\TNTFuzzyMatch;
        return $fm->fuzzyMatchFromFile($pattern, $path);
    }
}
