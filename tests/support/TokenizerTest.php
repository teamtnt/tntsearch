<?php

use TeamTNT\TNTSearch\Support\Tokenizer;

class TokenizerTest extends PHPUnit\Framework\TestCase
{
    public function testTokenize()
    {
        $tokenizer = new Tokenizer;

        $text = "This is some text";
        $res  = $tokenizer->tokenize($text);

        $this->assertContains("this", $res);
        $this->assertContains("text", $res);

        $text = "123 123 123";
        $res  = $tokenizer->tokenize($text);
        $this->assertContains("123", $res);

        $text = "Hi! This text contains an test@email.com. Test's email 123.";
        $res  = $tokenizer->tokenize($text);
        $this->assertContains("test", $res);
        $this->assertContains("email", $res);
        $this->assertContains("test@email", $res);
        $this->assertContains("contains", $res);
        $this->assertContains("123", $res);

        $text = "Superman (1941)";
        $res  = $tokenizer->tokenize($text);
        $this->assertContains("superman", $res);
        $this->assertContains("1941", $res);

        $text = "čćž šđ";
        $res  = $tokenizer->tokenize($text);
        $this->assertContains("čćž", $res);
        $this->assertContains("šđ", $res);
    }
}
