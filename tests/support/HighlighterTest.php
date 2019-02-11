<?php

use TeamTNT\TNTSearch\Support\Highlighter;

class HighlighterTest extends PHPUnit\Framework\TestCase
{
    public function testHighlight()
    {
        $hl     = new Highlighter;
        $text   = "This is some text";
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

    public function testExtractRelevant()
    {
        $hl       = new Highlighter;
        $words    = "This is some text";
        $fulltext = "bla bla bla bla bla bla bla bla bla bla bla bla bla bla bla bla bla ".
            "bla bla bla This is a sentence that contains the phrase This is some text and ".
            "thats it bla bla bla bla bla bla bla bla bla bla bla bla bla bla bla bla ".
            "bla bla bla bla bla bla bla bla bla bla bla bla bla bla bla bla bla bla bla ".
            "bla bla bla bla bla bla bla bla bla bla bla bla bla bla bla bla bla bla bla bla ";
        $res = $hl->extractRelevant($words, $fulltext, 100);
        $this->assertEquals("...bla This is a sentence that contains the phrase This is some text and thats it bla bla bla bla...", $res);
    }
}
