<?php

namespace TeamTNT\TNTSearch;

use Illuminate\Support\ServiceProvider;
use TeamTNT\TNTSearch\TNTSearch;

class TNTSearchServiceProvider extends ServiceProvider
{

    public function register()
    {
        $this->app['tntsearch'] = $this->app->share(function ($app) {
            $config = [
                'driver'   => 'mysql',
                'host'     => env('DB_HOST', 'localhost'),
                'database' => env('DB_DATABASE'),
                'username' => env('DB_USERNAME'),
                'password' => env('DB_PASSWORD'),
                'storage'  => storage_path(),
            ];

            if (isset($app['config']['services']['tntsearch'])) {
                $config = $app['config']['services']['tntsearch'];
            }
            $tnt = new TNTSearch;
            $tnt->loadConfig($config);
            return $tnt;
        });
    }
}
