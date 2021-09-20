<?php

namespace TeamTNT\TNTSearch\Stemmer;

/**
 * 
 * @link https://github.com/Tutanchamon/pl_stemmer
 * Simple stemmer for polish language based on pl_stemmer by Błażej Kubiński.
 * 
 */

class PolishStemmer implements Stemmer
{

    public static function removeNouns($word)
    {
        if (strlen($word) > 7 && in_array(mb_substr($word, -5), array("zacja", "zacją", "zacji"))) {
            return mb_substr($word, 0, -4);
        }
        if (strlen($word) > 6 && in_array(mb_substr($word, -4), array("acja", "acji", "acją", "tach", "anie", "enie", "eniu", "aniu"))) {
            return mb_substr($word, 0, -4);
        }
        if (strlen($word) > 6 && (mb_substr($word, -4) == "tyka")) {
            return mb_substr($word, 0, -2);
        }
        if (strlen($word) > 5 && in_array(mb_substr($word, -3), array("ach", "ami", "nia", "niu", "cia", "ciu"))) {
            return mb_substr($word, 0, -3);
        }
        if (strlen($word) > 5 && in_array(mb_substr($word, -3), array("cji", "cja", "cją"))) {
            return mb_substr($word, 0, -2);
        }
        if (strlen($word) > 5 && in_array(mb_substr($word, -2), array("ce", "ta"))) {
            return mb_substr($word, 0, -2);
        }
        return $word;
    }

    public static function removeDiminutive($word)
    {
        if (strlen($word) > 6) {
            if (in_array(mb_substr($word, -5), array("eczek", "iczek", "iszek", "aszek", "uszek"))) {
                return mb_substr($word, 0, -5);
            }
            if (in_array(mb_substr($word, -4), array("enek", "ejek", "erek"))) {
                return mb_substr($word, 0, -2);
            }
        }
        if (strlen($word) > 4) {
            if (in_array(mb_substr($word, -2), array("ek", "ak"))) {
                return mb_substr($word, 0, -2);
            }
        }
        return $word;
    }

    public static function removeAdjectiveEnds($word)
    {
        if (strlen($word) > 7 && (mb_substr($word, 0, 3) == "naj") && in_array(mb_substr($word, -3), array("sze", "szy"))) {
            return mb_substr($word, 3, -3);
        }
        if (strlen($word) > 7 && (mb_substr($word, 0, 3) == "naj") && (mb_substr($word, 0, 5) == "szych")) {
            return mb_substr($word, 3, -5);
        }
        if (strlen($word) > 6 && (mb_substr($word, -4) == "czny")) {
            return mb_substr($word, 0, -4);
        }
        if (strlen($word) > 5 && in_array(mb_substr($word, -3), array("owy", "owa", "owe", "ych", "ego"))) {
            return mb_substr($word, 0, -3);
        }
        if (strlen($word) > 5 && (mb_substr($word, -2) == "ej")) {
            return mb_substr($word, 0, -2);
        }
        return $word;
    }

    public static function removeVerbsEnds($word)
    {
        if (strlen($word) > 5 && (mb_substr($word, -3) == "bym")) {
            return mb_substr($word, 0, -3);
        }
        if (strlen($word) > 5 && in_array(mb_substr($word, -3), array("esz", "asz", "cie", "eść", "aść", "łem", "amy", "emy"))) {
            return mb_substr($word, 0, -3);
        }
        if (strlen($word) > 3 && in_array(mb_substr($word, -3), array("esz", "asz", "eść", "aść", "eć", "ać"))) {
            return mb_substr($word, 0, -2);
        }
        if (strlen($word) > 3 && in_array(mb_substr($word, -2), array("aj"))) {
            return mb_substr($word, 0, -1);
        }
        if (strlen($word) > 3 && in_array(mb_substr($word, -2), array("ać", "em", "am", "ał", "ił", "ić", "ąc"))) {
            return mb_substr($word, 0, -2);
        }
        return $word;
    }

    public static function removeAdverbsEnds($word)
    {
        if (strlen($word) > 4 && in_array(mb_substr($word, -3), array("nie", "wie", "rze"))) {
            return mb_substr($word, 0, -2);
        }
        return $word;
    }

    public static function removePluralForms($word)
    {
        if (strlen($word) > 4 && in_array(mb_substr($word, -2), array("ów", "om"))) {
            return mb_substr($word, 0, -2);
        }
        if (strlen($word) > 4 && (mb_substr($word, -3) == "ami")) {
            return mb_substr($word, 0, -3);
        }
        return $word;
    }

    public static function removeGeneralEnds($word)
    {
        if (strlen($word) > 4 && in_array(substr($word, -2), array("ia", "ie"))) {
            return substr($word, 0, -2);
        }
        if (strlen($word) > 4 && in_array(substr($word, -1), array("u", "ą", "i", "a", "ę", "y", "ę", "ł"))) {
            return substr($word, 0, -1);
        }
        return $word;
    }


    public static function stem($word)
    {

        $word = mb_strtolower($word);

        $stem = $word;

        $stem = self::removeNouns($stem);
        $stem = self::removeDiminutive($stem);
        $stem = self::removeAdjectiveEnds($stem);
        $stem = self::removeVerbsEnds($stem);
        $stem = self::removeAdverbsEnds($stem);
        $stem = self::removePluralForms($stem);
        $stem = self::removeGeneralEnds($stem);

        return $stem;
    }
}
