<?php

namespace tokenizer;

use PHPUnit;
use TeamTNT\TNTSearch\Tokenizer\FourgramTokenizer;

class FourgramTokenizerTest extends PHPUnit\Framework\TestCase
{
    public function testFourgramTokenize()
    {
        $tokenizer = new FourgramTokenizer;

        $text = "Quick Foxes";
        $res = $tokenizer->tokenize($text);

        $this->assertEquals(["quic", "uick", "foxe", "oxes"], $res);
    }
}
