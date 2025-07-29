<?php

namespace TeamTNT\TNTSearch\Support;

class Str
{
    public static function isValidUtf8(string $text): bool
    {
        return preg_match('/./u', $text) === 1;
    }
}
