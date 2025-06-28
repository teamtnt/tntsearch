<?php
namespace TeamTNT\TNTSearch\Tokenizer;

class FourgramTokenizer extends AbstractTokenizer implements TokenizerInterface
{

    public function tokenize($text, $stopwords = [])
    {
        return (new NGramTokenizer(4, 4))->tokenize($text, $stopwords);
    }
}
