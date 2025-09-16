<?php

use think\facade\Cache;

// 应用公共文件
if (!function_exists('redis')) {
    function redis()
    {
        static $redis = null;
        if ($redis === null) {
            $redis = Cache::store('redis')->handler();
        }
        return $redis;
    }
}