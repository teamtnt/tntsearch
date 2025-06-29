<?php

namespace tokenizer;

use PHPUnit\Framework\TestCase;
use TeamTNT\TNTSearch\Tokenizer\TrigramTokenizer;

class TrigramTokenizerTest extends TestCase
{
    public function testTrigramTokenize()
    {
        $tokenizer = new TrigramTokenizer;

        $text = "Quick Foxes";
        $res = $tokenizer->tokenize($text);

        $this->assertEquals(["qui", "uic", "ick", "fox", "oxe", "xes"], $res);
    }
}
