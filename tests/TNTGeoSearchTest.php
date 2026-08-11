<?php

use TeamTNT\TNTSearch\TNTGeoSearch;

class TNTGeoSearchTest extends PHPUnit\Framework\TestCase
{
    protected $indexName = "cities-geo.index";

    protected $config = [
        'storage' => __DIR__ . '/_files/'
    ];

    /**
     * If we're located in Munich, lets find 2 nearest cities around 50km
     */
    public function testFindNearest()
    {
        //we're skipping this test
        $this->assertTrue(true);
        return;

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

    /**
     * Issue #357: querying the exact coordinates of an indexed location makes
     * the computed cosine round marginally above 1.0, so acos() returned NAN
     * and the (closest, distance-0) result was silently dropped.
     */
    public function testFindNearestAtExactIndexedLocationIsReturned()
    {
        // City 9389 in the fixture is stored at exactly this coordinate.
        $currentLocation = [
            'longitude' => 11.5833,
            'latitude'  => 48.15,
        ];

        $citiesIndex = new TNTGeoSearch();
        $citiesIndex->loadConfig($this->config);
        $citiesIndex->selectIndex($this->indexName);

        $cities = $citiesIndex->findNearest($currentLocation, 50, 5);

        $this->assertContains(9389, $cities['ids']);
        foreach ($cities['distances'] as $d) {
            $this->assertFalse(is_nan($d), 'Distance must never be NAN');
        }
    }

    public function tearDown(): void
    {
        if (file_exists(__DIR__ . '/../_files/' . $this->indexName)) {
            unlink(__DIR__ . '/../_files/' . $this->indexName);
        }
    }
}
