<?php

use TeamTNT\TNTSearch\Support\NGramTokenizer;

class NGramTokenizerTest extends PHPUnit\Framework\TestCase
{
    public function testTrigramTokenize()
    {
        $tokenizer = new NGramTokenizer(3, 3);

        $text = "Quick Foxes";
        $res  = $tokenizer->tokenize($text);

        $this->assertEquals(["Qui", "uic", "ick", "Fox", "oxe", "xes"], $res);
    }

    public function testNgram12Tokenize()
    {
        $tokenizer = new NGramTokenizer(1, 2);

        $text = "Quick Fox";
        $res  = $tokenizer->tokenize($text);

        $this->assertEquals(["Q", "u", "i", "c", "k", "Qu", "ui", "ic", "ck", "F", "o", "x", "Fo", "ox"], $res);
    }

    public function testFourGramTokenize()
    {
        $tokenizer = new NGramTokenizer(4, 4);

        $text = "Quick Foxes";
        $res  = $tokenizer->tokenize($text);

        $this->assertEquals(["Quic", "uick", "Foxe", "oxes"], $res);
    }

}
