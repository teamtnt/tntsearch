<?php

use TeamTNT\TNTSearch\Stemmer\PortugueseStemmer;

class PortugueseStemmerTestTest extends PHPUnit_Framework_TestCase
{

    public function testStem()
    {
        $stemmer = new PortugueseStemmer;
        $this->assertEquals("gost", $stemmer->stem("gostou"));
        $this->assertEquals("gost", $stemmer->stem("gosto"));
        $this->assertEquals("gost", $stemmer->stem("gostaram"));
    }
}