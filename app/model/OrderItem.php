<?php

namespace app\model;

use think\Model;

class OrderItem extends Model
{
    protected $name = 'order_item';
    protected $pk = 'id';

    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $type = [
        'price' => 'float',
        'quantity' => 'integer',
        'total_price' => 'float',
    ];

    // 关联：订单
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    // 关联：商品
    public function goods()
    {
        return $this->belongsTo(Goods::class, 'goods_id', 'id');
    }

    // 关联：商品规格
    public function sku()
    {
        return $this->belongsTo(GoodsSku::class, 'sku_id', 'id');
    }
}