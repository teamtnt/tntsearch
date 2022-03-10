<?php

use TeamTNT\TNTSearch\Stemmer\LatvianStemmer;

class LatvianStemmerTest extends PHPUnit\Framework\TestCase
{
    public function testStem()
    {
        $stemmer = new LatvianStemmer;
        $this->assertEquals("pien", $stemmer->stem("piens"));
        $this->assertEquals("pien", $stemmer->stem("piena"));
        $this->assertEquals("pien", $stemmer->stem("pienu"));
        $this->assertEquals("pien", $stemmer->stem("pienam"));
        $this->assertEquals("izgatavot", $stemmer->stem("izgatavotajam"));
        $this->assertEquals("gudr", $stemmer->stem("gudrajiem"));

    }
}
