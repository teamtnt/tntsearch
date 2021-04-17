<?php
namespace TeamTNT\TNTSearch\Stemmer;

/**
 *
 * @link http://snowball.tartarus.org/algorithms/french/stemmer.html
 * The original author is wamania
 *
 */

class FrenchStemmer implements Stemmer
{
    /**
     * All french vowels
     */
    protected static $vowels = ['a', 'e', 'i', 'o', 'u', 'y', 'â', 'à', 'ë', 'é', 'ê', 'è', 'ï', 'î', 'ô', 'û', 'ù'];
    protected $word;

    /**
     * helper, contains stringified list of vowels
     * @var string
     */
    protected $plainVowels;

    /**
     * The original word, use to check if word has been modified
     * @var string
     */
    protected $originalWord;

    /**
     * RV value
     * @var string
     */
    protected $rv;

    /**
     * RV index (based on the beginning of the word)
     * @var int
     */
    protected $rvIndex;

    /**
     * R1 value
     * @var int
     */
    protected $r1;

    /**
     * R1 index (based on the beginning of the word)
     * @var int
     */
    protected $r1Index;

    /**
     * R2 value
     * @var int
     */
    protected $r2;

    /**
     * R2 index (based on the beginning of the word)
     * @var int
     */
    protected $r2Index;

    public static function stem($word)
    {
        return (new static)->analyze($word);
    }

    public function analyze($word)
    {
        $this->word = mb_strtolower($word);

        $this->plainVowels = implode('', static::$vowels);

        $this->step0();

        $this->rv();
        $this->r1();
        $this->r2();

        // to know if step1, 2a or 2b have altered the word
        $this->originalWord = $this->word;

        $nextStep = $this->step1();

        // Do step 2a if either no ending was removed by step 1, or if one of endings amment, emment, ment, ments was found.
        if (($nextStep == 2) || ($this->originalWord === $this->word) ) {
            $modified = $this->step2a();

            if (!$modified) {
                $this->step2b();
            }
        }

        if ($this->word != $this->originalWord) {
            $this->step3();

        } else {
            $this->step4();
        }

        $this->step5();
        $this->step6();
        $this->finish();

        return $this->word;
    }


    /**
     *  Assume the word is in lower case.
     *  Then put into upper case u or i preceded and followed by a vowel, and y preceded or followed by a vowel.
     *  u after q is also put into upper case. For example,
     *      jouer 		-> 		joUer
     *      ennuie 		-> 		ennuIe
     *      yeux 		-> 		Yeux
     *      quand 		-> 		qUand
     */
    private function step0()
    {
        $this->word = preg_replace('#([q])u#u', '$1U', $this->word);
        $this->word = preg_replace('#(['.$this->plainVowels.'])y#u', '$1Y', $this->word);
        $this->word = preg_replace('#y(['.$this->plainVowels.'])#u', 'Y$1', $this->word);
        $this->word = preg_replace('#(['.$this->plainVowels.'])u(['.$this->plainVowels.'])#u', '$1U$2', $this->word);
        $this->word = preg_replace('#(['.$this->plainVowels.'])i(['.$this->plainVowels.'])#u', '$1I$2', $this->word);
    }

    /**
     * Step 1
     * Search for the longest among the following suffixes, and perform the action indicated.
     *
     * @return integer Next step number
     */
    private function step1()
    {
        // ance   iqUe   isme   able   iste   eux   ances   iqUes   ismes   ables   istes
        //     delete if in R2
        if (($position = $this->search([
            'ances', 'iqUes', 'ismes', 'ables', 'istes', 'ance', 'iqUe','isme', 'able', 'iste', 'eux'
            ])) !== false) {
            if ($this->inR2($position)) {
                $this->word = mb_substr($this->word, 0, $position);
            }

            return 3;
        }

        // atrice   ateur   ation   atrices   ateurs   ations
        //      delete if in R2
        //      if preceded by ic, delete if in R2, else replace by iqU
        if (($position = $this->search(['atrices', 'ateurs', 'ations', 'atrice', 'ateur', 'ation'])) !== false) {
            if ($this->inR2($position)) {
                $this->word = mb_substr($this->word, 0, $position);

                if (($position2 = $this->searchIfInR2(['ic'])) !== false) {
                    $this->word = mb_substr($this->word, 0, $position2);
                } else {
                    $this->word = preg_replace('#(ic)$#u', 'iqU', $this->word);
                }
            }

            return 3;
        }

        // logie   logies
        //      replace with log if in R2
        if (($position = $this->search(['logies', 'logie'])) !== false) {
            if ($this->inR2($position)) {
                $this->word = preg_replace('#(logies|logie)$#u', 'log', $this->word);
            }

            return 3;
        }

        // usion   ution   usions   utions
        //      replace with u if in R2
        if (($position = $this->search(['usions', 'utions', 'usion', 'ution'])) !== false) {
            if ($this->inR2($position)) {
                $this->word = preg_replace('#(usion|ution|usions|utions)$#u', 'u', $this->word);
            }

            return 3;
        }

        // ence   ences
        //      replace with ent if in R2
        if (($position = $this->search(['ences', 'ence'])) !== false) {
            if ($this->inR2($position)) {
                $this->word = preg_replace('#(ence|ences)$#u', 'ent', $this->word);
            }

            return 3;
        }

        // issement   issements
        //      delete if in R1 and preceded by a non-vowel
        if (($position = $this->search(['issements', 'issement'])) != false) {
            if ($this->inR1($position)) {
                $before = $position - 1;
                $letter = mb_substr($this->word, $before, 1);

                if (! in_array($letter, static::$vowels)) {
                    $this->word = mb_substr($this->word, 0, $position);
                }
            }

            return 3;
        }

        // ement   ements
        //      delete if in RV
        //      if preceded by iv, delete if in R2 (and if further preceded by at, delete if in R2), otherwise,
        //      if preceded by eus, delete if in R2, else replace by eux if in R1, otherwise,
        //      if preceded by abl or iqU, delete if in R2, otherwise,
        //      if preceded by ièr or Ièr, replace by i if in RV
        if (($position = $this->search(['ements', 'ement'])) !== false) {
            if ($this->inRv($position)) {
                $this->word = mb_substr($this->word, 0, $position);
            }

            if (($position = $this->searchIfInR2(['iv'])) !== false) {
                $this->word = mb_substr($this->word, 0, $position);

                if (($position2 = $this->searchIfInR2(['at'])) !== false) {
                    $this->word = mb_substr($this->word, 0, $position2);
                }
            } elseif (($position = $this->search(['eus'])) !== false) {
                if ($this->inR2($position)) {
                    $this->word = mb_substr($this->word, 0, $position);
                } elseif ($this->inR1($position)) {
                    $this->word = preg_replace('#(eus)$#u', 'eux', $this->word);
                }
            } elseif (($position = $this->searchIfInR2(['abl', 'iqU'])) !== false) {
                $this->word = mb_substr($this->word, 0, $position);
            } elseif (($this->searchIfInRv(['ièr', 'Ièr'])) !== false) {
                $this->word = preg_replace('#(ièr|Ièr)$#u', 'i', $this->word);
            }

            return 3;
        }

        // ité   ités
        //      delete if in R2
        //      if preceded by abil, delete if in R2, else replace by abl, otherwise,
        //      if preceded by ic, delete if in R2, else replace by iqU, otherwise,
        //      if preceded by iv, delete if in R2
        if (($position = $this->search(['ités', 'ité'])) !== false) {
            // delete if in R2
            if ($this->inR2($position)) {
                $this->word = mb_substr($this->word, 0, $position);
            }

            // if preceded by abil, delete if in R2, else replace by abl, otherwise,
            if (($position = $this->search(['abil'])) !== false) {
                if ($this->inR2($position)) {
                    $this->word = mb_substr($this->word, 0, $position);
                } else {
                    $this->word = preg_replace('#(abil)$#u', 'abl', $this->word);
                }

                // if preceded by ic, delete if in R2, else replace by iqU, otherwise,
            } elseif (($position = $this->search(['ic'])) !== false) {
                if ($this->inR2($position)) {
                    $this->word = mb_substr($this->word, 0, $position);
                } else {
                    $this->word = preg_replace('#(ic)$#u', 'iqU', $this->word);
                }

                // if preceded by iv, delete if in R2
            } elseif (($position = $this->searchIfInR2(['iv'])) !== false) {
                $this->word = mb_substr($this->word, 0, $position);
            }

            return 3;
        }

        // if   ive   ifs   ives
        //      delete if in R2
        //      if preceded by at, delete if in R2 (and if further preceded by ic, delete if in R2, else replace by iqU)
        if (($position = $this->search(['ifs', 'ives', 'if', 'ive'])) !== false) {
            if ($this->inR2($position)) {
                $this->word = mb_substr($this->word, 0, $position);
            }

            if (($position = $this->searchIfInR2(['at'])) !== false) {
                $this->word = mb_substr($this->word, 0, $position);

                if (($position2 = $this->search(['ic'])) !== false) {
                    if ($this->inR2($position2)) {
                        $this->word = mb_substr($this->word, 0, $position2);
                    } else {
                        $this->word = preg_replace('#(ic)$#u', 'iqU', $this->word);
                    }
                }
            }

            return 3;
        }

        // eaux
        //      replace with eau
        if (($this->search(['eaux'])) !== false) {
            $this->word = preg_replace('#(eaux)$#u', 'eau', $this->word);

            return 3;
        }

        // aux
        //      replace with al if in R1
        if (($position = $this->search(['aux'])) !== false) {
            if ($this->inR1($position)) {
                $this->word = preg_replace('#(aux)$#u', 'al', $this->word);
            }

            return 3;
        }

        // euse   euses
        //      delete if in R2, else replace by eux if in R1
        if (($position = $this->search(['euses', 'euse'])) !== false) {
            if ($this->inR2($position)) {
                $this->word = mb_substr($this->word, 0, $position);
            } elseif ($this->inR1($position)) {
                $this->word = preg_replace('#(euses|euse)$#u', 'eux', $this->word);

            }

            return 3;
        }

        // amment
        //      replace with ant if in RV
        if ( ($position = $this->search(['amment'])) !== false) {
            if ($this->inRv($position)) {
                $this->word = preg_replace('#(amment)$#u', 'ant', $this->word);
            }
            return 2;
        }

        // emment
        //      replace with ent if in RV
        if (($position = $this->search(['emment'])) !== false) {
            if ($this->inRv($position)) {
                $this->word = preg_replace('#(emment)$#u', 'ent', $this->word);
            }

            return 2;
        }

        // ment   ments
        //      delete if preceded by a vowel in RV
        if (($position = $this->search(['ments', 'ment'])) != false) {
            $before = $position - 1;
            $letter = mb_substr($this->word, $before, 1);

            if ($this->inRv($before) && (in_array($letter, static::$vowels)) ) {
                $this->word = mb_substr($this->word, 0, $position);
            }

            return 2;
        }

        return 2;
    }

    /**
     * Step 2a: Verb suffixes beginning i
     *  In steps 2a and 2b all tests are confined to the RV region.
     *  Search for the longest among the following suffixes and if found, delete if preceded by a non-vowel.
     *      îmes   ît   îtes   i   ie   ies   ir   ira   irai   iraIent   irais   irait   iras   irent   irez   iriez
     *      irions   irons   iront   is   issaIent   issais   issait   issant   issante   issantes   issants   isse
     *      issent   isses   issez   issiez   issions   issons   it
     *  (Note that the non-vowel itself must also be in RV.)
     */
    private function step2a()
    {
        if (($position = $this->searchIfInRv([
                'îmes', 'îtes', 'ît', 'ies', 'ie', 'iraIent', 'irais', 'irait', 'irai', 'iras', 'ira', 'irent', 'irez', 'iriez',
                'irions', 'irons', 'iront', 'ir', 'issaIent', 'issais', 'issait', 'issant', 'issantes', 'issante', 'issants',
                'issent', 'isses', 'issez', 'isse', 'issiez', 'issions', 'issons', 'is', 'it', 'i'])) !== false) {

            $before = $position - 1;
            $letter = mb_substr($this->word, $before, 1);

            if ( $this->inRv($before) && (!in_array($letter, static::$vowels)) ) {
                $this->word = mb_substr($this->word, 0, $position);

                return true;
            }
        }

        return false;
    }

    /**
     * Do step 2b if step 2a was done, but failed to remove a suffix.
     * Step 2b: Other verb suffixes
     */
    private function step2b()
    {
        // é   ée   ées   és   èrent   er   era   erai   eraIent   erais   erait   eras   erez   eriez   erions   erons   eront   ez   iez
        //      delete
        if (($position = $this->searchIfInRv([
                'ées', 'èrent', 'erais', 'erait', 'erai', 'eraIent', 'eras', 'erez', 'eriez',
                'erions', 'erons', 'eront', 'era', 'er', 'iez', 'ez','és', 'ée', 'é'])) !== false) {

            $this->word = mb_substr($this->word, 0, $position);

            return true;
        }

        // âmes   ât   âtes   a   ai   aIent   ais   ait   ant   ante   antes   ants   as   asse   assent   asses   assiez   assions
        //      delete
        //      if preceded by e, delete
        if (($position = $this->searchIfInRv([
                'âmes', 'âtes', 'ât', 'aIent', 'ais', 'ait', 'antes', 'ante', 'ants', 'ant',
                'assent', 'asses', 'assiez', 'assions', 'asse', 'as', 'ai', 'a'])) !== false) {

            $before = $position - 1;
            $letter = mb_substr($this->word, $before, 1);

            if ( $this->inRv($before) && ($letter === 'e') ) {
                $this->word = mb_substr($this->word, 0, $before);
            } else {
                $this->word = mb_substr($this->word, 0, $position);
            }

            return true;
        }

        // ions
        //      delete if in R2
        if ( ($position = $this->searchIfInRv(array('ions'))) !== false) {
            if ($this->inR2($position)) {
                $this->word = mb_substr($this->word, 0, $position);
            }

            return true;
        }

        return false;
    }

    /**
     * Step 3: Replace final Y with i or final ç with c
     */
    private function step3()
    {
        $this->word = preg_replace('#(Y)$#u', 'i', $this->word);
        $this->word = preg_replace('#(ç)$#u', 'c', $this->word);
    }

    /**
     * Step 4: Residual suffix
     */
    private function step4()
    {
        //If the word ends s, not preceded by a, i, o, u, è or s, delete it.
        if (preg_match('#[^aiouès]s$#', $this->word)) {
            $this->word = mb_substr($this->word, 0, -1);
        }

        // In the rest of step 4, all tests are confined to the RV region.
        // ion
        //      delete if in R2 and preceded by s or t
        if ((($position = $this->searchIfInRv(['ion'])) !== false) && ($this->inR2($position)) ) {
            $before = $position - 1;
            $letter = mb_substr($this->word, $before, 1);

            if ( $this->inRv($before) && (($letter === 's') || ($letter === 't')) ) {
                $this->word = mb_substr($this->word, 0, $position);
            }

            return true;
        }

        // ier   ière   Ier   Ière
        //      replace with i
        if (($this->searchIfInRv(['ier', 'ière', 'Ier', 'Ière'])) !== false) {
            $this->word = preg_replace('#(ier|ière|Ier|Ière)$#u', 'i', $this->word);

            return true;
        }

        // e
        //      delete
        if (($this->searchIfInRv(['e'])) !== false) {
            $this->word = mb_substr($this->word, 0, -1);

            return true;
        }

        // ë
        //      if preceded by gu, delete
        if (($position = $this->searchIfInRv(['guë'])) !== false) {
            if ($this->inRv($position + 2)) {
                $this->word = mb_substr($this->word, 0, -1);
                
                return true;
            }
        }

        return false;
    }

    /**
     * Step 5: Undouble
     * If the word ends enn, onn, ett, ell or eill, delete the last letter
     */
    private function step5()
    {
        if ($this->search(['enn', 'onn', 'ett', 'ell', 'eill']) !== false) {
            $this->word = mb_substr($this->word, 0, -1);
        }
    }

    /**
     * Step 6: Un-accent
     * If the words ends é or è followed by at least one non-vowel, remove the accent from the e.
     */
    private function step6()
    {
        $this->word = preg_replace('#(é|è)([^'.$this->plainVowels.']+)$#u', 'e$2', $this->word);
    }

    /**
     * And finally:
     * Turn any remaining I, U and Y letters in the word back into lower case.
     */
    private function finish()
    {
        $this->word = str_replace(['I','U','Y'], ['i', 'u', 'y'], $this->word);
    }

    /**
     *  If the word begins with two vowels, RV is the region after the third letter,
     *  otherwise the region after the first vowel not at the beginning of the word,
     *  or the end of the word if these positions cannot be found.
     *  (Exceptionally, par, col or tap, at the begining of a word is also taken to define RV as the region to their right.)
     */
    protected function rv()
    {
        $length = mb_strlen($this->word);

        $this->rv = '';
        $this->rvIndex = $length;

        if ($length < 3) {
            return true;
        }

        // If the word begins with two vowels, RV is the region after the third letter
        $first = mb_substr($this->word, 0, 1);
        $second = mb_substr($this->word, 1, 1);

        if ( (in_array($first, static::$vowels)) && (in_array($second, static::$vowels)) ) {
            $this->rv = mb_substr($this->word, 3);
            $this->rvIndex = 3;
            
            return true;
        }

        // (Exceptionally, par, col or tap, at the begining of a word is also taken to define RV as the region to their right.)
        $begin3 = mb_substr($this->word, 0, 3);
        
        if (in_array($begin3, ['par', 'col', 'tap'])) {
            $this->rv = mb_substr($this->word, 3);
            $this->rvIndex = 3;
            
            return true;
        }

        //  otherwise the region after the first vowel not at the beginning of the word,
        for ($i = 1; $i < $length; ++$i) {
            $letter = mb_substr($this->word, $i, 1);
            
            if (in_array($letter, static::$vowels)) {
                $this->rv = mb_substr($this->word, ($i + 1));
                $this->rvIndex = $i + 1;
                
                return true;
            }
        }

        return false;
    }

    protected function inRv($position)
    {
        return ($position >= $this->rvIndex);
    }

    protected function inR1($position)
    {
        return ($position >= $this->r1Index);
    }

    protected function inR2($position)
    {
        return ($position >= $this->r2Index);
    }

    protected function searchIfInRv($suffixes)
    {
        return $this->search($suffixes, $this->rvIndex);
    }

    protected function searchIfInR2($suffixes)
    {
        return $this->search($suffixes, $this->r2Index);
    }

    protected function search($suffixes, $offset = 0)
    {
        $length = mb_strlen($this->word);

        if ($offset > $length) {
            return false;
        }

        foreach ($suffixes as $suffixe) {
            if ((($position = mb_strrpos($this->word, $suffixe, $offset)) !== false)
                && ((mb_strlen($suffixe) + $position) == $length)) {
                return $position;
            }
        }

        return false;
    }

    /**
     * R1 is the region after the first non-vowel following a vowel, or the end of the word if there is no such non-vowel.
     */
    protected function r1()
    {
        list($this->r1Index, $this->r1) = $this->rx($this->word);
    }

    /**
     * R2 is the region after the first non-vowel following a vowel in R1, or the end of the word if there is no such non-vowel.
     */
    protected function r2()
    {
        list($index, $value) = $this->rx($this->r1);

        $this->r2 = $value;
        $this->r2Index = $this->r1Index + $index;
    }

    /**
     * Common function for R1 and R2
     * Search the region after the first non-vowel following a vowel in $word, or the end of the word if there is no such non-vowel.
     * R1 : $in = $this->word
     * R2 : $in = R1
     */
    protected function rx($in)
    {
        $length = mb_strlen($in);

        // defaults
        $value = '';
        $index = $length;

        // we search all vowels
        $vowels = [];
        
        for ($i = 0; $i < $length; ++$i) {
            $letter = mb_substr($in, $i, 1);
            
            if (in_array($letter, static::$vowels)) {
                $vowels[] = $i;
            }
        }

        // search the non-vowel following a vowel
        foreach ($vowels as $position) {
            $after = $position + 1;
            $letter = mb_substr($in, $after, 1);

            if (!in_array($letter, static::$vowels)) {
                $index = $after + 1;
                $value = mb_substr($in, ($after + 1));

                break;
            }
        }

        return [$index, $value];
    }
}
