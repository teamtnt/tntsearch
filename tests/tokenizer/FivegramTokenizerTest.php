<?php

namespace tokenizer;

use PHPUnit;
use TeamTNT\TNTSearch\Tokenizer\FivegramTokenizer;

class FivegramTokenizerTest extends PHPUnit\Framework\TestCase
{
    public function testFourgramTokenize()
    {
        $tokenizer = new FivegramTokenizer;

        $text = "Quick Foxes";
        $res = $tokenizer->tokenize($text);

        $this->assertEquals(["quick", "foxes"], $res);
    }
}
