<?php

namespace TeamTNT\TNTSearch\Spell;

class JaroWinklerDistance
{
    private $threshold = 0.7;

    public function getDistance($str1, $str2)
    {
        $j = $this->jaro($str1, $str2);
        if ($j < $this->threshold) {
            return $j;
        }

        $lengthOfCommonPrefix = 0;
        for ($i = 0; $i < min(strlen($str1), strlen($str2)); $i++) {
            if ($str1[$i] == $str2[$i]) {
                $lengthOfCommonPrefix++;
            } else {
                break;
            }
        }

        $lp = min(0.1, 1 / max(strlen($str1), strlen($str2))) * $lengthOfCommonPrefix;
        $jw = $j + ($lp * (1 - $j));
        return $jw;
    }

    public function jaro($str1, $str2)
    {
        // length of the strings
        $str1_len = strlen($str1);
        $str2_len = strlen($str2);

        // if both strings are empty return 1
        // if only one of the strings is empty return 0
        if ($str1_len == 0) {
            return $str2_len == 0 ? 1 : 0;
        }

        // max distance between two chars to be considered matching
        $match_distance = max($str1_len, $str2_len) / 2 - 1;

        $str1_matches = array_fill(0, $str1_len, 0);

        $str2_matches = array_fill(0, $str2_len, 0);

        // number of matches and transpositions
        $matches        = 0;
        $transpositions = 0;

        // find the matches
        for ($i = 0; $i < $str1_len; $i++) {
            // start and end take into account the match distance
            $start = (int) max(0, $i - $match_distance);
            $end   = (int) min($i + $match_distance + 1, $str2_len);

            for ($k = $start; $k < $end; $k++) {
                // if $str2 already has a match continue
                if ($str2_matches[$k]) {
                    continue;
                }

                // if str1 and str2 are not
                if ($str1[$i] != $str2[$k]) {
                    continue;
                }

                // otherwise assume there is a match
                $str1_matches[$i] = true;
                $str2_matches[$k] = true;
                $matches++;
                break;
            }
        }

        // if there are no matches return 0
        if ($matches == 0) {
            return 0.0;
        }

        // count transpositions
        $k = 0;
        for ($i = 0; $i < $str1_len; $i++) {
            // if there are no matches in str1 continue
            if (!$str1_matches[$i]) {
                continue;
            }

            // while there is no match in str2 increment k
            while (!$str2_matches[$k]) {
                $k++;
            }

            // increment transpositions
            if ($str1[$i] != $str2[$k]) {
                $transpositions++;
            }

            $k++;
        }

        // divide the number of transpositions by two as per the algorithm specs
        // this division is valid because the counted transpositions include both
        // instances of the transposed characters.
        $transpositions /= 2.0;

        // return the Jaro distance
        return (($matches / $str1_len) +
            ($matches / $str2_len) +
            (($matches - $transpositions) / $matches)) / 3.0;
    }
}
