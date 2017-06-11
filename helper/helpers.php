<?php

if (!function_exists('stringEndsWith')) {
    function stringEndsWith($haystack, $needle)
    {
        // search forward starting from end minus needle length characters
        return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== false);
    }
}

if (!function_exists('fuzzyMatch')) {
    function fuzzyMatch($pattern, $str)
    {
        $j             = 0;
        $patternLength = strlen($pattern);
        $strLength     = strlen($str);

        for ($i = 0; $i < $strLength && $j < $patternLength; $i++) {
            if ($pattern[$j] == $str[$i]) {
                $j++;
            }
        }

        return ($j == $patternLength);
    }
}