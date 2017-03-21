<?php

namespace TeamTNT\TNTSearch;

use PDO;
use TeamTNT\TNTSearch\Support\Collection;

class TNTGeoSearch extends TNTSearch
{
    protected $earthRadius = 6371;
    /**
     * Distance is in KM
     */
    public function findNearest($currentLocation, $distance, $limit = 10)
    {
        $startTimer = microtime(true);

        $res = $this->buildQuery($currentLocation, $distance, $limit);

        $stopTimer = microtime(true);

        return [
            'ids'            => $res->pluck('doc_id'),
            'distances'      => $res->pluck('distance'),
            'hits'           => $res->count(),
            'execution_time' => round($stopTimer - $startTimer, 7) * 1000 ." ms"
        ];
    }

    public function buildQuery($currentLocation, $distance, $limit)
    {

        $query = "
            SELECT doc_id, longitude, latitude,
            :CUR_sin_lat * sin_lat + :CUR_cos_lat * cos_lat * (cos_lng * :CUR_cos_lng + sin_lng * :CUR_sin_lng) AS distance
            FROM locations AS l
            JOIN (
                   SELECT  :latpoint  AS latpoint, :longpoint AS longpoint,
                           :radius AS radius,      111.045 AS distance_unit
            ) AS p
            WHERE l.latitude
                BETWEEN p.latpoint  - (p.radius / p.distance_unit)
                    AND p.latpoint  + (p.radius / p.distance_unit)
                    AND l.longitude
                BETWEEN p.longpoint - (p.radius / (p.distance_unit * :CUR_cos_lat))
                    AND p.longpoint + (p.radius / (p.distance_unit * :CUR_cos_lat))
            ORDER BY distance DESC
            LIMIT :limit";

        $stmtDoc = $this->index->prepare($query);

        $cur_lat = $currentLocation['latitude'];
        $cur_lng = $currentLocation['longitude'];

        $CUR_cos_lat = cos($cur_lat * pi() / 180);
        $CUR_sin_lat = sin($cur_lat * pi() / 180);
        $CUR_cos_lng = cos($cur_lng * pi() / 180);
        $CUR_sin_lng = sin($cur_lng * pi() / 180);

        $stmtDoc->bindValue(':latpoint', $cur_lat);
        $stmtDoc->bindValue(':longpoint', $cur_lng);
        $stmtDoc->bindValue(':radius', $distance);
        $stmtDoc->bindValue(':CUR_cos_lat', $CUR_cos_lat);
        $stmtDoc->bindValue(':CUR_sin_lat', $CUR_sin_lat);
        $stmtDoc->bindValue(':CUR_cos_lng', $CUR_cos_lng);
        $stmtDoc->bindValue(':CUR_sin_lng', $CUR_sin_lng);
        $stmtDoc->bindValue(':limit', $limit);
        $stmtDoc->execute();
        $locations = new Collection($stmtDoc->fetchAll(PDO::FETCH_ASSOC));

        $locations = $locations->map(function ($location) use ($distance) {
            $location['distance'] = acos($location['distance']) * $this->earthRadius;

            if ($location['distance'] <= $distance) {
                return $location;
            }

        });

        return $locations;
    }
}
