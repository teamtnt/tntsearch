<?php

namespace TeamTNT\TNTSearch\Stemmer;

class NoStemmer implements StemmerInterface
{
    public static function stem($word)
    {
        return $word;
    }
}