<?php

use TeamTNT\TNTSearch\Stemmer\FrenchStemmer;

class FrenchStemmerTest extends PHPUnit\Framework\TestCase
{
    public function testStem()
    {
        $this->assertSame('abaiss', FrenchStemmer::stem('abaissant'));
        $this->assertSame('abandon', FrenchStemmer::stem('abandonnés'));
        $this->assertSame(FrenchStemmer::stem('frontières'), FrenchStemmer::stem('frontière'));
    }
}
