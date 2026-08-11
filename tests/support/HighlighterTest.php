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

    // Issue #274: highlighting term by term re-scanned the markup injected for
    // earlier terms, so a later term could match an attribute of an already
    // inserted tag and highlight it too. Each word must be wrapped exactly once.
    public function testDoesNotHighlightItsOwnMarkup()
    {
        $hl   = new Highlighter;
        $text = "the price and the weight matter";

        $output = $hl->highlight($text, "price weight", 'span', [
            'simple'     => true,
            'tagOptions' => ['class' => 'price-weight'],
        ]);

        // Exactly two opening tags — the injected "price-weight" class must not
        // itself be highlighted by the second term.
        $this->assertEquals(2, substr_count($output, '<span'));
    }
}
