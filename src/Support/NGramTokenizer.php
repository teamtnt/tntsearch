<?php
namespace TeamTNT\TNTSearch\Support;

class NGramTokenizer extends AbstractTokenizer implements TokenizerInterface
{
    public $min_gram = 3;
    public $max_gram = 3;

    public function __construct($min_gram = 3, $max_gram = 3)
    {
        $this->min_gram = $min_gram;
        $this->max_gram = $max_gram;
    }

    protected static $pattern = '/[\s,\.]+/';

    public function tokenize($text, $stopwords = [])
    {
        $text = mb_strtolower($text);

        $ngrams = [];
        $splits = preg_split($this->getPattern(), $text, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($splits as $split) {
            for ($currentGram = $this->min_gram; $currentGram <= $this->max_gram; $currentGram++) {
                for ($i = 0; $i <= strlen($split) - $currentGram; $i++) {
                    $ngrams[] = mb_substr($split, $i, $currentGram);
                }
            }
        }

        return $ngrams;
    }
}
