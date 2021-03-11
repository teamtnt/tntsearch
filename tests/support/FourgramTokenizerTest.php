<?php

use TeamTNT\TNTSearch\Support\FourgramTokenizer;

class FourgramTokenizerTest extends PHPUnit\Framework\TestCase
{
    public function testFourgramTokenize()
    {
        $tokenizer = new FourgramTokenizer;

        $text = "Quick Foxes";
        $res  = $tokenizer->tokenize($text);

        $this->assertEquals(["Quic", "uick", "Foxe", "oxes"], $res);
    }
}
