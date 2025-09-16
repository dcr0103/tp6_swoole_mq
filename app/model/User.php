<?php

namespace app\model;

use think\Model;

class User extends Model
{
    protected $name = 'user';
    protected $pk = 'id';

    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $hidden = ['password'];

    // 关联：订单
    public function orders()
    {
        return $this->hasMany(Order::class, 'user_id', 'id');
    }

    // 关联：收货地址
    public function addresses()
    {
        return $this->hasMany(UserAddress::class, 'user_id', 'id');
    }
}