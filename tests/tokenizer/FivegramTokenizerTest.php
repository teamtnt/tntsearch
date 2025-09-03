<?php

namespace tokenizer;

use PHPUnit\Framework\TestCase;
use TeamTNT\TNTSearch\Tokenizer\FivegramTokenizer;

class FivegramTokenizerTest extends TestCase
{
    public function testFourgramTokenize()
    {
        $tokenizer = new FivegramTokenizer;

        $text = "Quick Foxes";
        $res = $tokenizer->tokenize($text);

        $this->assertEquals(["quick", "foxes"], $res);
    }
}
