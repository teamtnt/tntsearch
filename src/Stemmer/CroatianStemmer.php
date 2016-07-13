<?php

/*
This is a reimplementation in PHP of a simple rule-based stemmer for Croatian
at http://nlp.ffzg.hr/resources/tools/stemmer-for-croatian/ (Python).
The original author is Ivan Pandžić. */

namespace TeamTNT\TNTSearch\Stemmer;

class CroatianStemmer implements Stemmer
{
    protected static $stop = ['biti', 'jesam', 'budem', 'sam', 'jesi', 'budeš', 'si', 'jesmo', 'budemo',
        'smo', 'jeste', 'budete', 'ste', 'jesu', 'budu', 'su', 'bih', 'bijah', 'bjeh',
        'bijaše', 'bi', 'bje', 'bješe', 'bijasmo', 'bismo', 'bjesmo', 'bijaste', 'biste',
        'bjeste', 'bijahu', 'biste', 'bjeste', 'bijahu', 'bi', 'biše', 'bjehu', 'bješe',
        'bio', 'bili', 'budimo', 'budite', 'bila', 'bilo', 'bile', 'ću', 'ćeš', 'će',
        'ćemo', 'ćete', 'želim', 'želiš', 'želi', 'želimo', 'želite', 'žele', 'moram',
        'moraš', 'mora', 'moramo', 'morate', 'moraju', 'trebam', 'trebaš', 'treba',
        'trebamo', 'trebate', 'trebaju', 'mogu', 'možeš', 'može', 'možemo', 'možete'];

    public static function stem($token)
    {
        if (in_array($token, self::$stop)) {
            return $token;
        }
        return self::korjenuj(self::transformiraj($token));
    }

    public static function istakniSlogotvornoR($niz)
    {
        return preg_replace('/(^|[^aeiou])r($|[^aeiou])/', '\1R\2', $niz);
    }

    public static function imaSamoglasnik($niz)
    {
        preg_match('/[aeiouR]/', self::istakniSlogotvornoR($niz), $matches);

        if (count($matches) > 0) {
            return true;
        }

        return false;
    }

    public static function transformiraj($pojavnica)
    {
        foreach (self::$transformations as $trazi => $zamijeni) {
            if (self::endsWith($pojavnica, $trazi)) {
                return substr($pojavnica, 0, -1 * strlen($trazi)) . $zamijeni;
            }
        }
        return $pojavnica;
    }

    public static function korjenuj($pojavnica)
    {
        foreach (self::$rules as $rule) {
            $rules    = explode(" ", $rule);
            $osnova   = $rules[0];
            $nastavak = $rules[1];
            preg_match("/^(" . $osnova . ")(" . $nastavak . ")$/", $pojavnica, $dioba);
            if (!empty($dioba)) {
                if (self::imaSamoglasnik($dioba[1]) && strlen($dioba[1]) > 1) {
                    return $dioba[1];
                }
            }
        }
        return $pojavnica;
    }

    public static function endsWith($haystack, $needle)
    {
        // search forward starting from end minus needle length characters
        return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== false);
    }

    protected static $transformations = [
        'lozi'     => 'loga',
        'lozima'   => 'loga',
        'pjesi'    => 'pjeh',
        'pjesima'  => 'pjeh',
        'vojci'    => 'vojka',
        'bojci'    => 'bojka',
        'jaci'     => 'jak',
        'jacima'   => 'jak',
        'čajan'    => 'čajni',
        'ijeran'   => 'ijerni',
        'laran'    => 'larni',
        'ijesan'   => 'ijesni',
        'anjac'    => 'anjca',
        'ajac'     => 'ajca',
        'ajaca'    => 'ajca',
        'ljaca'    => 'ljca',
        'ljac'     => 'ljca',
        'ejac'     => 'ejca',
        'ejaca'    => 'ejca',
        'ojac'     => 'ojca',
        'ojaca'    => 'ojca',
        'ajaka'    => 'ajka',
        'ojaka'    => 'ojka',
        'šaca'     => 'šca',
        'šac'      => 'šca',
        'inzima'   => 'ing',
        'inzi'     => 'ing',
        'tvenici'  => 'tvenik',
        'tetici'   => 'tetika',
        'teticima' => 'tetika',
        'nstava'   => 'nstva',
        'nicima'   => 'nik',
        'ticima'   => 'tik',
        'zicima'   => 'zik',
        'snici'    => 'snik',
        'kuse'     => 'kusi',
        'kusan'    => 'kusni',
        'kustava'  => 'kustva',
        'dušan'    => 'dušni',
        'antan'    => 'antni',
        'bilan'    => 'bilni',
        'tilan'    => 'tilni',
        'avilan'   => 'avilni',
        'silan'    => 'silni',
        'gilan'    => 'gilni',
        'rilan'    => 'rilni',
        'nilan'    => 'nilni',
        'alan'     => 'alni',
        'ozan'     => 'ozni',
        'rave'     => 'ravi',
        'stavan'   => 'stavni',
        'pravan'   => 'pravni',
        'tivan'    => 'tivni',
        'sivan'    => 'sivni',
        'atan'     => 'atni',
        'cenata'   => 'centa',
        'denata'   => 'denta',
        'genata'   => 'genta',
        'lenata'   => 'lenta',
        'menata'   => 'menta',
        'jenata'   => 'jenta',
        'venata'   => 'venta',
        'tetan'    => 'tetni',
        'pletan'   => 'pletni',
        'šave'     => 'šavi',
        'manata'   => 'manta',
        'tanata'   => 'tanta',
        'lanata'   => 'lanta',
        'sanata'   => 'santa',
        'ačak'     => 'ačka',
        'ačaka'    => 'ačka',
        'ušak'     => 'uška',
        'atak'     => 'atka',
        'ataka'    => 'atka',
        'atci'     => 'atka',
        'atcima'   => 'atka',
        'etak'     => 'etka',
        'etaka'    => 'etka',
        'itak'     => 'itka',
        'itaka'    => 'itka',
        'itci'     => 'itka',
        'otak'     => 'otka',
        'otaka'    => 'otka',
        'utak'     => 'utka',
        'utaka'    => 'utka',
        'utci'     => 'utka',
        'utcima'   => 'utka',
        'eskan'    => 'eskna',
        'tičan'    => 'tični',
        'ojsci'    => 'ojska',
        'esama'    => 'esma',
        'metara'   => 'metra',
        'centar'   => 'centra',
        'centara'  => 'centra',
        'istara'   => 'istra',
        'istar'    => 'istra',
        'ošću'     => 'osti',
        'daba'     => 'dba',
        'čcima'    => 'čka',
        'čci'      => 'čka',
        'mac'      => 'mca',
        'maca'     => 'mca',
        'naca'     => 'nca',
        'nac'      => 'nca',
        'voljan'   => 'voljni',
        'anaka'    => 'anki',
        'vac'      => 'vca',
        'vaca'     => 'vca',
        'saca'     => 'sca',
        'sac'      => 'sca',
        'naca'     => 'nca',
        'nac'      => 'nca',
        'raca'     => 'rca',
        'rac'      => 'rca',
        'aoca'     => 'alca',
        'alaca'    => 'alca',
        'alac'     => 'alca',
        'elaca'    => 'elca',
        'elac'     => 'elca',
        'olaca'    => 'olca',
        'olac'     => 'olca',
        'olce'     => 'olca',
        'njac'     => 'njca',
        'njaca'    => 'njca',
        'ekata'    => 'ekta',
        'ekat'     => 'ekta',
        'izam'     => 'izma',
        'izama'    => 'izma',
        'jebe'     => 'jebi',
        'baci'     => 'baci',
        'ašan'     => 'ašni',
    ];

    protected static $rules = [
        ".+(s|š)k ijima|ijega|ijemu|ijem|ijim|ijih|ijoj|ijeg|iji|ije|ija|oga|ome|omu|ima|og|om|im|ih|oj|i|e|o|a|u",
        ".+(s|š)tv ima|om|o|a|u",
        // N
        ".+(t|m|p|r|g)anij ama|ima|om|a|u|e|i| ",
        ".+an inom|ina|inu|ine|ima|in|om|u|i|a|e| ",
        ".+in ima|ama|om|a|e|i|u|o| ",
        ".+on ovima|ova|ove|ovi|ima|om|a|e|i|u| ",
        ".+n ijima|ijega|ijemu|ijeg|ijem|ijim|ijih|ijoj|iji|ije|ija|iju|ima|ome|omu|oga|oj|om|ih|im|og|o|e|a|u|i| ",
        // Ć
        ".+(a|e|u)ć oga|ome|omu|ega|emu|ima|oj|ih|om|eg|em|og|uh|im|e|a",
        // G
        ".+ugov ima|i|e|a",
        ".+ug ama|om|a|e|i|u|o",
        ".+log ama|om|a|u|e| ",
        ".+[^eo]g ovima|ama|ovi|ove|ova|om|a|e|i|u|o| ",
        // I
        ".+(rrar|ott|ss|ll)i jem|ja|ju|o| ",
        // J
        ".+uj ući|emo|ete|mo|em|eš|e|u| ",
        ".+(c|č|ć|đ|l|r)aj evima|evi|eva|eve|ama|ima|em|a|e|i|u| ",
        ".+(b|c|d|l|n|m|ž|g|f|p|r|s|t|z)ij ima|ama|om|a|e|i|u|o| ",
        // L
        //.+al inom|ina|inu|ine|ima|om|in|i|a|e
        //.+[^(lo|ž)]il ima|om|a|e|u|i|
        ".+[^z]nal ima|ama|om|a|e|i|u|o| ",
        ".+ijal ima|ama|om|a|e|i|u|o| ",
        ".+ozil ima|om|a|e|u|i| ",
        ".+olov ima|i|a|e",
        ".+ol ima|om|a|u|e|i| ",
        // M
        ".+lem ama|ima|om|a|e|i|u|o| ",
        ".+ram ama|om|a|e|i|u|o",
        //.+(es|e|u)m ama|om|a|e|i|u|o
        // R
        //.+(a|d|e|o|u)r ama|ima|om|u|a|e|i|
        ".+(a|d|e|o)r ama|ima|om|u|a|e|i| ",
        // S
        ".+(e|i)s ima|om|e|a|u",
        // Š
        ".+(t|n|j|k|j|t|b|g|v)aš ama|ima|om|em|a|u|i|e| ",
        ".+(e|i)š ima|ama|om|em|i|e|a|u| ",
        // T
        ".+ikat ima|om|a|e|i|u|o| ",
        ".+lat ima|om|a|e|i|u|o| ",
        ".+et ama|ima|om|a|e|i|u|o| ",
        //.+ot ama|ima|om|a|u|e|i|
        ".+(e|i|k|o)st ima|ama|om|a|e|i|u|o| ",
        ".+išt ima|em|a|e|u",
        //.+ut ovima|evima|ove|ovi|ova|eve|evi|eva|ima|om|a|u|e|i|
        // V
        ".+ova smo|ste|hu|ti|še|li|la|le|lo|t|h|o",
        ".+(a|e|i)v ijemu|ijima|ijega|ijeg|ijem|ijim|ijih|ijoj|oga|ome|omu|ima|ama|iji|ije|ija|iju|im|ih|oj|om|og|i|a|u|e|o| ",
        ".+[^dkml]ov ijemu|ijima|ijega|ijeg|ijem|ijim|ijih|ijoj|oga|ome|omu|ima|iji|ije|ija|iju|im|ih|oj|om|og|i|a|u|e|o| ",
        ".+(m|l)ov ima|om|a|u|e|i| ",
        // PRIDJEVI
        ".+el ijemu|ijima|ijega|ijeg|ijem|ijim|ijih|ijoj|oga|ome|omu|ima|iji|ije|ija|iju|im|ih|oj|om|og|i|a|u|e|o| ",
        ".+(a|e|š)nj ijemu|ijima|ijega|ijeg|ijem|ijim|ijih|ijoj|oga|ome|omu|ima|iji|ije|ija|iju|ega|emu|eg|em|im|ih|oj|om|og|a|e|i|o|u",
        ".+čin ama|ome|omu|oga|ima|og|om|im|ih|oj|a|u|i|o|e| ",
        ".+roši vši|smo|ste|še|mo|te|ti|li|la|lo|le|m|š|t|h|o",
        ".+oš ijemu|ijima|ijega|ijeg|ijem|ijim|ijih|ijoj|oga|ome|omu|ima|iji|ije|ija|iju|im|ih|oj|om|og|i|a|u|e| ",
        ".+(e|o)vit ijima|ijega|ijemu|ijem|ijim|ijih|ijoj|ijeg|iji|ije|ija|oga|ome|omu|ima|og|om|im|ih|oj|i|e|o|a|u| ",
        //.+tit ijima|ijega|ijemu|ijem|ijim|ijih|ijoj|ijeg|iji|ije|ija|oga|ome|omu|ima|og|om|im|ih|oj|e|o|a|u|i|
        ".+ast ijima|ijega|ijemu|ijem|ijim|ijih|ijoj|ijeg|iji|ije|ija|oga|ome|omu|ima|og|om|im|ih|oj|i|e|o|a|u| ",
        ".+k ijemu|ijima|ijega|ijeg|ijem|ijim|ijih|ijoj|oga|ome|omu|ima|iji|ije|ija|iju|im|ih|oj|om|og|i|a|u|e|o| ",
        // GLAGOLI
        ".+(e|a|i|u)va jući|smo|ste|jmo|jte|ju|la|le|li|lo|mo|na|ne|ni|no|te|ti|še|hu|h|j|m|n|o|t|v|š| ",
        ".+ir ujemo|ujete|ujući|ajući|ivat|ujem|uješ|ujmo|ujte|avši|asmo|aste|ati|amo|ate|aju|aše|ahu|ala|alo|ali|ale|uje|uju|uj|al|an|am|aš|at|ah|ao",
        ".+ač ismo|iste|iti|imo|ite|iše|eći|ila|ilo|ili|ile|ena|eno|eni|ene|io|im|iš|it|ih|en|i|e",
        ".+ača vši|smo|ste|smo|ste|hu|ti|mo|te|še|la|lo|li|le|ju|na|no|ni|ne|o|m|š|t|h|n",
        //.+ači smo|ste|ti|li|la|lo|le|mo|te|še|m|š|t|h|o|
        // Druga_vrsta
        ".+n uvši|usmo|uste|ući|imo|ite|emo|ete|ula|ulo|ule|uli|uto|uti|uta|em|eš|uo|ut|e|u|i",
        ".+ni vši|smo|ste|ti|mo|te|mo|te|la|lo|le|li|m|š|o",
        // A
        ".+((a|r|i|p|e|u)st|[^o]g|ik|uc|oj|aj|lj|ak|ck|čk|šk|uk|nj|im|ar|at|et|št|it|ot|ut|zn|zv)a jući|vši|smo|ste|jmo|jte|jem|mo|te|je|ju|ti|še|hu|la|li|le|lo|na|no|ni|ne|t|h|o|j|n|m|š",
        ".+ur ajući|asmo|aste|ajmo|ajte|amo|ate|aju|ati|aše|ahu|ala|ali|ale|alo|ana|ano|ani|ane|al|at|ah|ao|aj|an|am|aš",
        ".+(a|i|o)staj asmo|aste|ahu|ati|emo|ete|aše|ali|ući|ala|alo|ale|mo|ao|em|eš|at|ah|te|e|u| ",
        ".+(b|c|č|ć|d|e|f|g|j|k|n|r|t|u|v)a lama|lima|lom|lu|li|la|le|lo|l",
        ".+(t|č|j|ž|š)aj evima|evi|eva|eve|ama|ima|em|a|e|i|u| ",
        //.+(e|j|k|r|u|v)al ama|ima|om|u|i|a|e|o|
        //.+(e|j|k|r|t|u|v)al ih|im
        ".+([^o]m|ič|nč|uč|b|c|ć|d|đ|h|j|k|l|n|p|r|s|š|v|z|ž)a jući|vši|smo|ste|jmo|jte|mo|te|ju|ti|še|hu|la|li|le|lo|na|no|ni|ne|t|h|o|j|n|m|š",
        ".+(a|i|o)sta dosmo|doste|doše|nemo|demo|nete|dete|nimo|nite|nila|vši|nem|dem|neš|deš|doh|de|ti|ne|nu|du|la|li|lo|le|t|o",
        ".+ta smo|ste|jmo|jte|vši|ti|mo|te|ju|še|la|lo|le|li|na|no|ni|ne|n|j|o|m|š|t|h",
        ".+inj asmo|aste|ati|emo|ete|ali|ala|alo|ale|aše|ahu|em|eš|at|ah|ao",
        ".+as temo|tete|timo|tite|tući|tem|teš|tao|te|li|ti|la|lo|le",
        // I
        ".+(elj|ulj|tit|ac|ič|od|oj|et|av|ov)i vši|eći|smo|ste|še|mo|te|ti|li|la|lo|le|m|š|t|h|o",
        ".+(tit|jeb|ar|ed|uš|ič)i jemo|jete|jem|ješ|smo|ste|jmo|jte|vši|mo|še|te|ti|ju|je|la|lo|li|le|t|m|š|h|j|o",
        ".+(b|č|d|l|m|p|r|s|š|ž)i jemo|jete|jem|ješ|smo|ste|jmo|jte|vši|mo|lu|še|te|ti|ju|je|la|lo|li|le|t|m|š|h|j|o",
        ".+luč ujete|ujući|ujemo|ujem|uješ|ismo|iste|ujmo|ujte|uje|uju|iše|iti|imo|ite|ila|ilo|ili|ile|ena|eno|eni|ene|uj|io|en|im|iš|it|ih|e|i",
        ".+jeti smo|ste|še|mo|te|ti|li|la|lo|le|m|š|t|h|o",
        ".+e lama|lima|lom|lu|li|la|le|lo|l",
        ".+i lama|lima|lom|lu|li|la|le|lo|l",
        // Pridjev_t
        ".+at ijega|ijemu|ijima|ijeg|ijem|ijih|ijim|ima|oga|ome|omu|iji|ije|ija|iju|oj|og|om|im|ih|a|u|i|e|o| ",
        // Pridjev
        ".+et avši|ući|emo|imo|em|eš|e|u|i",
        ".+ ajući|alima|alom|avši|asmo|aste|ajmo|ajte|ivši|amo|ate|aju|ati|aše|ahu|ali|ala|ale|alo|ana|ano|ani|ane|am|aš|at|ah|ao|aj|an",
        ".+ anje|enje|anja|enja|enom|enoj|enog|enim|enih|anom|anoj|anog|anim|anih|eno|ovi|ova|oga|ima|ove|enu|anu|ena|ama",
        ".+ nijega|nijemu|nijima|nijeg|nijem|nijim|nijih|nima|niji|nije|nija|niju|noj|nom|nog|nim|nih|an|na|nu|ni|ne|no",
        ".+ om|og|im|ih|em|oj|an|u|o|i|e|a",
    ];
}
