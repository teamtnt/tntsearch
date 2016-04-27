<?php

namespace TeamTNT\TNTSearch\Stemmer;

/**

 * Copyright (c) 2013 Aris Buzachis (buzachis.aris@gmail.com)
 *
 * All rights reserved.
 *
 * This script is free software.
 *
 * DISCLAIMER:
 *
 * IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

/**
 * Takes a word and reduces it to its German stem using the Porter stemmer algorithm.
 *
 * References:
 *  - http://snowball.tartarus.org/algorithms/porter/stemmer.html
 *  - http://snowball.tartarus.org/algorithms/german/stemmer.html
 *
 * Usage:
 *  $stem = GermanStemmer::stem($word);
 *
 * @author Aris Buzachis <buzachis.aris@gmail.com>
 * @author Pascal Landau <kontakt@myseosolution.de>
 */

class GermanStemmer implements Stemmer
{
    /**
     *  R1 and R2 regions (see the Porter algorithm)
     */
    private static $R1;
    private static $R2;

    private static $cache = array();

    private static $vowels    = array('a', 'e', 'i', 'o', 'u', 'y', 'ä', 'ö', 'ü');
    private static $s_ending  = array('b', 'd', 'f', 'g', 'h', 'k', 'l', 'm', 'n', 'r', 't');
    private static $st_ending = array('b', 'd', 'f', 'g', 'h', 'k', 'l', 'm', 'n', 't');

    /**
     * Gets the stem of $word.
     * @param string $word
     * @return string
     */
    public static function stem($word)
    {
        $word = mb_strtolower($word);
        //check for invalid characters
        preg_match("#.#u", $word);
        if (preg_last_error() !== 0) {
            throw new \InvalidArgumentException("Word '$word' seems to be errornous. Error code from preg_last_error(): " . preg_last_error());
        }
        if (!isset(self::$cache[$word])) {
            $result             = self::getStem($word);
            self::$cache[$word] = $result;
        }

        return self::$cache[$word];
    }

    /**
     * @param $word
     * @return string
     */
    private static function getStem($word)
    {
        $word = self::step0a($word);
        $word = self::step1($word);
        $word = self::step2($word);
        $word = self::step3($word);
        $word = self::step0b($word);

        return $word;
    }

    /**
     * Replaces to protect some characters
     * @param string $word
     * @return string mixed
     */
    private static function step0a($word)
    {
        $vstr = implode('', self::$vowels);
        $word = preg_replace('#([' . $vstr . '])u([' . $vstr . '])#u', '$1U$2', $word);
        $word = preg_replace('#([' . $vstr . '])y([' . $vstr . '])#u', '$1Y$2', $word);

        return $word;
    }

    /**
     * Undo the initial replaces
     * @param string $word
     * @return string
     */
    private static function step0b($word)
    {
        $word = str_replace(array('ä', 'ö', 'ü', 'U', 'Y'), array('a', 'o', 'u', 'u', 'y'), $word);

        return $word;
    }

    private static function step1($word)
    {
        $word = str_replace('ß', 'ss', $word);

        self::getR($word);

        $replaceCount = 0;

        $arr = array('em', 'ern', 'er');
        foreach ($arr as $s) {
            self::$R1 = preg_replace('#' . $s . '$#u', '', self::$R1, -1, $replaceCount);
            if ($replaceCount > 0) {
                $word = preg_replace('#' . $s . '$#u', '', $word);
            }
        }

        $arr = array('en', 'es', 'e');
        foreach ($arr as $s) {
            self::$R1 = preg_replace('#' . $s . '$#u', '', self::$R1, -1, $replaceCount);
            if ($replaceCount > 0) {
                $word = preg_replace('#' . $s . '$#u', '', $word);
                $word = preg_replace('#niss$#u', 'nis', $word);
            }
        }

        $word = preg_replace('/([' . implode('', self::$s_ending) . '])s$/u', '$1', $word);

        return $word;
    }

    private static function step2($word)
    {
        self::getR($word);

        $replaceCount = 0;

        $arr = array('est', 'er', 'en');
        foreach ($arr as $s) {
            self::$R1 = preg_replace('#' . $s . '$#u', '', self::$R1, -1, $replaceCount);
            if ($replaceCount > 0) {
                $word = preg_replace('#' . $s . '$#u', '', $word);
            }
        }

        if (strpos(self::$R1, 'st') !== false) {
            self::$R1 = preg_replace('#st$#u', '', self::$R1);
            $word     = preg_replace('#(...[' . implode('', self::$st_ending) . '])st$#u', '$1', $word);
        }

        return $word;
    }

    private static function step3($word)
    {
        self::getR($word);

        $replaceCount = 0;

        $arr = array('end', 'ung');
        foreach ($arr as $s) {
            if (preg_match('#' . $s . '$#u', self::$R2)) {
                $word = preg_replace('#([^e])' . $s . '$#u', '$1', $word, -1, $replaceCount);
                if ($replaceCount > 0) {
                    self::$R2 = preg_replace('#' . $s . '$#u', '', self::$R2, -1, $replaceCount);
                }
            }
        }

        $arr = array('isch', 'ik', 'ig');
        foreach ($arr as $s) {
            if (preg_match('#' . $s . '$#u', self::$R2)) {
                $word = preg_replace('#([^e])' . $s . '$#u', '$1', $word, -1, $replaceCount);
                if ($replaceCount > 0) {
                    self::$R2 = preg_replace('#' . $s . '$#u', '', self::$R2);
                }
            }
        }

        $arr = array('lich', 'heit');
        foreach ($arr as $s) {
            self::$R2 = preg_replace('#' . $s . '$#u', '', self::$R2, -1, $replaceCount);
            if ($replaceCount > 0) {
                $word = preg_replace('#' . $s . '$#u', '', $word);
            } else {
                if (preg_match('#' . $s . '$#u', self::$R1)) {
                    $word = preg_replace('#(er|en)' . $s . '$#u', '$1', $word, -1, $replaceCount);
                    if ($replaceCount > 0) {
                        self::$R1 = preg_replace('#' . $s . '$#u', '', self::$R1);
                    }
                }
            }
        }

        $arr = array('keit');
        foreach ($arr as $s) {
            self::$R2 = preg_replace('#' . $s . '$#u', '', self::$R2, -1, $replaceCount);
            if ($replaceCount > 0) {
                $word = preg_replace('#' . $s . '$#u', '', $word);
            }
        }

        return $word;
    }

    /**
     * Find R1 and R2
     * @param string $word
     */
    private static function getR($word)
    {
        self::$R1 = "";
        self::$R2 = "";

        $vowels        = implode("", self::$vowels);
        $vowelGroup    = "[{$vowels}]";
        $nonVowelGroup = "[^{$vowels}]";
        // R1 is the region after the first non-vowel following a vowel, or is the null region at the end of the word if there is no such non-vowel.
        $pattern = "#(?P<rest>.*?{$vowelGroup}{$nonVowelGroup})(?P<r>.*)#u";
        if (preg_match($pattern, $word, $match)) {
            $rest = $match["rest"];
            $r1   = $match["r"];
            // [...], but then R1 is adjusted so that the region before it contains at least 3 letters.
            $cutOff = 3 - mb_strlen($rest);
            if ($cutOff > 0) {
                $r1 = mb_substr($r1, $cutOff);
            }
            self::$R1 = $r1;
        }

        //R2 is the region after the first non-vowel following a vowel in R1, or is the null region at the end of the word if there is no such non-vowel.
        if (preg_match($pattern, self::$R1, $match)) {
            self::$R2 = $match["r"];
        }
    }
}
