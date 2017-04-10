<?php

namespace TeamTNT\TNTSearch\Stemmer;

/**
 * Semple stemmer for russian language
 */

class RussianStemmer implements Stemmer
{
    private static $VOWEL = '/аеиоуыэюя/u';
    private static $PERFECTIVEGROUND = '/((ив|ивши|ившись|ыв|ывши|ывшись)|((?<=[ая])(в|вши|вшись)))$/u';
    private static $REFLEXIVE = '/(с[яь])$/u';
    private static $ADJECTIVE = '/(ее|ие|ые|ое|ими|ыми|ей|ий|ый|ой|ем|им|ым|ом|его|ого|ему|ому|их|ых|ую|юю|ая|яя|ою|ею)$/u';
    private static $PARTICIPLE = '/((ивш|ывш|ующ)|((?<=[ая])(ем|нн|вш|ющ|щ)))$/u';
    private static $VERB = '/((ила|ыла|ена|ейте|уйте|ите|или|ыли|ей|уй|ил|ыл|им|ым|ен|ило|ыло|ено|ят|ует|уют|ит|ыт|ены|ить|ыть|ишь|ую|ю)|((?<=[ая])(ла|на|ете|йте|ли|й|л|ем|н|ло|но|ет|ют|ны|ть|ешь|нно)))$/u';
    private static $NOUN = '/(а|ев|ов|ие|ье|е|иями|ями|ами|еи|ии|и|ией|ей|ой|ий|й|иям|ям|ием|ем|ам|ом|о|у|ах|иях|ях|ы|ь|ию|ью|ю|ия|ья|я)$/u';
    private static $RVRE = '/^(.*?[аеиоуыэюя])(.*)$/u';
    private static $DERIVATIONAL = '/[^аеиоуыэюя][аеиоуыэюя]+[^аеиоуыэюя]+[аеиоуыэюя].*(?<=о)сть?$/u';
    
    private static function s(&$s, $re, $to)
    {
        $orig = $s;
        $s    = preg_replace($re, $to, $s);
        return $orig !== $s;
    }
    
    private static function m($s, $re)
    {
        return preg_match($re, $s);
    }
    
    public static function stem($word)
    {
        $word = mb_strtolower($word);
        $word = str_replace('ё', 'е', $word);

        $stem = $word;
        
        do {
            if (!preg_match(self::$RVRE, $word, $p)) {
                break;
            }
            $start = $p[1];
            $RV    = $p[2];
            if (!$RV) {
                break;
            }
            
            // Step 1
            if (!self::s($RV, self::$PERFECTIVEGROUND, '')) {
                self::s($RV, self::$REFLEXIVE, '');
                
                if (self::s($RV, self::$ADJECTIVE, '')) {
                    self::s($RV, self::$PARTICIPLE, '');
                } else {
                    if (!self::s($RV, self::$VERB, '')) {
                        self::s($RV, self::$NOUN, '');
                    }
                }
            }
            
            // Step 2
            self::s($RV, '/и$/u', '');
            
            // Step 3
            if (self::m($RV, self::$DERIVATIONAL)) {
                self::s($RV, '/ость?$/u', '');
            }
            
            // Step 4
            if (!self::s($RV, '/ь$/u', '')) {
                self::s($RV, '/ейше?/u', '');
                self::s($RV, '/нн$/u', 'н');
            }
            
            $stem = $start . $RV;
        } while (FALSE);
        
        return $stem;
    }
    
}
