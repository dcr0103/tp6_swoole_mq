<?php

namespace app\controller;

use think\facade\Env;

class EnvTest
{
    public function index()
    {
        return json([
            'env_vars' => [
                'DATABASE_TYPE' => Env::get('DATABASE_TYPE'),
                'DATABASE_HOST' => Env::get('DATABASE_HOST'),
                'DATABASE_NAME' => Env::get('DATABASE_NAME'),
                'DATABASE_USERNAME' => Env::get('DATABASE_USERNAME'),
                'DATABASE_PASSWORD' => Env::get('DATABASE_PASSWORD'),
                'DATABASE_PORT' => Env::get('DATABASE_PORT'),
                'DATABASE_CHARSET' => Env::get('DATABASE_CHARSET'),
            ],
            'database_config' => config('database.connections.mysql'),
            'default_connection' => config('database.default')
        ]);
    }
}