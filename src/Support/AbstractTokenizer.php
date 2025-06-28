<?php
namespace TeamTNT\TNTSearch\Support;

/**
 * @deprecated Please use 'TeamTNT\TNTSearch\Tokenizer\AbstractTokenizer'.
 */
abstract class AbstractTokenizer
{
    static protected $pattern = '';

    public function getPattern()
    {
        if (empty(static::$pattern)) {
            throw new \LogicException("Tokenizer must define split \$pattern value");
        } else {
            return static::$pattern;
        }
    }
}
