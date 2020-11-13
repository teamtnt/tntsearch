<?php
namespace TeamTNT\TNTSearch\Support;

class ProductTokenizer extends AbstractTokenizer implements TokenizerInterface
{
    static protected $pattern = '/[\s,\.]+/';

    public function tokenize($text, $stopwords = [])
    {
        $text  = mb_strtolower($text);
        $split = preg_split($this->getPattern(), $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_diff($split, $stopwords);
    }
}
