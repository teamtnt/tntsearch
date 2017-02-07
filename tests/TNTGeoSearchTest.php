<?php

use TeamTNT\TNTSearch\Exceptions\IndexNotFoundException;
use TeamTNT\TNTSearch\TNTGeoSearch;

class TNTGeoSearchTest extends PHPUnit_Framework_TestCase
{
    protected $indexName = "cities-geo.index";

    protected $config = [
        'storage'  => __DIR__ . '/_files/',
    ];

    /**
     * If we're located in Munich, lets find all the cities around 50km
     */
    public function testFindNearest()
    {
        $currentLocation = [
            'longitude' => 11.576124,
            'latitude'  => 48.137154
        ];

        $distance = 50; //km

        $citiesIndex = new TNTGeoSearch();
        $cities = $citiesIndex->findNearest($currentLocation, $distance);

         $this->assertEquals([1,2,3], $cities['ids']);
    }
}
