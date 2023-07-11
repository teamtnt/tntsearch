<?php

use TeamTNT\TNTSearch\Stemmer\PolishStemmer;

class PolishStemmerTest extends PHPUnit\Framework\TestCase
{
    public function testStem()
    {
        $stemmer = new PolishStemmer;
        $this->assertEquals("czujnik", $stemmer->stem("czujnikami"));
        $this->assertEquals("kabel", $stemmer->stem("kabelek"));
        $this->assertEquals("mocniej", $stemmer->stem("najmocniejszy"));
        $this->assertEquals("przekaźnik", $stemmer->stem("przekaźnikowy"));
        $this->assertEquals("instaluj", $stemmer->stem("instalujesz"));
        $this->assertEquals("instaluj", $stemmer->stem("instalujesz"));
        $this->assertEquals("ciekaw", $stemmer->stem("ciekawie"));
        $this->assertEquals("przekaźnik", $stemmer->stem("przekaźników"));
        $this->assertEquals("moduł", $stemmer->stem("modułu"));
        $this->assertEquals($stemmer->stem("modułów"), $stemmer->stem("moduły"));
    }
}
