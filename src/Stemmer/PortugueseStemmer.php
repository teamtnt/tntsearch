<?php

namespace TeamTNT\TNTSearch\Stemmer;

/**
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

/**
 * This is a reimplementation of the Porter Stemmer Algorithm for Portuguese.
 * This script is based on the implementation found on <https://github.com/wamania/php-stemmer>
 * and has been rewriten to work with TNTSearch by Lucas Padilha <https://github.com/LucasPadilha>
 *
 * Takes a word and reduces it to its Portuguese stem using the Porter stemmer algorithm.
 *
 * References:
 *  - http://snowball.tartarus.org/algorithms/porter/stemmer.html
 *  - http://snowball.tartarus.org/algorithms/portuguese/stemmer.html
 *
 * Usage:
 *  $stem = PortugueseStemmer::stem($word);
 *
 * @author Lucas Padilha <https://github.com/LucasPadilha>
 */

class PortugueseStemmer implements Stemmer
{
    /**
     * UTF-8 Case lookup table
     *
     * This lookuptable defines the upper case letters to their correspponding
     * lower case letter in UTF-8
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    private static $utf8_lower_to_upper = [
        0x0061 => 0x0041, 0x03C6 => 0x03A6, 0x0163 => 0x0162, 0x00E5 => 0x00C5, 0x0062 => 0x0042,
        0x013A => 0x0139, 0x00E1 => 0x00C1, 0x0142 => 0x0141, 0x03CD => 0x038E, 0x0101 => 0x0100,
        0x0491 => 0x0490, 0x03B4 => 0x0394, 0x015B => 0x015A, 0x0064 => 0x0044, 0x03B3 => 0x0393,
        0x00F4 => 0x00D4, 0x044A => 0x042A, 0x0439 => 0x0419, 0x0113 => 0x0112, 0x043C => 0x041C,
        0x015F => 0x015E, 0x0144 => 0x0143, 0x00EE => 0x00CE, 0x045E => 0x040E, 0x044F => 0x042F,
        0x03BA => 0x039A, 0x0155 => 0x0154, 0x0069 => 0x0049, 0x0073 => 0x0053, 0x1E1F => 0x1E1E,
        0x0135 => 0x0134, 0x0447 => 0x0427, 0x03C0 => 0x03A0, 0x0438 => 0x0418, 0x00F3 => 0x00D3,
        0x0440 => 0x0420, 0x0454 => 0x0404, 0x0435 => 0x0415, 0x0449 => 0x0429, 0x014B => 0x014A,
        0x0431 => 0x0411, 0x0459 => 0x0409, 0x1E03 => 0x1E02, 0x00F6 => 0x00D6, 0x00F9 => 0x00D9,
        0x006E => 0x004E, 0x0451 => 0x0401, 0x03C4 => 0x03A4, 0x0443 => 0x0423, 0x015D => 0x015C,
        0x0453 => 0x0403, 0x03C8 => 0x03A8, 0x0159 => 0x0158, 0x0067 => 0x0047, 0x00E4 => 0x00C4,
        0x03AC => 0x0386, 0x03AE => 0x0389, 0x0167 => 0x0166, 0x03BE => 0x039E, 0x0165 => 0x0164,
        0x0117 => 0x0116, 0x0109 => 0x0108, 0x0076 => 0x0056, 0x00FE => 0x00DE, 0x0157 => 0x0156,
        0x00FA => 0x00DA, 0x1E61 => 0x1E60, 0x1E83 => 0x1E82, 0x00E2 => 0x00C2, 0x0119 => 0x0118,
        0x0146 => 0x0145, 0x0070 => 0x0050, 0x0151 => 0x0150, 0x044E => 0x042E, 0x0129 => 0x0128,
        0x03C7 => 0x03A7, 0x013E => 0x013D, 0x0442 => 0x0422, 0x007A => 0x005A, 0x0448 => 0x0428,
        0x03C1 => 0x03A1, 0x1E81 => 0x1E80, 0x016D => 0x016C, 0x00F5 => 0x00D5, 0x0075 => 0x0055,
        0x0177 => 0x0176, 0x00FC => 0x00DC, 0x1E57 => 0x1E56, 0x03C3 => 0x03A3, 0x043A => 0x041A,
        0x006D => 0x004D, 0x016B => 0x016A, 0x0171 => 0x0170, 0x0444 => 0x0424, 0x00EC => 0x00CC,
        0x0169 => 0x0168, 0x03BF => 0x039F, 0x006B => 0x004B, 0x00F2 => 0x00D2, 0x00E0 => 0x00C0,
        0x0434 => 0x0414, 0x03C9 => 0x03A9, 0x1E6B => 0x1E6A, 0x00E3 => 0x00C3, 0x044D => 0x042D,
        0x0436 => 0x0416, 0x01A1 => 0x01A0, 0x010D => 0x010C, 0x011D => 0x011C, 0x00F0 => 0x00D0,
        0x013C => 0x013B, 0x045F => 0x040F, 0x045A => 0x040A, 0x00E8 => 0x00C8, 0x03C5 => 0x03A5,
        0x0066 => 0x0046, 0x00FD => 0x00DD, 0x0063 => 0x0043, 0x021B => 0x021A, 0x00EA => 0x00CA,
        0x03B9 => 0x0399, 0x017A => 0x0179, 0x00EF => 0x00CF, 0x01B0 => 0x01AF, 0x0065 => 0x0045,
        0x03BB => 0x039B, 0x03B8 => 0x0398, 0x03BC => 0x039C, 0x045C => 0x040C, 0x043F => 0x041F,
        0x044C => 0x042C, 0x00FE => 0x00DE, 0x00F0 => 0x00D0, 0x1EF3 => 0x1EF2, 0x0068 => 0x0048,
        0x00EB => 0x00CB, 0x0111 => 0x0110, 0x0433 => 0x0413, 0x012F => 0x012E, 0x00E6 => 0x00C6,
        0x0078 => 0x0058, 0x0161 => 0x0160, 0x016F => 0x016E, 0x03B1 => 0x0391, 0x0457 => 0x0407,
        0x0173 => 0x0172, 0x00FF => 0x0178, 0x006F => 0x004F, 0x043B => 0x041B, 0x03B5 => 0x0395,
        0x0445 => 0x0425, 0x0121 => 0x0120, 0x017E => 0x017D, 0x017C => 0x017B, 0x03B6 => 0x0396,
        0x03B2 => 0x0392, 0x03AD => 0x0388, 0x1E85 => 0x1E84, 0x0175 => 0x0174, 0x0071 => 0x0051,
        0x0437 => 0x0417, 0x1E0B => 0x1E0A, 0x0148 => 0x0147, 0x0105 => 0x0104, 0x0458 => 0x0408,
        0x014D => 0x014C, 0x00ED => 0x00CD, 0x0079 => 0x0059, 0x010B => 0x010A, 0x03CE => 0x038F,
        0x0072 => 0x0052, 0x0430 => 0x0410, 0x0455 => 0x0405, 0x0452 => 0x0402, 0x0127 => 0x0126,
        0x0137 => 0x0136, 0x012B => 0x012A, 0x03AF => 0x038A, 0x044B => 0x042B, 0x006C => 0x004C,
        0x03B7 => 0x0397, 0x0125 => 0x0124, 0x0219 => 0x0218, 0x00FB => 0x00DB, 0x011F => 0x011E,
        0x043E => 0x041E, 0x1E41 => 0x1E40, 0x03BD => 0x039D, 0x0107 => 0x0106, 0x03CB => 0x03AB,
        0x0446 => 0x0426, 0x00FE => 0x00DE, 0x00E7 => 0x00C7, 0x03CA => 0x03AA, 0x0441 => 0x0421,
        0x0432 => 0x0412, 0x010F => 0x010E, 0x00F8 => 0x00D8, 0x0077 => 0x0057, 0x011B => 0x011A,
        0x0074 => 0x0054, 0x006A => 0x004A, 0x045B => 0x040B, 0x0456 => 0x0406, 0x0103 => 0x0102,
        0x03BB => 0x039B, 0x00F1 => 0x00D1, 0x043D => 0x041D, 0x03CC => 0x038C, 0x00E9 => 0x00C9,
        0x00F0 => 0x00D0, 0x0457 => 0x0407, 0x0123 => 0x0122
    ];

    private static $vowels = ['a', 'e', 'i', 'o', 'u', 'á', 'é', 'í', 'ó', 'ú', 'â', 'ê', 'ô'];

    public static function stem($word)
    {
        // we do ALL in UTF-8
        if (!self::check($word)) {
            throw new \Exception('Word must be in UTF-8');
        }

        $word = self::strtolower($word);
        $word = self::str_replace(['ã', 'õ'], ['a~', 'o~'], $word);

        $rv      = '';
        $rvIndex = '';
        self::rv($word, $rv, $rvIndex);

        $r1      = '';
        $r1Index = '';
        self::r1($word, $r1, $r1Index);

        $r2      = '';
        $r2Index = '';
        self::r2($r1, $r1Index, $r2, $r2Index);

        $initialWord = $word;

        self::step1($word, $r1Index, $r2Index, $rvIndex);

        if ($initialWord == $word) {
            self::step2($word, $rvIndex);
        }

        if ($initialWord != $word) {
            self::step3($word, $rvIndex);
        } else {
            self::step4($word, $rvIndex);
        }

        self::step5($word, $rvIndex);

        self::finish($word);

        return $word;
    }

    /**
     * R1 is the region after the first non-vowel following a vowel, or the end of the word if there is no such non-vowel.
     */
    private static function r1($word, &$r1, &$r1Index)
    {
        list($index, $value) = self::rx($word);

        $r1      = $value;
        $r1Index = $index;

        return true;
    }

    /**
     * R2 is the region after the first non-vowel following a vowel in R1, or the end of the word if there is no such non-vowel.
     */
    private static function r2($r1, $r1Index, &$r2, &$r2Index)
    {
        list($index, $value) = self::rx($r1);

        $r2      = $value;
        $r2Index = $r1Index + $index;

        return true;
    }

    /**
     * Common function for R1 and R2
     * Search the region after the first non-vowel following a vowel in $word, or the end of the word if there is no such non-vowel.
     * R1 : $in = $this->word
     * R2 : $in = R1
     */
    private static function rx($in)
    {
        $length = self::strlen($in);

        // Defaults
        $value = '';
        $index = $length;

        // Search all vowels
        $vowels = [];
        for ($i = 0; $i < $length; $i++) {
            $letter = self::substr($in, $i, 1);

            if (in_array($letter, static::$vowels)) {
                $vowels[] = $i;
            }
        }

        // Search the non-vowel following a vowel
        foreach ($vowels as $position) {
            $after  = $position + 1;
            $letter = self::substr($in, $after, 1);

            if (!in_array($letter, static::$vowels)) {
                $index = $after + 1;
                $value = self::substr($in, ($after + 1));
                break;
            }
        }

        return [$index, $value];
    }

    /**
     * Used by spanish, italian, portuguese, etc (but not by french)
     *
     * If the second letter is a consonant, RV is the region after the next following vowel,
     * or if the first two letters are vowels, RV is the region after the next consonant,
     * and otherwise (consonant-vowel case) RV is the region after the third letter.
     * But RV is the end of the word if these positions cannot be found.
     */
    private static function rv($word, &$rv, &$rvIndex)
    {
        $length = self::strlen($word);

        if ($length < 3) {
            return true;
        }

        $first  = self::substr($word, 0, 1);
        $second = self::substr($word, 1, 1);

        // If the second letter is a consonant, RV is the region after the next following vowel,
        if (!in_array($second, static::$vowels)) {
            for ($i = 2; $i < $length; $i++) {
                $letter = self::substr($word, $i, 1);

                if (in_array($letter, static::$vowels)) {
                    $rv      = self::substr($word, ($i + 1));
                    $rvIndex = $i + 1;

                    return true;
                }
            }
        }

        // or if the first two letters are vowels, RV is the region after the next consonant,
        if ((in_array($first, static::$vowels)) && (in_array($second, static::$vowels))) {
            for ($i = 2; $i < $length; $i++) {
                $letter = self::substr($word, $i, 1);

                if (!in_array($letter, static::$vowels)) {
                    $rv      = self::substr($word, ($i + 1));
                    $rvIndex = $i + 1;

                    return true;
                }
            }
        }

        // and otherwise (consonant-vowel case) RV is the region after the third letter.
        if ((!in_array($first, static::$vowels)) && (in_array($second, static::$vowels))) {
            $rv      = self::substr($word, 3);
            $rvIndex = 3;

            return true;
        }

        return false;
    }

    private static function inRv($position, $rvIndex)
    {
        return ($position >= $rvIndex);
    }

    private static function inR1($position, $r1Index)
    {
        return ($position >= $r1Index);
    }

    private static function inR2($position, $r2Index)
    {
        return ($position >= $r2Index);
    }

    private static function searchIfInRv($word, $suffixes, $rvIndex)
    {
        return self::search($word, $suffixes, $rvIndex);
    }

    private static function searchIfInR2($word, $suffixes, $r2Index)
    {
        return self::search($word, $suffixes, $r2Index);
    }

    private static function search($word, $suffixes, $offset = 0)
    {
        $length = self::strlen($word);

        if ($offset > $length) {
            return false;
        }

        foreach ($suffixes as $suffix) {
            if ((($position = self::strrpos($word, $suffix, $offset)) !== false) && ((self::strlen($suffix) + $position) == $length)) {
                return $position;
            }
        }
        return false;
    }

    /**
     * Step 1: Standard suffix removal
     */
    private static function step1(&$word, $r1Index, $r2Index, $rvIndex)
    {
        // delete if in R2
        if (($position = self::search($word, ['amentos', 'imentos', 'adoras', 'adores', 'amento', 'imento', 'adora', 'istas', 'ismos', 'antes', 'ância', 'ezas', 'eza', 'icos', 'icas', 'ismo', 'ável', 'ível', 'ista', 'oso', 'osos', 'osas', 'osa', 'ico', 'ica', 'ador', 'aça~o', 'aço~es', 'ante'])) !== false) {
            if (self::inR2($position, $r2Index)) {
                $word = self::substr($word, 0, $position);
            }

            return true;
        }

        // replace with log if in R2
        if (($position = self::search($word, ['logías', 'logía'])) !== false) {
            if (self::inR2($position, $r2Index)) {
                $word = preg_replace('#(logías|logía)$#u', 'log', $word);
            }

            return true;
        }

        // replace with u if in R2
        if (($position = self::search($word, ['uciones', 'ución'])) !== false) {
            if (self::inR2($position, $r2Index)) {
                $word = preg_replace('#(uciones|ución)$#u', 'u', $word);
            }

            return true;
        }

        // replace with ente if in R2
        if (($position = self::search($word, ['ências', 'ência'])) !== false) {
            if (self::inR2($position, $r2Index)) {
                $word = preg_replace('#(ências|ência)$#u', 'ente', $word);
            }

            return true;
        }

        // delete if in R1
        // if preceded by iv, delete if in R2 (and if further preceded by at, delete if in R2), otherwise,
        // if preceded by os, ic or ad, delete if in R2
        if (($position = self::search($word, ['amente'])) !== false) {
            // delete if in R1
            if (self::inR1($position, $r1Index)) {
                $word = self::substr($word, 0, $position);
            }

            // if preceded by iv, delete if in R2 (and if further preceded by at, delete if in R2), otherwise,
            if (($position2 = self::searchIfInR2($word, ['iv'], $r2Index)) !== false) {
                $word = self::substr($word, 0, $position2);

                if (($position3 = self::searchIfInR2($word, ['at'], $r2Index)) !== false) {
                    $word = self::substr($word, 0, $position3);
                }

                // if preceded by os, ic or ad, delete if in R2
            } elseif (($position4 = self::searchIfInR2($word, ['os', 'ic', 'ad'], $r2Index)) !== false) {
                $word = self::substr($word, 0, $position4);
            }

            return true;
        }

        // delete if in R2
        // if preceded by ante, avel or ível, delete if in R2
        if (($position = self::search($word, ['mente'])) !== false) {
            // delete if in R2
            if (self::inR2($position, $r2Index)) {
                $word = self::substr($word, 0, $position);
            }

            // if preceded by ante, avel or ível, delete if in R2
            if (($position2 = self::searchIfInR2($word, ['ante', 'avel', 'ível'], $r2Index)) != false) {
                $word = self::substr($word, 0, $position2);
            }

            return true;
        }

        // delete if in R2
        // if preceded by abil, ic or iv, delete if in R2
        if (($position = self::search($word, ['idades', 'idade'])) !== false) {
            // delete if in R2
            if (self::inR2($position, $r2Index)) {
                $word = self::substr($word, 0, $position);
            }

            // if preceded by abil, ic or iv, delete if in R2
            if (($position2 = self::searchIfInR2($word, ['abil', 'ic', 'iv'], $r2Index)) !== false) {
                $word = self::substr($word, 0, $position2);
            }

            return true;
        }

        // delete if in R2
        // if preceded by at, delete if in R2
        if (($position = self::search($word, ['ivas', 'ivos', 'iva', 'ivo'])) !== false) {
            // delete if in R2
            if (self::inR2($position, $r2Index)) {
                $word = self::substr($word, 0, $position);
            }

            // if preceded by at, delete if in R2
            if (($position2 = self::searchIfInR2($word, ['at'], $r2Index)) !== false) {
                $word = self::substr($word, 0, $position2);
            }

            return true;
        }

        // replace with ir if in RV and preceded by e
        if (($position = self::search($word, ['iras', 'ira'])) !== false) {
            if (self::inRv($position, $rvIndex)) {
                $before = $position - 1;
                $letter = self::substr($word, $before, 1);

                if ($letter == 'e') {
                    $word = preg_replace('#(iras|ira)$#u', 'ir', $word);
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Step 2: Verb suffixes
     * Search for the longest among the following suffixes in RV, and if found, delete.
     */
    private static function step2(&$word, $rvIndex)
    {
        if (($position = self::searchIfInRv($word, ['aríamos', 'eríamos', 'iríamos', 'ássemos', 'êssemos', 'íssemos', 'aríeis', 'eríeis', 'iríeis', 'ásseis', 'ésseis', 'ísseis', 'áramos', 'éramos', 'íramos', 'ávamos', 'aremos', 'eremos', 'iremos', 'ariam', 'eriam', 'iriam', 'assem', 'essem', 'issem', 'arias', 'erias', 'irias', 'ardes', 'erdes', 'irdes', 'asses', 'esses', 'isses', 'astes', 'estes', 'istes', 'áreis', 'areis', 'éreis', 'ereis', 'íreis', 'ireis', 'áveis', 'íamos', 'armos', 'ermos', 'irmos', 'aria', 'eria', 'iria', 'asse', 'esse', 'isse', 'aste', 'este', 'iste', 'arei', 'erei', 'irei', 'adas', 'idas', 'aram', 'eram', 'iram', 'avam', 'arem', 'erem', 'irem', 'ando', 'endo', 'indo', 'ara~o', 'era~o', 'ira~o', 'arás', 'aras', 'erás', 'eras', 'irás', 'avas', 'ares', 'eres', 'ires', 'íeis', 'ados', 'idos', 'ámos', 'amos', 'emos', 'imos', 'iras', 'ada', 'ida', 'ará', 'ara', 'erá', 'era', 'irá', 'ava', 'iam', 'ado', 'ido', 'ias', 'ais', 'eis', 'ira', 'ia', 'ei', 'am', 'em', 'ar', 'er', 'ir', 'as', 'es', 'is', 'eu', 'iu', 'ou'], $rvIndex)) !== false) {
            $word = self::substr($word, 0, $position);

            return true;
        }

        return false;
    }

    /**
     * Step 3: d-suffixes
     *
     */
    private static function step3(&$word, $rvIndex)
    {
        // Delete suffix i if in RV and preceded by c
        if (self::searchIfInRv($word, ['i'], $rvIndex) !== false) {
            $letter = self::substr($word, -2, 1);

            if ($letter == 'c') {
                $word = self::substr($word, 0, -1);
            }

            return true;
        }

        return false;
    }

    /**
     * Step 4
     */
    private static function step4(&$word, $rvIndex)
    {
        // If the word ends with one of the suffixes "os   a   i   o   á   í   ó" in RV, delete it
        if (($position = self::searchIfInRv($word, ['os', 'a', 'i', 'o', 'á', 'í', 'ó'], $rvIndex)) !== false) {
            $word = self::substr($word, 0, $position);

            return true;
        }

        return false;
    }

    /**
     * Step 5
     */
    private static function step5(&$word, $rvIndex)
    {
        // If the word ends with one of "e   é   ê" in RV, delete it, and if preceded by gu (or ci) with the u (or i) in RV, delete the u (or i).
        if (self::searchIfInRv($word, ['e', 'é', 'ê'], $rvIndex) !== false) {
            $word = self::substr($word, 0, -1);

            if (($position2 = self::search($word, ['gu', 'ci'])) !== false) {
                if (self::inRv(($position2 + 1), $rvIndex)) {
                    $word = self::substr($word, 0, -1);
                }
            }

            return true;
        } elseif (self::search($word, ['ç']) !== false) {
            $word = preg_replace('#(ç)$#u', 'c', $word);

            return true;
        }

        return false;
    }

    private static function finish(&$word)
    {
        // turn U and Y back into lower case, and remove the umlaut accent from a, o and u.
        $word = self::str_replace(['a~', 'o~'], ['ã', 'õ'], $word);
    }

    /**
     * Tries to detect if a string is in Unicode encoding
     *
     * @author <bmorel@ssi.fr>
     * @link   http://www.php.net/manual/en/function.utf8-encode.php
     */
    private static function check($str)
    {
        for ($i = 0; $i < strlen($str); $i++) {
            if (ord($str[$i]) < 0x80) {
                continue;
            }
            # 0bbbbbbb
            elseif ((ord($str[$i])&0xE0) == 0xC0) {
                $n = 1;
            }
            # 110bbbbb
            elseif ((ord($str[$i])&0xF0) == 0xE0) {
                $n = 2;
            }
            # 1110bbbb
            elseif ((ord($str[$i])&0xF8) == 0xF0) {
                $n = 3;
            }
            # 11110bbb
            elseif ((ord($str[$i])&0xFC) == 0xF8) {
                $n = 4;
            }
            # 111110bb
            elseif ((ord($str[$i])&0xFE) == 0xFC) {
                $n = 5;
            }
            # 1111110b
            else {
                return false;
            }
            # Does not match any model
            for ($j = 0; $j < $n; $j++) {
                # n bytes matching 10bbbbbb follow ?
                if ((++$i == strlen($str)) || ((ord($str[$i])&0xC0) != 0x80)) {
                    return false;
                }

            }
        }
        return true;
    }

    /**
     * Unicode aware replacement for strlen()
     *
     * utf8_decode() converts characters that are not in ISO-8859-1
     * to '?', which, for the purpose of counting, is alright - It's
     * even faster than mb_strlen.
     *
     * @author <chernyshevsky at hotmail dot com>
     * @see    strlen()
     * @see    utf8_decode()
     */
    private static function strlen($string)
    {
        return mb_strlen($string, 'UTF-8');
    }

    /**
     * Unicode aware replacement for substr()
     *
     * @author lmak at NOSPAM dot iti dot gr
     * @link   http://www.php.net/manual/en/function.substr.php
     * @see    substr()
     */
    private static function substr($str, $start, $length = null)
    {
        $ar = [];
        preg_match_all("/./u", $str, $ar);

        if ($length != null) {
            return join("", array_slice($ar[0], $start, $length));
        } else {
            return join("", array_slice($ar[0], $start));
        }
    }

    /**
     * Unicode aware replacement for strrepalce()
     *
     * @author Harry Fuecks <hfuecks@gmail.com>
     * @see    strreplace();
     */
    private static function str_replace($s, $r, $str)
    {
        if (!is_array($s)) {
            $s = '!' . preg_quote($s, '!') . '!u';
        } else {
            foreach ($s as $k => $v) {
                $s[$k] = '!' . preg_quote($v) . '!u';
            }
        }
        return preg_replace($s, $r, $str);
    }

    /**
     * This is a unicode aware replacement for strtolower()
     *
     * Uses mb_string extension if available
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     * @see    strtolower()
     * @see    utf8_strtoupper()
     */
    private static function strtolower($string)
    {
        if (!defined('UTF8_NOMBSTRING') && function_exists('mb_strtolower')) {
            return mb_strtolower($string, 'utf-8');
        }

        //global $utf8_upper_to_lower;
        $utf8_upper_to_lower = array_flip(self::$utf8_lower_to_upper);
        $uni                 = self::utf8_to_unicode($string);
        $cnt                 = count($uni);
        for ($i = 0; $i < $cnt; $i++) {
            if ($utf8_upper_to_lower[$uni[$i]]) {
                $uni[$i] = $utf8_upper_to_lower[$uni[$i]];
            }
        }
        return self::unicode_to_utf8($uni);
    }

    /**
     * This function returns any UTF-8 encoded text as a list of
     * Unicode values:
     *
     * @author Scott Michael Reynen <scott@randomchaos.com>
     * @link   http://www.randomchaos.com/document.php?source=php_and_unicode
     * @see    unicode_to_utf8()
     */
    private static function utf8_to_unicode(&$str)
    {
        $unicode     = [];
        $values      = [];
        $looking_for = 1;

        for ($i = 0; $i < strlen($str); $i++) {
            $this_value = ord($str[$i]);
            if ($this_value < 128) {
                $unicode[] = $this_value;
            } else {
                if (count($values) == 0) {
                    $looking_for = ($this_value < 224) ? 2 : 3;
                }

                $values[] = $this_value;
                if (count($values) == $looking_for) {
                    $number = ($looking_for == 3) ?
                    (($values[0] % 16) * 4096) + (($values[1] % 64) * 64) + ($values[2] % 64) :
                    (($values[0] % 32) * 64) + ($values[1] % 64);
                    $unicode[]   = $number;
                    $values      = [];
                    $looking_for = 1;
                }
            }
        }
        return $unicode;
    }

    /**
     * This function converts a Unicode array back to its UTF-8 representation
     *
     * @author Scott Michael Reynen <scott@randomchaos.com>
     * @link   http://www.randomchaos.com/document.php?source=php_and_unicode
     * @see    utf8_to_unicode()
     */
    private static function unicode_to_utf8(&$str)
    {
        if (!is_array($str)) {
            return '';
        }

        $utf8 = '';
        foreach ($str as $unicode) {
            if ($unicode < 128) {
                $utf8 .= chr($unicode);
            } elseif ($unicode < 2048) {
                $utf8 .= chr(192 + (($unicode - ($unicode % 64)) / 64));
                $utf8 .= chr(128 + ($unicode % 64));
            } else {
                $utf8 .= chr(224 + (($unicode - ($unicode % 4096)) / 4096));
                $utf8 .= chr(128 + ((($unicode % 4096) - ($unicode % 64)) / 64));
                $utf8 .= chr(128 + ($unicode % 64));
            }
        }
        return $utf8;
    }

    /**
     * This is an Unicode aware replacement for strrpos
     *
     * Uses mb_string extension if available
     *
     * @author Harry Fuecks <hfuecks@gmail.com>
     * @see    strpos()
     */
    private static function strrpos($haystack, $needle, $offset = 0)
    {
        if (!defined('UTF8_NOMBSTRING') && function_exists('mb_strrpos')) {
            return mb_strrpos($haystack, $needle, $offset, 'utf-8');
        }

        if (!$offset) {
            $ar    = self::explode($needle, $haystack);
            $count = count($ar);
            if ($count > 1) {
                return self::strlen($haystack) - self::strlen($ar[($count - 1)]) - self::strlen($needle);
            }
            return false;
        } else {
            if (!is_int($offset)) {
                trigger_error('Offset must be an integer', E_USER_WARNING);
                return false;
            }

            $str = self::substr($haystack, $offset);

            if (false !== ($pos = self::strrpos($str, $needle))) {
                return $pos + $offset;
            }
            return false;
        }
    }

    /**
     * Unicode aware replacement for explode
     *
     * @author Harry Fuecks <hfuecks@gmail.com>
     * @see    explode();
     */
    private static function explode($sep, $str)
    {
        if ($sep == '') {
            trigger_error('Empty delimiter', E_USER_WARNING);
            return false;
        }

        return preg_split('!' . preg_quote($sep, '!') . '!u', $str);
    }
}
