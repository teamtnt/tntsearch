<?php

use TeamTNT\TNTSearch\Support\BigramTokenizer;

class BigramTokenizerTest extends PHPUnit\Framework\TestCase
{
    public function testBigramTokenize()
    {
        $tokenizer = new BigramTokenizer;

        $text = "Quick Foxes";
        $res  = $tokenizer->tokenize($text);

        $this->assertEquals(["qu", "ui", "ic", "ck", "fo", "ox", "xe", "es"], $res);
    }
}
