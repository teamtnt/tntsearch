<?php

use TeamTNT\TNTSearch\Stemmer\PortugueseStemmer;

class PortugueseStemmerTest extends PHPUnit\Framework\TestCase
{

    public function testStem()
    {
        $stemmer = new PortugueseStemmer;
        $this->assertEquals("gost", $stemmer->stem("gostou"));
        $this->assertEquals("gost", $stemmer->stem("gosto"));
        $this->assertEquals("gost", $stemmer->stem("gostaram"));
    }
}
