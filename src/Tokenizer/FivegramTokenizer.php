<?php
namespace TeamTNT\TNTSearch\Tokenizer;

class FivegramTokenizer extends AbstractTokenizer implements TokenizerInterface
{

    public function tokenize($text, $stopwords = [])
    {
        $ngramTokenizer = new NGramTokenizer(5, 5);
        return $ngramTokenizer->tokenize($text, $stopwords);
    }
}
