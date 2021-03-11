<?php

use TeamTNT\TNTSearch\Support\TrigramTokenizer;

class TrigramTokenizerTest extends PHPUnit\Framework\TestCase
{
    public function testTrigramTokenize()
    {
        $tokenizer = new TrigramTokenizer;

        $text = "Quick Foxes";
        $res  = $tokenizer->tokenize($text);

        $this->assertEquals(["qui", "uic", "ick", "fox", "oxe", "xes"], $res);
    }
}
