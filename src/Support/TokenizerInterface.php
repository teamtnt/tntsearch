<?php
namespace TeamTNT\TNTSearch\Support;

interface TokenizerInterface
{
    public function tokenize($text, $stopwords);
}
