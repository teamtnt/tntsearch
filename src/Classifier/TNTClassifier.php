<?php

namespace TeamTNT\TNTSearch\Classifier;

use TeamTNT\TNTSearch\Stemmer\NoStemmer;
use TeamTNT\TNTSearch\Support\Tokenizer;

class TNTClassifier
{
    public $documents              = [];
    public $words                  = [];
    public $types                  = [];
    public $vc                     = null;
    public $tokenizer              = null;
    public $stemmer                = null;
    protected $arraySumOfWordType  = null;
    protected $arraySumOfDocuments = null;

    public function __construct()
    {
        $this->tokenizer = new Tokenizer;
        $this->stemmer   = new NoStemmer;
    }

    public function predict($statement)
    {
        $words = $this->tokenizer->tokenize($statement);

        $best_likelihood = -INF;
        $best_type       = '';
        foreach ($this->types as $type) {
            $likelihood = log($this->pTotal($type)); // calculate P(Type)
            $p          = 0;
            foreach ($words as $word) {
                $word = $this->stemmer->stem($word);
                $p += log($this->p($word, $type));
            }
            $likelihood += $p; // calculate P(word, Type)
            if ($likelihood > $best_likelihood) {
                $best_likelihood = $likelihood;
                $best_type       = $type;
            }
        }
        return [
            'likelihood' => $best_likelihood,
            'label'      => $best_type
        ];
    }

    public function learn($statement, $type)
    {
        if (!in_array($type, $this->types)) {
            $this->types[] = $type;
        }

        $words = $this->tokenizer->tokenize($statement);

        foreach ($words as $word) {
            $word = $this->stemmer->stem($word);

            if (!isset($this->words[$type][$word])) {
                $this->words[$type][$word] = 0;
            }
            $this->words[$type][$word]++; // increment the word count for the type
        }
        if (!isset($this->documents[$type])) {
            $this->documents[$type] = 0;
        }

        $this->documents[$type]++; // increment the document count for the type
    }

    public function p($word, $type)
    {
        $count = 0;
        if (isset($this->words[$type][$word])) {
            $count = $this->words[$type][$word];
        }

        if (!isset($this->arraySumOfWordType[$type])) {
            $this->arraySumOfWordType[$type] = array_sum($this->words[$type]);
        }

        return ($count + 1) / ($this->arraySumOfWordType[$type] + $this->vocabularyCount());
    }

    public function pTotal($type)
    {
        if (!isset($this->arraySumOfDocuments)) {
            $this->arraySumOfDocuments = array_sum($this->documents);
        }
        return ($this->documents[$type]) / $this->arraySumOfDocuments;
    }

    public function vocabularyCount()
    {
        if (isset($this->vc)) {
            return $this->vc;
        }

        $words = [];
        foreach ($this->words as $key => $value) {
            foreach ($this->words[$key] as $word => $count) {
                $words[$word] = 0;
            }
        }
        $this->vc = count($words);
        return $this->vc;
    }

    public function save($path)
    {
        $s = serialize($this);
        return file_put_contents($path, $s);
    }

    public function load($name)
    {
        $s          = file_get_contents($name);
        $classifier = unserialize($s);

        unset($this->vc);
        unset($this->arraySumOfDocuments);
        unset($this->arraySumOfWordType);

        $this->documents = $classifier->documents;
        $this->words     = $classifier->words;
        $this->types     = $classifier->types;
        $this->tokenizer = $classifier->tokenizer;
        $this->stemmer   = $classifier->stemmer;
    }
}
