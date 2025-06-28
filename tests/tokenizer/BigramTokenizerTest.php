<?php

namespace tokenizer;

use PHPUnit\Framework\TestCase;
use TeamTNT\TNTSearch\Tokenizer\BigramTokenizer;

class BigramTokenizerTest extends TestCase
{
    public function testBigramTokenize()
    {
        $tokenizer = new BigramTokenizer;

        $text = "Quick Foxes";
        $res = $tokenizer->tokenize($text);

        $this->assertEquals(["qu", "ui", "ic", "ck", "fo", "ox", "xe", "es"], $res);
    }
}
