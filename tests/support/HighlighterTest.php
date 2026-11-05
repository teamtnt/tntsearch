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

    // Issue #370: a match near the end of the text, with an odd number of
    // characters after the snippet start, gave a float start position. This
    // caused a deprecation notice in mb_substr() on PHP 8.1+.
    public function testExtractRelevantMatchNearEnd()
    {
        $hl       = new Highlighter;
        $fulltext = str_repeat("bla ", 45) . "needle ends";

        $deprecations = [];
        set_error_handler(function ($errno, $errstr) use (&$deprecations) {
            $deprecations[] = $errstr;
            return true;
        }, E_DEPRECATED);
        try {
            $res = $hl->extractRelevant("needle", $fulltext, 100);
        } finally {
            restore_error_handler();
        }

        $this->assertEquals([], $deprecations);
        $this->assertEquals("..." . str_repeat("bla ", 20) . "needle ends", $res);
    }

    // A long text with multibyte characters before and after the match. The
    // expected snippet is what the mb_stripos() implementation returned.
    public function testExtractRelevantLongText()
    {
        $hl       = new Highlighter;
        $fulltext = str_repeat("Lorem ípsum dolor sit ämet, consectetur adipiscing élit. ", 1000) .
            "The search needle is somewhere in this sentence. " .
            str_repeat("Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. ", 300) .
            "Another needle near the end.";

        $res = $hl->extractRelevant('search needle', $fulltext, 100);
        $this->assertEquals(
            "...ämet, consectetur adipiscing élit. The search needle is somewhere in this sentence. Sed do...",
            $res
        );
    }

    public function testExtractLocationsReturnsCharacterOffsets()
    {
        $hl = new Highlighter;

        $locations = $hl->_extractLocations(['text'], "Café naïve 🎉 text and 文字 text");
        $this->assertEquals([13, 25], $locations);
    }

    public function testExtractLocationsIgnoresCase()
    {
        $hl = new Highlighter;

        $locations = $hl->_extractLocations(['été', 'text'], "ÉTÉ Été été TEXT Text");
        $this->assertEquals([0, 4, 8, 12, 17], $locations);
    }

    // The expected values are what the mb_stripos() implementation returned:
    // "ß" does not match "SS", and the Turkish "İ" and "ı" do not match "i" or "I".
    public function testExtractLocationsSpecialCaseFolding()
    {
        $hl = new Highlighter;

        $this->assertEquals([11], $hl->_extractLocations(['ss'], "Straße STRASSE"));
        $this->assertEquals([4], $hl->_extractLocations(['ß'], "Straße STRASSE"));
        $this->assertEquals([10, 12], $hl->_extractLocations(['i'], "İstanbul ıi I"));
        $this->assertEquals([9], $hl->_extractLocations(['ı'], "İstanbul ıi I"));
    }

    public function testExtractLocationsMultipleWords()
    {
        $hl = new Highlighter;

        // "la" overlaps every "bla", and "bla" is given twice
        $locations = $hl->_extractLocations(['bla', 'la', 'bla'], "bla blabla");
        $this->assertEquals([0, 1, 4, 5, 7, 8], $locations);
    }

    // The regex cannot search invalid UTF-8, so the mb_stripos() search is used
    public function testExtractLocationsInvalidUtf8()
    {
        $hl = new Highlighter;

        $locations = $hl->_extractLocations(['text'], "caf\xC3\xA9 \xFF\xFE text \xC3 TEXT");
        $this->assertEquals([8, 15], $locations);

        $locations = $hl->_extractLocations(["t\xFF"], "caf\xC3\xA9 t\xFF text");
        $this->assertEquals([5], $locations);
    }

    // PCRE cannot compile a very long word, so the mb_stripos() search is used
    // without a warning. PHPUnit turns warnings into errors.
    public function testExtractLocationsVeryLongWord()
    {
        $hl   = new Highlighter;
        $word = str_repeat("a", 70000);

        $this->assertEquals([2], $hl->_extractLocations([$word], "x " . $word . " y"));
    }

    // The regex is UTF-8 only, so the mb_stripos() search is used
    public function testExtractLocationsOtherInternalEncoding()
    {
        $hl       = new Highlighter;
        $encoding = mb_internal_encoding();
        mb_internal_encoding('ISO-8859-1');
        try {
            $locations = $hl->_extractLocations(['text'], "café text");
        } finally {
            mb_internal_encoding($encoding);
        }

        // "é" is two bytes, so two ISO-8859-1 characters
        $this->assertEquals([6], $locations);
    }

    public function testExtractLocationsEmptyWord()
    {
        $hl = new Highlighter;

        $this->assertEquals([5], $hl->_extractLocations(['', 'text'], "some text"));
        $this->assertEquals([], $hl->_extractLocations([''], "some text"));
        $this->assertEquals([7], $hl->_extractLocations(['0'], "number 0"));
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
