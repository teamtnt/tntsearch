<?php

namespace TeamTNT\TNTSearch\Stemmer;

/*
 *  The following code, downloaded from <https://www.drupal.org/project/italianstemmer>,
 *  was originally written by Roberto Mirizzi (<roberto.mirizzi@gmail.com>,
 *  <http://sisinflab.poliba.it/mirizzi/>) in February 2007. It was the PHP5 implementation
 *  of Martin Porter's stemming algorithm for Italian language. This algorithm can be found
 *  at the address: <http://snowball.tartarus.org/algorithms/italian/stemmer.html>.
 *
 *  It was rewritten in March 2017 for TNTSearch by GaspariLab S.r.l., <dev@gasparilab.it>.
 */

/*
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

class ItalianStemmer implements Stemmer
{
    private static $cache = [];

    private static $vocali = ['a', 'e', 'i', 'o', 'u', 'à', 'è', 'ì', 'ò', 'ù'];
    private static $consonanti = [
        'b', 'c', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'm', 'n', 'p', 'q', 'r', 's', 't', 'v', 'w', 'x', 'y', 'z',
        'I', 'U',
    ];
    private static $accenti_acuti = ['á', 'é', 'í', 'ó', 'ú'];
    private static $accenti_gravi = ['à', 'è', 'ì', 'ò', 'ù'];

    private static $suffissi_step0 = [
        'ci', 'gli', 'la', 'le', 'li', 'lo', 'mi', 'ne', 'si', 'ti', 'vi', 'sene',
        'gliela', 'gliele', 'glieli', 'glielo', 'gliene', 'mela', 'mele', 'meli', 'melo', 'mene', 'tela', 'tele',
        'teli', 'telo', 'tene', 'cela', 'cele', 'celi', 'celo', 'cene', 'vela', 'vele', 'veli', 'velo', 'vene',
    ];

    private static $suffissi_step1_a = [
        'anza', 'anze', 'ico', 'ici', 'ica', 'ice', 'iche', 'ichi', 'ismo', 'ismi', 'abile', 'abili', 'ibile',
        'ibili', 'ista', 'iste', 'isti', 'istà', 'istè', 'istì', 'oso', 'osi', 'osa', 'ose', 'mente', 'atrice',
        'atrici', 'ante', 'anti',
    ];
    private static $suffissi_step1_b = ['azione', 'azioni', 'atore', 'atori'];
    private static $suffissi_step1_c = ['logia', 'logie'];
    private static $suffissi_step1_d = ['uzione', 'uzioni', 'usione', 'usioni'];
    private static $suffissi_step1_e = ['enza', 'enze'];
    private static $suffissi_step1_f = ['amento', 'amenti', 'imento', 'imenti'];
    private static $suffissi_step1_g = ['amente'];
    private static $suffissi_step1_h = ['ità'];
    private static $suffissi_step1_i = ['ivo', 'ivi', 'iva', 'ive'];

    private static $suffissi_step2 = [
        'ammo', 'ando', 'ano', 'are', 'arono', 'asse', 'assero', 'assi', 'assimo', 'ata', 'ate', 'ati', 'ato', 'ava',
        'avamo', 'avano', 'avate', 'avi', 'avo', 'emmo', 'enda', 'ende', 'endi', 'endo', 'erà', 'erai', 'eranno',
        'ere', 'erebbe', 'erebbero', 'erei', 'eremmo', 'eremo', 'ereste', 'eresti', 'erete', 'erò', 'erono', 'essero',
        'ete', 'eva', 'evamo', 'evano', 'evate', 'evi', 'evo', 'Yamo', 'iamo', 'immo', 'irà', 'irai', 'iranno', 'ire',
        'irebbe', 'irebbero', 'irei', 'iremmo', 'iremo', 'ireste', 'iresti', 'irete', 'irò', 'irono', 'isca',
        'iscano', 'isce', 'isci', 'isco', 'iscono', 'issero', 'ita', 'ite', 'iti', 'ito', 'iva', 'ivamo', 'ivano',
        'ivate', 'ivi', 'ivo', 'ono', 'uta', 'ute', 'uti', 'uto', 'ar', 'ir',
    ];

    private static $ante_suff_a = ['ando', 'endo'];
    private static $ante_suff_b = ['ar', 'er', 'ir'];

    public function __construct()
    {
        usort(self::$suffissi_step0, create_function('$a,$b', 'return mb_strlen($a)>mb_strlen($b) ? -1 : 1;'));
        usort(self::$suffissi_step1_a, create_function('$a,$b', 'return mb_strlen($a)>mb_strlen($b) ? -1 : 1;'));
        usort(self::$suffissi_step2, create_function('$a,$b', 'return mb_strlen($a)>mb_strlen($b) ? -1 : 1;'));
    }

    /**
     * Gets the stem of $word.
     *
     * @param string $word
     *
     * @return string
     */
    public static function stem($word)
    {
        $word = mb_strtolower($word);

        // Check for invalid characters
        preg_match('#.#u', $word);
        if (preg_last_error() !== 0) {
            throw new \InvalidArgumentException('Word "'.$word.'" seems to be errornous.
                Error code from preg_last_error(): '.preg_last_error());
        }

        if (!isset(self::$cache[$word])) {
            $result = self::getStem($word);
            self::$cache[$word] = $result;
        }

        return self::$cache[$word];
    }

    /**
     * @param $word
     *
     * @return string
     */
    private static function getStem($word)
    {
        $str = self::trim($word);
        $str = self::toLower($str);
        $str = self::replaceAccAcuti($str);
        $str = self::putUAfterQToUpper($str);
        $str = self::IUBetweenVowToUpper($str);
        $step0 = self::step0($str);
        $step1 = self::step1($step0);
        $step2 = self::step2($step0, $step1);
        $step3a = self::step3a($step2);
        $step3b = self::step3b($step3a);
        $step4 = self::step4($step3b);

        return $step4;
    }

    private static function trim($str)
    {
        return trim($str);
    }

    private static function toLower($str)
    {
        return strtolower($str);
    }

    private static function replaceAccAcuti($str)
    {
        return str_replace(self::$accenti_acuti, self::$accenti_gravi, $str); //strtr
    }

    private static function putUAfterQToUpper($str)
    {
        return str_replace('qu', 'qU', $str);
    }

    private static function IUBetweenVowToUpper($str)
    {
        $pattern = '/([aeiouàèìòù])([iu])([aeiouàèìòù])/';

        return preg_replace_callback($pattern, function ($matches) {
            return strtoupper($matches[0]);
        }, $str);
    }

    private static function returnRV($str)
    {
        /*
        If the second letter is a consonant, RV is the region after the next following vowel,
        or if the first two letters are vowels, RV is the region after the next consonant, and otherwise
        (consonant-vowel case) RV is the region after the third letter.
        But RV is the end of the word if these positions cannot be found. Example:
        m a c h o [ho]     o l i v a [va]     t r a b a j o [bajo]     á u r e o [eo] prezzo sprezzante
        */

        if (mb_strlen($str) < 2) {
            return '';
        } //$str;

        if (in_array($str[1], self::$consonanti)) {
            $str = mb_substr($str, 2);
            $str = strpbrk($str, implode(self::$vocali));

            return mb_substr($str, 1); //secondo me devo mettere 1
        } elseif (in_array($str[0], self::$vocali) && in_array($str[1], self::$vocali)) {
            $str = strpbrk($str, implode(self::$consonanti));

            return mb_substr($str, 1);
        } elseif (in_array($str[0], self::$consonanti) && in_array($str[1], self::$vocali)) {
            return mb_substr($str, 3);
        }
    }

    private static function returnR1($str)
    {
        /*
        R1 is the region after the first non-vowel following a vowel, or is the null region at the end
        of the word if there is no such non-vowel. Example:
        beautiful [iful]	beauty [y]	beau [NULL]	animadversion [imadversion]	sprinkled [kled]	eucharist [harist]
        */

        $pattern = '/['.implode(self::$vocali).']+'.'['.implode(self::$consonanti).']'.'(.*)/';
        preg_match($pattern, $str, $matches);

        return count($matches) >= 1 ? $matches[1] : '';
    }

    private static function returnR2($str)
    {
        /*
        R2 is the region after the first non-vowel following a vowel in R1, or is the null region at the end
        of the word if there is no such non-vowel. Example:
        beautiful [ul]	beauty [NULL]	beau [NULL]	animadversion [adversion]	sprinkled [NULL]	eucharist [ist]
        */

        $R1 = self::returnR1($str);

        $pattern = '/['.implode(self::$vocali).']+'.'['.implode(self::$consonanti).']'.'(.*)/';
        preg_match($pattern, $R1, $matches);

        return count($matches) >= 1 ? $matches[1] : '';
    }

    private static function step0($str)
    {
        //Step 0: Attached pronoun
        //Always do steps 0

        $str_len = mb_strlen($str);
        $rv = self::returnRV($str);
        $rv_len = mb_strlen($rv);

        $pos = 0;
        foreach (self::$suffissi_step0 as $suff) {
            if ($rv_len - mb_strlen($suff) < 0) {
                continue;
            }
            $pos = mb_strpos($rv, $suff, $rv_len - mb_strlen($suff));
            if ($pos !== false) {
                break;
            }
        }

        $ante_suff = mb_substr($rv, 0, $pos);
        $ante_suff_len = mb_strlen($ante_suff);

        foreach (self::$ante_suff_a as $ante_a) {
            if ($ante_suff_len - mb_strlen($ante_a) < 0) {
                continue;
            }
            $pos_a = mb_strpos($ante_suff, $ante_a, $ante_suff_len - mb_strlen($ante_a));
            if ($pos_a !== false) {
                return mb_substr($str, 0, $pos + $str_len - $rv_len);
            }
        }

        foreach (self::$ante_suff_b as $ante_b) {
            if ($ante_suff_len - mb_strlen($ante_b) < 0) {
                continue;
            }
            $pos_b = mb_strpos($ante_suff, $ante_b, $ante_suff_len - mb_strlen($ante_b));
            if ($pos_b !== false) {
                return mb_substr($str, 0, $pos + $str_len - $rv_len).'e';
            }
        }

        return $str;
    }

    private static function deleteStuff($arr_suff, $str, $str_len, $where, $ovunque = false)
    {
        if ($where === 'r2') {
            $r = self::returnR2($str);
        } elseif ($where === 'rv') {
            $r = self::returnRV($str);
        } elseif ($where === 'r1') {
            $r = self::returnR1($str);
        }

        $r_len = mb_strlen($r);

        if ($ovunque) {
            foreach ($arr_suff as $suff) {
                if ($str_len - mb_strlen($suff) < 0) {
                    continue;
                }
                $pos = mb_strpos($str, $suff, $str_len - mb_strlen($suff));
                if ($pos !== false) {
                    $pattern = '/'.$suff.'$/';
                    $ret_str = preg_match($pattern, $r) ? mb_substr($str, 0, $pos) : '';
                    if ($ret_str !== '') {
                        return $ret_str;
                    }
                    break;
                }
            }
        } else {
            foreach ($arr_suff as $suff) {
                if ($r_len - mb_strlen($suff) < 0) {
                    continue;
                }
                $pos = mb_strpos($r, $suff, $r_len - mb_strlen($suff));
                if ($pos !== false) {
                    return mb_substr($str, 0, $pos + $str_len - $r_len);
                }
            }
        }
    }

    private static function step1($str)
    {
        // Step 1: Standard suffix removal
        // Always do steps 1

        $str_len = mb_strlen($str);

        // Delete if in R1, if preceded by 'iv', delete if in R2 (and if further preceded by 'at', delete if in R2),
        // otherwise, if preceded by 'os', 'ic' or 'abil', delete if in R2
        if (!empty($ret_str = self::deleteStuff(self::$suffissi_step1_g, $str, $str_len, 'r1'))) {
            if (!empty($ret_str1 = self::deleteStuff(['iv'], $ret_str, mb_strlen($ret_str), 'r2'))) {
                if (!empty($ret_str2 = self::deleteStuff(['at'], $ret_str1, mb_strlen($ret_str1), 'r2'))) {
                    return $ret_str2;
                } else {
                    return $ret_str1;
                }
            } elseif (!empty(
                $ret_str1 = self::deleteStuff(['os', 'ic', 'abil'], $ret_str, mb_strlen($ret_str), 'r2')
            )) {
                return $ret_str1;
            } else {
                return $ret_str;
            }
        }

        // Delete if in R2
        if (count($ret_str = self::deleteStuff(self::$suffissi_step1_a, $str, $str_len, 'r2', true))) {
            return $ret_str;
        }

        // Delete if in R2, if preceded by 'ic', delete if in R2
        if (count($ret_str = self::deleteStuff(self::$suffissi_step1_b, $str, $str_len, 'r2'))) {
            if (count($ret_str1 = self::deleteStuff(['ic'], $ret_str, mb_strlen($ret_str), 'r2'))) {
                return $ret_str1;
            } else {
                return $ret_str;
            }
        }

        // Replace with 'log' if in R2
        if (count($ret_str = self::deleteStuff(self::$suffissi_step1_c, $str, $str_len, 'r2'))) {
            return $ret_str.'log';
        }

        // Replace with 'u' if in R2
        if (count($ret_str = self::deleteStuff(self::$suffissi_step1_d, $str, $str_len, 'r2'))) {
            return $ret_str.'u';
        }

        // Replace with 'ente' if in R2
        if (count($ret_str = self::deleteStuff(self::$suffissi_step1_e, $str, $str_len, 'r2'))) {
            return $ret_str.'ente';
        }

        // Delete if in RV
        if (count($ret_str = self::deleteStuff(self::$suffissi_step1_f, $str, $str_len, 'rv'))) {
            return $ret_str;
        }

        // Delete if in R2, if preceded by 'abil', 'ic' or 'iv', delete if in R2
        if (count($ret_str = self::deleteStuff(self::$suffissi_step1_h, $str, $str_len, 'r2'))) {
            if (count($ret_str1 = self::deleteStuff(['abil', 'ic', 'iv'], $ret_str, mb_strlen($ret_str), 'r2'))) {
                return $ret_str1;
            } else {
                return $ret_str;
            }
        }

        // Delete if in R2, if preceded by 'at', delete if in R2 (and if further preceded by 'ic', delete if in R2)
        if (count($ret_str = self::deleteStuff(self::$suffissi_step1_i, $str, $str_len, 'r2'))) {
            if (count($ret_str1 = self::deleteStuff(['at'], $ret_str, mb_strlen($ret_str), 'r2'))) {
                if (count($ret_str2 = self::deleteStuff(['ic'], $ret_str1, mb_strlen($ret_str1), 'r2'))) {
                    return $ret_str2;
                } else {
                    return $ret_str1;
                }
            } else {
                return $ret_str;
            }
        }

        return $str;
    }

    private static function step2($str, $str_step1)
    {
        //Step 2: Verb suffixes
        //Do step 2 if no ending was removed by step 1

        if ($str != $str_step1) {
            return $str_step1;
        }

        $str_len = mb_strlen($str);

        if (count($ret_str = self::deleteStuff(self::$suffissi_step2, $str, $str_len, 'rv'))) {
            return $ret_str;
        }

        return $str;
    }

    private static function step3a($str)
    {
        // Step 3a: Delete a final 'a', 'e', 'i', 'o',' à', 'è', 'ì' or 'ò' if it is in RV,
        // and a preceding 'i' if it is in RV ('crocchi' -> 'crocch', 'crocchio' -> 'crocch')
        // Always do steps 3a

        $vocale_finale = ['a', 'e', 'i', 'o', 'à', 'è', 'ì', 'ò'];

        $str_len = mb_strlen($str);

        if (count($ret_str = self::deleteStuff($vocale_finale, $str, $str_len, 'rv'))) {
            if (count($ret_str1 = self::deleteStuff(['i'], $ret_str, mb_strlen($ret_str), 'rv'))) {
                return $ret_str1;
            } else {
                return $ret_str;
            }
        }

        return $str;
    }

    private static function step3b($str)
    {
        // Step 3b: Replace final 'ch' (or 'gh') with 'c' (or 'g') if in 'RV' ('crocch' -> 'crocc')
        // Always do steps 3b

        $rv = self::returnRV($str);

        $pattern = '/([cg])h$/';

        return mb_substr($str, 0, mb_strlen($str) - mb_strlen($rv))
            . preg_replace_callback(
                $pattern,
                function ($matches) {
                    return $matches[0];
                },
                $rv
            );
    }

    private static function step4($str)
    {
        // Step 4: Finally, turn I and U back into lower case

        return strtolower($str);
    }
}
