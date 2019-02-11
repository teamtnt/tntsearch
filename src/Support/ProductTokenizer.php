<?php
namespace TeamTNT\TNTSearch\Support;

class ProductTokenizer implements TokenizerInterface
{
    public function tokenize($text, $stopwords = [])
    {
        $text  = mb_strtolower($text);
        $split = preg_split("/[\s,]+/", $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_diff($split, $stopwords);
    }
}
