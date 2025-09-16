<?php

namespace app\model;

use think\Model;
use think\model\concern\SoftDelete;

class GoodsSku extends Model
{
    use SoftDelete;

    protected $name = 'goods_sku';
    protected $pk = 'id';

    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    protected $deleteTime = 'delete_time';

    protected $type = [
        'price' => 'float',
        'stock' => 'integer',
        'sales' => 'integer',
    ];

    // 关联：商品
    public function goods()
    {
        return $this->belongsTo(Goods::class, 'goods_id', 'id');
    }

    // 检查库存
    public function hasStock($quantity)
    {
        return $this->stock >= $quantity;
    }

    // 扣减库存
    public function decreaseStock($quantity)
    {
        return $this->where('id', $this->id)
                   ->where('stock', '>=', $quantity)
                   ->dec('stock', $quantity)
                   ->inc('sales', $quantity)
                   ->update();
    }

    // 增加库存
    public function increaseStock($quantity)
    {
        return $this->where('id', $this->id)
                   ->inc('stock', $quantity)
                   ->dec('sales', $quantity)
                   ->update();
    }

    // 获取规格值
    public function getSpecsAttribute($value)
    {
        return json_decode($value, true) ?? [];
    }

    // 设置规格值
    public function setSpecsAttribute($value)
    {
        $this->attributes['specs'] = json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}