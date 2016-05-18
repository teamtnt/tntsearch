<?php

namespace TeamTNT\TNTSearch\Stemmer;

class NoStemmer implements Stemmer
{
    public static function stem($word)
    {
        return $word;
    }
}