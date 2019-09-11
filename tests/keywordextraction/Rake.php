<?php

use TeamTNT\TNTSearch\KeywordExtraction\Rake;

class RakeTest extends PHPUnit\Framework\TestCase
{

    public function __construct()
    {
        $this->rake = new Rake;
        parent::__construct();
    }

    public function testExtractKeywords()
    {
        $text   = "A scoop of ice cream";
        $actual = $this->rake->extractKeywords($text);

        $expected = ["ice cream" => 4, "scoop" => 1];
        $this->assertEquals($expected, $actual);
    }

    public function testExtractKeywords2()
    {
        $text = "Compatibility of systems of linear constraints over the set of natural
                numbers. Criteria of compatibility of a system of linear Diophantine
                equations, strict inequations, and nonstrict inequations are considered.
                Upper bounds for components of a minimal set of solutions and algorithms
                of construction of minimal generating sets of solutions for all types of
                systems are given. These criteria and the corresponding algorithms for
                constructing a minimal supporting set of solutions can be used in solving
                all the considered types of systems and systems of mixed types.";

        $actual = $this->rake->extractKeywords($text);

        $this->assertEquals(8.666666666666666, $actual["minimal generating sets"], '', 0.0001);
        $this->assertEquals(8.5, $actual["linear diophantine equations"], '', 0.0001);
        $this->assertEquals(7.666666666666666, $actual["minimal supporting set"], '', 0.0001);
    }

    public function testTokenize()
    {
        $expected = ["a", "scoop", "of", "ice", "cream"];
        $actual   = $this->rake->tokenize('A scoop of ice cream');
        $this->assertEquals($expected, $actual);
    }

    public function testGenerateCandidateKeywords()
    {
        $text     = "A scoop of ice cream";
        $expected = [["scoop"], ["ice", "cream"]];
        $actual   = $this->rake->generateCandidateKeywords($text);
        $this->assertEquals($expected, $actual);
    }

    public function testWordDegree()
    {
        $text       = "A scoop of ice cream";
        $phraseList = $this->rake->generateCandidateKeywords($text);

        $degree = $this->rake->wordDegree("scoop", $phraseList);
        $this->assertEquals($degree, 1);

        $degree = $this->rake->wordDegree("ice", $phraseList);
        $this->assertEquals($degree, 2);

        $degree = $this->rake->wordDegree("cream", $phraseList);
        $this->assertEquals($degree, 2);

    }

    public function testWordDegree2()
    {
        $text = "Compatibility of systems of linear constraints over the set of natural numbers of Criteria of compatibility of a system of linear Diophantine equations, strict inequations, and nonstrict inequations are considered. Upper bounds for components of a minimal set of solutions and algorithms of construction of minimal generating sets of solutions for all types of systems are given. These criteria and the corresponding algorithms for constructing a minimal supporting set of solutions can be used in solving all the considered types of systems and systems of mixed types.";

        $phraseList = $this->rake->generateCandidateKeywords($text);

        $degree = $this->rake->wordDegree("set", $phraseList);

        $this->assertEquals(6, $degree);

        $degree = $this->rake->wordDegree("natural", $phraseList);
        $this->assertEquals(2, $degree);
    }

    public function testWordFrequency()
    {
        $text = "Compatibility of systems of linear constraints over the set of natural numbers of Criteria of compatibility of a system of linear Diophantine equations, strict inequations, and nonstrict inequations are considered. Upper bounds for components of a minimal set of solutions and algorithms of construction of minimal generating sets of solutions for all types of systems are given. These criteria and the corresponding algorithms for constructing a minimal supporting set of solutions can be used in solving all the considered types of systems and systems of mixed types.";

        $phraseList = $this->rake->generateCandidateKeywords($text);

        $frequency = $this->rake->wordFrequency("systems", $phraseList);

        $this->assertEquals(4, $frequency);
    }

    public function testCalculateWordScores()
    {
        $text     = "A scoop of ice cream";
        $expected = ["scoop" => 1, "ice" => 2, "cream" => 2];

        $phraseList = $this->rake->generateCandidateKeywords($text);

        $actual = $this->rake->calculateWordScores($phraseList);
        $this->assertEquals($expected, $actual);
    }
}
