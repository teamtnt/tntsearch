<?php
namespace TeamTNT\TNTSearch\Support;

class EdgeNgramTokenizer extends AbstractTokenizer implements TokenizerInterface
{
    protected static $pattern = '/[\s,\.]+/';

    public function tokenize($text, $stopwords = [])
    {
        $text = mb_strtolower($text);

        $ngrams = [];
        $splits = preg_split($this->getPattern(), $text, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($splits as $split) {
            for ($i = 2; $i <= strlen($split); $i++) {
                $ngrams[] = mb_substr($split, 0, $i);
            }
        }

        return $ngrams;
    }
}
