<?php

namespace tokenizer;

use PHPUnit\Framework\TestCase;
use TeamTNT\TNTSearch\Tokenizer\FourgramTokenizer;

class FourgramTokenizerTest extends TestCase
{
    public function testFourgramTokenize()
    {
        $tokenizer = new FourgramTokenizer;

        $text = "Quick Foxes";
        $res = $tokenizer->tokenize($text);

        $this->assertEquals(["quic", "uick", "foxe", "oxes"], $res);
    }
}
