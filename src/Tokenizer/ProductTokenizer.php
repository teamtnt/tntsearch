<?php
namespace TeamTNT\TNTSearch\Tokenizer;

class ProductTokenizer extends AbstractTokenizer implements TokenizerInterface
{
    static protected $pattern = '/[\s,\.]+/';

    public function tokenize($text, $stopwords = [])
    {
        if (!is_scalar($text)) {
            return [];
        }

        $text  = mb_strtolower((string)$text);
        $split = preg_split($this->getPattern(), $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_diff($split, $stopwords);
    }
}
