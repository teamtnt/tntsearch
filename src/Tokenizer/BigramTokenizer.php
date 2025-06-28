<?php
namespace TeamTNT\TNTSearch\Tokenizer;

class BigramTokenizer extends AbstractTokenizer implements TokenizerInterface
{
    public function tokenize($text, $stopwords = [])
    {
        return (new NGramTokenizer(2, 2))->tokenize($text, $stopwords);
    }
}
