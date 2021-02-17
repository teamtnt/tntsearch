<?php

use TeamTNT\TNTSearch\Stemmer\GermanStemmer;

class GermanStemmerTest extends PHPUnit\Framework\TestCase
{

    public function testStem()
    {
        $stemmer = new GermanStemmer;
        $this->assertEquals("vergnug", $stemmer->stem("vergnüglich"));
        $this->assertEquals("unfallversicherungstrag", $stemmer->stem("Unfallversicherungsträger"));
    }
}
