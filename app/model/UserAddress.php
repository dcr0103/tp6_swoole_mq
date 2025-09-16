<?php

namespace app\model;

use think\Model;

class UserAddress extends Model
{
    protected $name = 'user_address';
    protected $pk = 'id';

    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $type = [
        'is_default' => 'boolean',
    ];

    // 关联：用户
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    // 作用域：用户地址
    public function scopeUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // 作用域：默认地址
    public function scopeDefault($query)
    {
        return $query->where('is_default', 1);
    }
}