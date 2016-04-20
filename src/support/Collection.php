<?php

namespace TeamTNT\TNTSearch\Support;

use ArrayIterator;
use Countable;
use IteratorAggregate;

class Collection implements Countable, IteratorAggregate
{

    protected $items = [];

    public function __construct($items = [])
    {
        $this->items = $items;
    }

    public function each(callable $callback)
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    public function filter(callable $callback = null)
    {
        if ($callback) {
            $return = [];

            foreach ($this->items as $key => $value) {
                if ($callback($value, $key)) {
                    $return[$key] = $value;
                }
            }

            return new static($return);
        }

        return new static(array_filter($this->items));
    }

    public function isEmpty()
    {
        return empty($this->items);
    }

    public function map(callable $callback)
    {
        $keys = array_keys($this->items);

        $items = array_map($callback, $this->items, $keys);

        return new static(array_combine($keys, $items));
    }

    public function reduce(callable $callback, $initial = null)
    {
        return array_reduce($this->items, $callback, $initial);
    }

    public function get($key)
    {
        return $this->items[$key];
    }

    public function pluck($value, $key = null)
    {
        return array_column($this->items, $value, $key);
    }

    public function implode($glue)
    {
        return implode($glue, $this->items);
    }

    public function count()
    {
        return count($this->items);
    }

    public function getIterator()
    {
        return new ArrayIterator($this->items);
    }

    public function toArray()
    {
        return $this->items;
    }
}
