<?php

namespace TeamTNT\TNTSearch\Stemmer;

/**
 * Light stemmer for Latvian.
 *
 * Original Java code can be found in https://github.com/apache/lucene-solr
 * Ported to Python by Rihards Krišlauks with minor modifications
 *
 * Ported to PHP from https://github.com/rihardsk/LatvianStemmer
 *
 * Light stemmer for Latvian.
 * <p>
 * This is a light version of the algorithm in Karlis Kreslin's PhD thesis
 * <i>A stemming algorithm for Latvian</i> with the following modifications:
 * <ul>
 *   <li>Only explicitly stems noun and adjective morphology
 *   <li>Stricter length/vowel checks for the resulting stems (verb etc suffix stripping is removed)
 *   <li>Removes only the primary inflectional suffixes: case and number for nouns
 *       case, number, gender, and definitiveness for adjectives.
 *   <li>Palatalization is only handled when a declension II,V,VI noun suffix is removed.
 * </ul>
 */
class LatvianStemmer implements Stemmer
{
    private static $affixes = [
        ['ajiem', 3, false],
        ['ajai', 3, false],
        ['ajam', 2, false],
        ['ajām', 2, false],
        ['ajos', 2, false],
        ['ajās', 2, false],
        ['iem', 2, true],
        ['ajā', 2, false],
        ['ais', 2, false],
        ['ai', 2, false],
        ['ei', 2, false],
        ['ām', 1, false],
        ['am', 1, false],
        ['ēm', 1, false],
        ['īm', 1, false],
        ['im', 1, false],
        ['um', 1, false],
        ['us', 1, true],
        ['as', 1, false],
        ['ās', 1, false],
        ['es', 1, false],
        ['os', 1, true],
        ['ij', 1, false],
        ['īs', 1, false],
        ['ēs', 1, false],
        ['is', 1, false],
        ['ie', 1, false],
        ['u', 1, true],
        ['a', 1, true],
        ['i', 1, true],
        ['e', 1, false],
        ['ā', 1, false],
        ['ē', 1, false],
        ['ī', 1, false],
        ['ū', 1, false],
        ['o', 1, false],
        ['s', 0, false],
        ['š', 0, false],
    ];
    private static $VOWELS = 'aāeēiīouū';

    /**
     * @param $word string
     *
     * @return string
     */
    public static function stem($word)
    {
        $word = mb_strtolower($word);
        $s = mb_str_split($word);
        $numVowels = self::numVowels($s);
        $length = count($s);

        foreach (self::$affixes as $affix) {
            if ($numVowels > $affix[1] and $length >= mb_strlen($affix[0]) + 3 and self::endswith($s, $length,
                    $affix[0])) {
                $length -= mb_strlen($affix[0]);
                if ($affix[2]) {
                    $s = self::unPalatalize($s, $length);
                } else {
                    $s = array_slice($s, 0, $length);
                }
                break;
            }
        }
        return implode('', $s);
    }

    /**
     * @param $s array<string>
     *
     * @return int
     */
    private static function numVowels($s)
    {
        $count = 0;
        foreach ($s as $char) {
            if (mb_substr_count(self::$VOWELS, $char) > 0) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * @param $s array<string>
     * @param $length integer
     * @param $suffix string
     *
     * @return bool
     */
    public static function endswith($s, $length, $suffix)
    {
        return str_ends_with(implode('', array_splice($s, 0, $length)), $suffix);
    }

    /**
     * @param $s array<string>
     * @param $length integer
     *
     * @return array
     */
    public static function unPalatalize($s, $length)
    {
        // we check the character removed: if its -u then
        // its 2,5, or 6 gen pl., and these two can only apply then.
        if ($s[$length] === 'u') {
            // kš -> kst
            if (self::endswith($s, $length, "kš")) {
                $length += 1;
                $s[$length - 2] = 's';
                $s[$length - 1] = 't';
                return array_splice($s, 0, $length);
            } elseif (self::endswith($s, $length, "ņņ")) {
                $s[$length - 2] = 'n';
                $s[$length - 1] = 'n';
                return array_splice($s, 0, $length);
            }
        }
        // otherwise all other rules
        if (self::endswith($s, $length, 'pj') or self::endswith($s, $length, 'bj') or self::endswith($s, $length,
                'mj') or self::endswith($s, $length, 'vj')) {
            $length--;
        } elseif (self::endswith($s, $length, 'šņ')) {
            $s[$length - 2] = 's';
            $s[$length - 1] = 'n';
        } elseif (self::endswith($s, $length, 'žņ')) {
            $s[$length - 2] = 'z';
            $s[$length - 1] = 'n';
        } elseif (self::endswith($s, $length, 'šļ')) {
            $s[$length - 2] = 's';
            $s[$length - 1] = 'l';
        } elseif (self::endswith($s, $length, 'žļ')) {
            $s[$length - 2] = 'z';
            $s[$length - 1] = 'l';
        } elseif (self::endswith($s, $length, 'ļņ')) {
            $s[$length - 2] = 'l';
            $s[$length - 1] = 'n';
        } elseif (self::endswith($s, $length, 'ļļ')) {
            $s[$length - 2] = 'l';
            $s[$length - 1] = 'l';
        } elseif (self::endswith($s, $length, 'č')) {
            $s[$length - 1] = 'c';
        } elseif (self::endswith($s, $length, 'ļ')) {
            $s[$length - 1] = 'l';
        } elseif (self::endswith($s, $length, 'ņ')) {
            $s[$length - 1] = 'n';
        }
        return array_splice($s, 0, $length);
    }
}
