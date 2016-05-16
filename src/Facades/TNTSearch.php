<?php

namespace TeamTNT\TNTSearch\Facades;

use Illuminate\Support\Facades\Facade;

class TNTSearch extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'tntsearch';
    }
}
