<?php
namespace TeamTNT\TNTSearch\Tokenizer;

class TrigramTokenizer extends AbstractTokenizer implements TokenizerInterface
{

    public function tokenize($text, $stopwords = [])
    {
        $ngramTokenizer = new NGramTokenizer(3, 3);
        return $ngramTokenizer->tokenize($text, $stopwords);
    }
}
