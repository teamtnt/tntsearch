<?php

use TeamTNT\TNTSearch\Stemmer\CroatianStemmer;

class CroatianStemmerTest extends PHPUnit\Framework\TestCase
{
    public function testIstakniSlogotvornoR()
    {
        $stemmer = new CroatianStemmer;
        $this->assertEquals("cRveno", $stemmer->istakniSlogotvornoR("crveno"));
        $this->assertEquals("tvRdo", $stemmer->istakniSlogotvornoR("tvrdo"));
        $this->assertEquals("vRt", $stemmer->istakniSlogotvornoR("vrt"));
    }

    public function testImaSamoglasnik()
    {
        $stemmer = new CroatianStemmer;
        $this->assertTrue($stemmer->imaSamoglasnik("test"));
        $this->assertTrue($stemmer->imaSamoglasnik("vrt"));
        $this->assertFalse($stemmer->imaSamoglasnik("dgk"));
    }

    public function testTransformiraj()
    {
        $stemmer = new CroatianStemmer;
        $this->assertEquals("ginekologa", $stemmer->transformiraj("ginekolozi"));
        $this->assertEquals("ujak", $stemmer->transformiraj("ujaci"));
        $this->assertEquals("policajca", $stemmer->transformiraj("policajaca"));
    }

    public function testKorjenuj()
    {
        $stemmer = new CroatianStemmer;
        $this->assertEquals("njem", $stemmer->korjenuj("njemu"));
        $this->assertEquals("stisk", $stemmer->korjenuj("stiska"));
        $this->assertEquals("jasn", $stemmer->korjenuj("jasno"));
        $this->assertEquals("kalibr", $stemmer->korjenuj("kalibra"));
        $this->assertEquals("zagrijavanj", $stemmer->korjenuj("zagrijavanje"));
        $this->assertEquals("biznis", $stemmer->korjenuj("biznisom"));
        $this->assertEquals("razgovara", $stemmer->korjenuj("razgovarati"));
        $this->assertEquals("najbogat", $stemmer->korjenuj("najbogatijih"));
    }

    public function testStem()
    {
        $stemmer = new CroatianStemmer;
        $this->assertEquals("biti", $stemmer->stem("biti"));
        $this->assertEquals("njem", $stemmer->stem("njemu"));
        $this->assertEquals("stisk", $stemmer->stem("stiska"));
        $this->assertEquals("jasn", $stemmer->stem("jasno"));
        $this->assertEquals("kalibr", $stemmer->stem("kalibra"));
        $this->assertEquals("zagrijavanj", $stemmer->stem("zagrijavanje"));
        $this->assertEquals("biznis", $stemmer->stem("biznisom"));
        $this->assertEquals("razgovara", $stemmer->stem("razgovarati"));
        $this->assertEquals("najbogat", $stemmer->stem("najbogatijih"));

        $this->assertEquals("čćžšđ", $stemmer->stem("čćžšđ"));
    }
}
