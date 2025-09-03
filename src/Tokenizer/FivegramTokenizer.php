<?php
namespace TeamTNT\TNTSearch\Tokenizer;

class FivegramTokenizer extends AbstractTokenizer implements TokenizerInterface
{

    public function tokenize($text, $stopwords = [])
    {
        return (new NGramTokenizer(5, 5))->tokenize($text, $stopwords);
    }
}
