<?php
namespace TeamTNT\TNTSearch\Tokenizer;

class TrigramTokenizer extends AbstractTokenizer implements TokenizerInterface
{

    public function tokenize($text, $stopwords = [])
    {
        return (new NGramTokenizer(3, 3))->tokenize($text, $stopwords);
    }
}
