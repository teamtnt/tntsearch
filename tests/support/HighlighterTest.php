<?php

use TeamTNT\Support\Hihglighter;

class HighlighterTest extends PHPUnit_Framework_TestCase
{
    public function testHighlight()
    {
        $hl = new Hihglighter;
        $text = "This is some text";
        $output = $hl->highlight($text, "is text", 'em', ['wholeWord' => false]);
        $this->assertEquals("Th<em>is</em> <em>is</em> some <em>text</em>", $output);

        $output = $hl->highlight($text, "is text", 'em', ['wholeWord' => true]);
        $this->assertEquals("This <em>is</em> some <em>text</em>", $output);

        $output = $hl->highlight($text, "this text", 'em', ['caseSensitive' => true]);
        $this->assertEquals("This is some <em>text</em>", $output);

        $output = $hl->highlight($text, "this text", 'em', ['caseSensitive' => false]);
        $this->assertEquals("<em>This</em> is some <em>text</em>", $output);

        $output = $hl->highlight($text, "text", 'em');
        $this->assertEquals("This is some <em>text</em>", $output);

        $output = $hl->highlight($text, "text", 'b');
        $this->assertEquals("This is some <b>text</b>", $output);
    }
}