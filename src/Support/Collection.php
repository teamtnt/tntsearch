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

    public function forget($key)
    {
        unset($this->items[$key]);
    }

    /**
     * @param callable $callback
     *
     * @return Collection
     */
    public function each(callable $callback)
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * @param callable|null $callback
     *
     * @return static
     */
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

    /**
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->items);
    }

    /**
     * @param callable $callback
     *
     * @return static
     */
    public function map(callable $callback)
    {
        $keys = array_keys($this->items);

        $items = array_map($callback, $this->items, $keys);

        return new static(array_combine($keys, $items));
    }

    /**
     * @param callable $callback
     * @param null     $initial
     *
     * @return mixed
     */
    public function reduce(callable $callback, $initial = null)
    {
        return array_reduce($this->items, $callback, $initial);
    }

    public function get($key)
    {
        return $this->items[$key];
    }

    /**
     * @param      $value
     * @param null $key
     *
     * @return array
     */
    public function pluck($value, $key = null)
    {
        return array_column($this->items, $value, $key);
    }

    /**
     * @param $glue
     *
     * @return string
     */
    public function implode($glue)
    {
        return implode($glue, $this->items);
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->items);
    }

    /**
     * @param int $offset
     * @param int $length
     *
     * @return static
     */
    public function slice($offset, $length = null)
    {
        return new static(array_slice($this->items, $offset, $length, true));
    }

    /**
     * @param int $limit
     * @return static
     */
    public function take($limit)
    {
        return $this->slice(0, abs($limit));
    }

    /**
     * @return ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->items;
    }
}
