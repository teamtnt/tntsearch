<?php
namespace TeamTNT\TNTSearch\Support;

class Tokenizer implements TokenizerInterface
{
    public function tokenize($text)
    {
        return preg_split("/[^\p{L}\p{N}]+/u", strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
    }
}