<?php
namespace TeamTNT\TNTSearch\Support;

class BigramTokenizer extends AbstractTokenizer implements TokenizerInterface
{
    public function tokenize($text, $stopwords = [])
    {
        $ngramTokenizer = new NGramTokenizer(2, 2);
        return $ngramTokenizer->tokenize($text, $stopwords);
    }
}
