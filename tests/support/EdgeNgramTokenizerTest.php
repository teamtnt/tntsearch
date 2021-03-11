<?php

use TeamTNT\TNTSearch\Support\EdgeNgramTokenizer;

class EdgeNgramTokenizerTest extends PHPUnit\Framework\TestCase
{
    public function testEdgeNgramTokenize()
    {
        $tokenizer = new EdgeNgramTokenizer;

        $text = "Quick Foxes";
        $res  = $tokenizer->tokenize($text);

        $this->assertEquals(["Qu", "Qui", "Quic", "Quick", "Fo", "Fox", "Foxe", "Foxes"], $res);
    }
}
