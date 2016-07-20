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

    public function testAgainstDictionary()
    {
        $vocabulary = explode("\n", file_get_contents(__DIR__ ."/porter/input.txt"));
        $expected = explode("\n", file_get_contents(__DIR__ ."/porter/output.txt"));

        $stemmer = new PorterStemmer;

        foreach ($vocabulary as $key => $word) {
            $stem = $stemmer->stem(trim($word));
            $this->assertEquals(trim($expected[$key]), $stem);
        }
    }
}