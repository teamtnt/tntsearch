<?php
namespace TeamTNT\TNTSearch\Tokenizer;

interface TokenizerInterface
{
    public function tokenize($text, $stopwords = []);

    public function getPattern();
}
