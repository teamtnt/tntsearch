<?php
namespace TeamTNT\TNTSearch\Support;

/**
 * @deprecated Please use 'TeamTNT\TNTSearch\Tokenizer\TokenizerInterface'.
 */
interface TokenizerInterface
{
    public function tokenize($text, $stopwords);

    public function getPattern();
}
