<?php

namespace TeamTNT\TNTSearch\Stemmer;

/**
 * Semple stemmer for ukrainian language
 */

class UkrainianStemmer implements StemmerInterface
{
    private static string $VOWEL = '/аеиоуюяіїє/u';
    /* http://uk.wikipedia.org/wiki/Голосний_звук */
    // var $PERFECTIVEGROUND = '/((ив|ивши|ившись|ыв|ывши|ывшись((?<=[ая])(в|вши|вшись)))$/';
    private static string $PERFECTIVEGROUND = '/(ив|ивши|ившись|ів|івши|івшись((?<=[ая|я])(в|вши|вшись)))$/u';
    private static string $REFLEXIVE = '/(с[яьи])$/u'; // http://uk.wikipedia.org/wiki/Рефлексивне_дієслово
    private static string $ADJECTIVE = '/(ими|ій|ий|а|е|ова|ове|ів|є|їй|єє|еє|я|ім|ем|им|ім|их|іх|ою|йми|іми|у|ю|ого|ому|ої)$/u'; //http://uk.wikipedia.org/wiki/Прикметник + http://wapedia.mobi/uk/Прикметник
    private static string $PARTICIPLE = '/(ий|ого|ому|им|ім|а|ій|у|ою|ій|і|их|йми|их)$/u'; //http://uk.wikipedia.org/wiki/Дієприкметник
    private static string $VERB = '/(сь|ся|ив|ать|ять|у|ю|ав|али|учи|ячи|вши|ши|е|ме|ати|яти|є)$/u'; //http://uk.wikipedia.org/wiki/Дієслово
    private static string $NOUN = '/(а|ев|ов|е|ями|ами|еи|и|ей|ой|ий|й|иям|ям|ием|ем|ам|ом|о|у|ах|иях|ях|ы|ь|ию|ью|ю|ия|ья|я|і|ові|ї|ею|єю|ою|є|еві|ем|єм|ів|їв|\'ю)$/u'; //http://uk.wikipedia.org/wiki/Іменник
    private static string $RVRE = '/^(.*?[аеиоуюяіїє])(.*)$/u';
    private static string $DERIVATIONAL = '/[^аеиоуюяіїє][аеиоуюяіїє]+[^аеиоуюяіїє]+[аеиоуюяіїє].*(?<=о)сть?$/u';
    
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
            self::s($RV, '/[и|i]$/u', '');
            
            // Step 3
            if (self::m($RV, self::$DERIVATIONAL)) {
                self::s($RV, '/сть?$/u', '');
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
