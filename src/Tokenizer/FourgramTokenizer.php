<?php
namespace TeamTNT\TNTSearch\Tokenizer;

class FourgramTokenizer extends AbstractTokenizer implements TokenizerInterface
{

    public function tokenize($text, $stopwords = [])
    {
        $ngramTokenizer = new NGramTokenizer(4, 4);
        return $ngramTokenizer->tokenize($text, $stopwords);
    }
}
