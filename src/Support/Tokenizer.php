<?php
namespace TeamTNT\TNTSearch\Support;

class Tokenizer implements TokenizerInterface
{
    public function tokenize($text, $stopwords = [])
    {
        $text  = mb_strtolower($text);
        $split = preg_split("/[^\p{L}\p{N}]+/u", $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_diff($split, $stopwords);
    }
}
