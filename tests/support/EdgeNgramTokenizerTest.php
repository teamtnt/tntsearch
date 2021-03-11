<?php

use TeamTNT\TNTSearch\Support\EdgeNgramTokenizer;

class EdgeNgramTokenizerTest extends PHPUnit\Framework\TestCase
{
    public function testEdgeNgramTokenize()
    {
        $tokenizer = new EdgeNgramTokenizer;

        $text = "Quick Foxes";
        $res  = $tokenizer->tokenize($text);

        $this->assertEquals(["qu", "qui", "quic", "quick", "fo", "fox", "foxe", "foxes"], $res);
    }

    public function testEdgeNgramTokenize2()
    {
        $tokenizer = new EdgeNgramTokenizer;

        $text = "Germany";
        $res  = $tokenizer->tokenize($text);

        $this->assertEquals(["ge", "ger", "germ", "germa", "german", "germany"], $res);
    }
}
