<?php

namespace TeamTNT\TNTSearch\Support;

class Expression
{
    protected $operatorQueue;
    protected $tokenQueue;

    public function toPostfix($exp)
    {
        $postfix = [];
        $stack   = [];

        $tokens = $this->lex($exp);
        foreach ($tokens as $token) {
            if ($this->isOperand($token)) {
                $postfix[] = $token;
            } else {
                if ($token == ")") {
                    while (($top = array_pop($stack)) != "(" && !empty($top)) {
                        $postfix[] = $top;
                    }

                } else {
                    while (
                        count($stack) && !(end($stack) == "(") &&
                        ($this->priority(end($stack)) >= $this->priority($token))
                    ) {
                        $postfix[] = array_pop($stack);
                    }
                    $stack[] = $token;
                }
            }
        }
        while (!empty($stack)) {
            $postfix[] = array_pop($stack);
        }

        return $postfix;
    }

    public function isOperand($str)
    {

        if (
            ($str == "|") || ($str == "&") || ($str == "~") ||
            ($str == "(") || ($str == ")")
        ) {
            return false;
        }

        return true;

    }

    public function isOperator($str)
    {
        return !$this->isOperand($str);
    }

    public function priority($operator)
    {

        $priority = 0;

        if ($operator == ("&")) {
            $priority = 2;
        }

        if ($operator == "~") {
            $priority = 3;
        }

        if ($operator == "|") {
            $priority = 1;
        }

        if ($operator == "(" || $operator == ")") {
            $priority = 4;
        }

        return $priority;

    }

    public function lex($string)
    {
        $bad  = [' or ', ' -', ' '];
        $good = ['|', '~', '&'];

        $string = str_replace($bad, $good, $string);
        $string = mb_strtolower($string);
        
        $tokens = [];
        $token  = "";
        foreach (str_split($string) as $char) {

            if ($this->isOperator($char)) {
                if ($token) {
                    $tokens[] = $token;
                }

                $tokens[] = $char;
                $token    = "";
            } else {
                $token .= $char;
            }
        }
        if ($token) {
            $tokens[] = $token;
        }

        return $tokens;
    }
}
