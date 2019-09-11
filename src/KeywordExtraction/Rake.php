<?php

namespace TeamTNT\TNTSearch\KeywordExtraction;

class Rake
{
    public function __construct($language = "english")
    {
        $stopwords       = file_get_contents(__DIR__."/../Stopwords/".$language.".json");
        $this->stopwords = json_decode($stopwords);
    }

    public function extractKeywords($text, $includeScores = true)
    {
        $phraseList   = $this->generateCandidateKeywords($text);
        $wordScores   = $this->calculateWordScores($phraseList);
        $phraseScores = $this->calculatePhraseScores($phraseList, $wordScores);

        arsort($phraseScores);
        $oneThird = ceil(count($phraseScores) / 3) + 1;

        $phraseScores = array_slice($phraseScores, 0, $oneThird);
        if ($includeScores) {
            return $phraseScores;
        }
        return array_keys($phraseScores);
    }

    public function generateCandidateKeywords($text)
    {
        $phraseList = [];

        $words  = $this->tokenize($text);
        $phrase = [];

        foreach ($words as $word) {
            if (in_array($word, $this->stopwords) || ctype_punct($word)) {
                if (count($phrase) > 0) {
                    $phraseList[] = $phrase;
                    $phrase       = [];
                }
            } else {
                $phrase[] = $word;
            }
        }

        if (count($phrase) > 0) {
            $phraseList[] = $phrase;
            $phrase       = [];
        }

        return $phraseList;
    }

    public function calculatePhraseScores($phraseList, $wordScores)
    {

        $result = [];

        foreach ($phraseList as $phrase) {
            $wordScore = 0;

            foreach ($phrase as $word) {
                $wordScore += $wordScores[$word];
            }

            $result[implode(" ", $phrase)] = $wordScore;
        }

        return $result;
    }

    public function calculateWordScores($phraseList)
    {
        $result = [];

        foreach ($phraseList as $phrase) {
            foreach ($phrase as $word) {
                $wordScore     = $this->wordDegree($word, $phraseList) / $this->wordFrequency($word, $phraseList);
                $result[$word] = $wordScore;
            }
        }
        return $result;
    }

    public function wordDegree($word, $phraseList)
    {
        $count = 0;

        foreach ($phraseList as $phrase) {
            foreach ($phrase as $p) {
                if ($p == $word) {
                    $count += count($phrase);
                }
            }
        }
        return $count;
    }

    public function wordFrequency($word, $phraseList)
    {
        $count = 0;

        foreach ($phraseList as $phrase) {
            foreach ($phrase as $p) {
                if ($p == $word) {
                    $count++;
                }
            }
        }
        return $count;
    }

    public function returnFormatedPharaseList($phraseList)
    {
        $formatedList = [];
        foreach ($phraseList as $phrase) {
            $formatedList[] = implode(" ", $phrase);
        }
        return $formatedList;
    }

    public function tokenize($str)
    {
        $str = mb_strtolower($str);

        $arr = [];
        // for the character classes
        // see http://php.net/manual/en/regexp.reference.unicode.php
        $pat = '/
                    ([\pZ\pC]*)         # match any separator or other
                                        # in sequence
                    (
                        [^\pP\pZ\pC]+ | # match a sequence of characters
                                        # that are not punctuation,
                                        # separator or other
                        .               # match punctuations one by one
                    )
                    ([\pZ\pC]*)         # match a sequence of separators
                                        # that follows
                /xu';

        preg_match_all($pat, $str, $arr);
        return $arr[2];
    }

}
