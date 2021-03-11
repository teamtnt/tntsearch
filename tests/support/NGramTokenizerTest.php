<?php

use TeamTNT\TNTSearch\Support\NGramTokenizer;

class NGramTokenizerTest extends PHPUnit\Framework\TestCase
{
    public function testTrigramTokenize()
    {
        $tokenizer = new NGramTokenizer(3, 3);

        $text = "Quick Foxes";
        $res  = $tokenizer->tokenize($text);

        $this->assertEquals(["qui", "uic", "ick", "fox", "oxe", "xes"], $res);
    }

    public function testNgram12Tokenize()
    {
        $tokenizer = new NGramTokenizer(1, 2);

        $text = "Quick Fox";
        $res  = $tokenizer->tokenize($text);

        $this->assertEquals(["q", "u", "i", "c", "k", "qu", "ui", "ic", "ck", "f", "o", "x", "fo", "ox"], $res);
    }

    public function testFourGramTokenize()
    {
        $tokenizer = new NGramTokenizer(4, 4);

        $text = "Quick Foxes";
        $res  = $tokenizer->tokenize($text);

        $this->assertEquals(["quic", "uick", "foxe", "oxes"], $res);
    }

}
