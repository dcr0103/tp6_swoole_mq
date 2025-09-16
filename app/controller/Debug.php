<?php

namespace app\controller;

use think\facade\Config;

class Debug
{
    public function index()
    {
        return json([
            'database_config' => Config::get('database'),
            'mysql_config' => Config::get('database.connections.mysql'),
            'env_vars' => [
                'DATABASE_DRIVER' => env('DATABASE_DRIVER'),
                'DATABASE_TYPE' => env('DATABASE_TYPE'),
                'DATABASE_HOST' => env('DATABASE_HOST'),
                'DATABASE_NAME' => env('DATABASE_NAME'),
                'DATABASE_USERNAME' => env('DATABASE_USERNAME'),
                'DATABASE_PASSWORD' => env('DATABASE_PASSWORD'),
                'DATABASE_PORT' => env('DATABASE_PORT'),
                'DATABASE_CHARSET' => env('DATABASE_CHARSET'),
            ]
        ]);
    }
}