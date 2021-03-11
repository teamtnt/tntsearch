<?php

use TeamTNT\TNTSearch\Support\FivegramTokenizer;

class FivegramTokenizerTest extends PHPUnit\Framework\TestCase
{
    public function testFourgramTokenize()
    {
        $tokenizer = new FivegramTokenizer;

        $text = "Quick Foxes";
        $res  = $tokenizer->tokenize($text);

        $this->assertEquals(["quick", "foxes"], $res);
    }
}
