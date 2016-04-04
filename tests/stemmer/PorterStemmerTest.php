<?php

use TeamTNT\TNTSearch\Stemmer\PorterStemmer;

class PorterStemmerTestTest extends PHPUnit_Framework_TestCase
{

    public function testStem()
    {
        $stemmer = new PorterStemmer;
        $this->assertEquals("test", $stemmer->stem("testing"));
        $this->assertEquals("sourc", $stemmer->stem("source"));
        $this->assertEquals("code", $stemmer->stem("code"));
        $this->assertEquals("is", $stemmer->stem("is"));
        $this->assertEquals("funni", $stemmer->stem("funny"));

    }
}