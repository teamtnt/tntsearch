<?php

use TeamTNT\TNTSearch\TNTGeoSearch;

class TNTGeoSearchTest extends PHPUnit_Framework_TestCase
{
    protected $indexName = "cities-geo.index";

    protected $config = [
        'storage' => __DIR__.'/_files/'
    ];

    /**
     * If we're located in Munich, lets find 2 nearest cities around 50km
     */
    public function testFindNearest()
    {
        $currentLocation = [
            'longitude' => 11.576124,
            'latitude'  => 48.137154
        ];

        $distance = 50; //km

        $citiesIndex = new TNTGeoSearch();
        $citiesIndex->loadConfig($this->config);
        $citiesIndex->selectIndex($this->indexName);

        $cities = $citiesIndex->findNearest($currentLocation, $distance, 2);

        $this->assertEquals([9389, 9407], $cities['ids']);
        $this->assertEquals(2, $cities['hits']);
    }
}
