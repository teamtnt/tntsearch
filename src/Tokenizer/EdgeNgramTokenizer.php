<?php
namespace TeamTNT\TNTSearch\Tokenizer;

class EdgeNgramTokenizer extends AbstractTokenizer implements TokenizerInterface
{
    protected static $pattern = '/[\s,\.]+/';

    public function tokenize($text, $stopwords = [])
    {
        if (!is_scalar($text)) {
            return [];
        }

        $text = mb_strtolower((string)$text);

        $ngrams = [];
        $splits = preg_split($this->getPattern(), $text, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($splits as $split) {
            for ($i = 2; $i <= mb_strlen($split); $i++) {
                $ngrams[] = mb_substr($split, 0, $i);
            }
        }

        return $ngrams;
    }
}
