<?php

namespace TeamTNT\TNTSearch;

class TNTFuzzyMatch
{
    public function norm($vec)
    {
        $norm       = 0;
        $components = count($vec);

        for ($i = 0; $i < $components; $i++) {
            $norm += $vec[$i] * $vec[$i];
        }

        return sqrt($norm);
    }

    public function dot($vec1, $vec2)
    {
        $prod       = 0;
        $components = count($vec1);

        for ($i = 0; $i < $components; $i++) {
            $prod += ($vec1[$i] * $vec2[$i]);
        }

        return $prod;
    }

    public function wordToVector($word)
    {
        $alphabet = "aAbBcCčČćĆdDđĐeEfFgGhHiIjJkKlLmMnNoOpPqQrRsSšŠtTvVuUwWxXyYzZžŽ1234567890'+ /";

        $result = [];
        foreach (str_split($word) as $w) {
            $result[] = strpos($alphabet, $w) + 1000000;
        }
        return $result;
    }

    public function angleBetweenVectors($a, $b)
    {
        $denominator = ($this->norm($a) * $this->norm($b));

        if ($denominator == 0) {
            return 0;
        }

        return $this->dot($a, $b) / $denominator;
    }

    public function hasCommonSubsequence($pattern, $str)
    {
        $pattern = mb_strtolower($pattern);
        $str     = mb_strtolower($str);

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

    public function makeVectorSameLength($str, $pattern)
    {
        $j   = 0;
        $max = max(count($pattern), count($str));
        $a   = [];
        $b   = [];

        for ($i = 0; $i < $max && $j < $max; $i++) {
            if (isset($pattern[$j]) && isset($str[$i]) && $pattern[$j] == $str[$i]) {
                $j++;
                $b[] = $str[$i];
            } else {
                $b[] = 0;
            }
        }

        return $b;
    }

    public function fuzzyMatchFromFile($pattern, $path)
    {
        $res   = [];
        $lines = fopen($path, "r");
        if ($lines) {
            while (!feof($lines)) {
                $line = fgets($lines, 4096);
                $line = str_replace("\r", "", $line);
                $line = str_replace("\n", "", $line);
                if ($this->hasCommonSubsequence($pattern, $line)) {
                    $res[] = $line;
                }
            }
            fclose($lines);
        }

        $paternVector = $this->wordToVector($pattern);

        $sorted = [];
        foreach ($res as $caseSensitiveWord) {
            $word                   = mb_strtolower(trim($caseSensitiveWord));
            $wordVector             = $this->wordToVector($word);
            $normalizedPaternVector = $this->makeVectorSameLength($wordVector, $paternVector);

            $angle = $this->angleBetweenVectors($wordVector, $normalizedPaternVector);

            if (strpos($word, $pattern) !== false) {
                $angle += 0.2;
            }
            $sorted[$caseSensitiveWord] = $angle;
        }

        arsort($sorted);
        return $sorted;
    }

    public function fuzzyMatch($pattern, $items)
    {
        $res = [];

        foreach ($items as $item) {
            if ($this->hasCommonSubsequence($pattern, $item)) {
                $res[] = $item;
            }
        }

        $paternVector = $this->wordToVector($pattern);

        $sorted = [];
        foreach ($res as $word) {
            $word                   = trim($word);
            $wordVector             = $this->wordToVector($word);
            $normalizedPaternVector = $this->makeVectorSameLength($wordVector, $paternVector);

            $angle = $this->angleBetweenVectors($wordVector, $normalizedPaternVector);

            if (strpos($word, $pattern) !== false) {
                $angle += 0.2;
            }

            $sorted[$word] = $angle;
        }

        arsort($sorted);

        return $sorted;
    }
}
