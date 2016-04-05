<?php

use TeamTNT\TNTSearch\Stemmer\GermanStemmer;

class GermanStemmerTestTest extends PHPUnit_Framework_TestCase
{

    public function testStem()
    {
        $stemmer = new GermanStemmer;
        $this->assertEquals("vergnug", $stemmer->stem("vergnüglich"));
        $this->assertEquals("unfallversicherungstrag", $stemmer->stem("Unfallversicherungsträger"));
    }
}