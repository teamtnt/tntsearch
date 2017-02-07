<?php

namespace TeamTNT\TNTSearch;

class TNTGeoSearch extends TNTSearch
{
    /**
     * Distance is in KM
     */
    public function findNearest($currentLocation, $distance)
    {
        $startTimer = microtime(true);

        //do some math

        $stopTimer = microtime(true);

        return [
            'ids'            => [1,2,3],
            'hits'           => 200,
            'execution time' => round($stopTimer - $startTimer, 7) * 1000 ." ms"
        ];
    }
}