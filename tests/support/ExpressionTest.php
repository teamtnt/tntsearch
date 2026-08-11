<?php

use TeamTNT\TNTSearch\Support\Expression;

class ExpressionTest extends PHPUnit\Framework\TestCase
{
    public function testToPostfix()
    {
        $exp = new Expression;
        $this->assertEquals(['a', 'b', '&', 'c', '|'], $exp->toPostfix("a&b|c"));
        $this->assertEquals(['aw', 'bw', '&', 'cw', '|'], $exp->toPostfix("aw&bw|cw"));
        $this->assertEquals(['aw', 'bw', '&', 'cw', '|'], $exp->toPostfix("aw&bw|cw)"));
        $this->assertEquals(['a', 'b', 'd', 'c', '&', '|', '&'], $exp->toPostfix("a&(b|d&c)"));
        $this->assertEquals(['a', 'b', '|'], $exp->toPostfix("a|b"));
        $this->assertEquals(['great', 'awsome', '|'], $exp->toPostfix("great|awsome"));
        $this->assertEquals(['great', 'awsome', '|'], $exp->toPostfix("great or awsome"));
        $this->assertEquals(['great', 'awsome', '&'], $exp->toPostfix("great awsome"));
        $this->assertEquals(['email', 'test', '&', 'com', '&'], $exp->toPostfix("email test com"));
        $this->assertEquals(['first', 'last', '&', 'something', 'else', '&', '|'], $exp->toPostfix("(first last) or (something else)"));
        $this->assertEquals(['first', 'last', '|', 'something', 'else', '|', '&'], $exp->toPostfix("(first or last)&(something or else)"));
        $this->assertEquals(['first', 'last', '|', 'something', '&', 'else', '|'], $exp->toPostfix("(first or last)&something or else)"));
    }

    // Issues #263 / #246 / #267: the " -term" (dash) negation must behave like
    // the "~term" (tilde) negation — AND-NOT — instead of dropping the implicit
    // AND and turning into OR-NOT (which also crashed on two negations).
    public function testDashNegationBehavesLikeTildeNegation()
    {
        $exp = new Expression;

        $this->assertEquals($exp->toPostfix("foo ~bar"), $exp->toPostfix("foo -bar"));
        $this->assertEquals(['foo', 'bar', '~', '&'], $exp->toPostfix("foo -bar"));

        // Two negations must not leave a dangling operand on the stack.
        $this->assertEquals(
            ['foo', 'bar', '~', '&', 'alpha', '~', '&'],
            $exp->toPostfix("foo -bar -alpha")
        );
    }
}
