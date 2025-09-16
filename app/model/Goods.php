<?php

namespace app\model;

use think\Model;
use think\model\concern\SoftDelete;

class Goods extends Model
{
    use SoftDelete;

    protected $name = 'goods';
    protected $pk = 'id';

    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    protected $deleteTime = 'delete_time';

    protected $type = [
        'price' => 'float',
        'stock' => 'integer',
        'sales' => 'integer',
        'status' => 'integer',
    ];

    // 关联：商品SKU
    public function skus()
    {
        return $this->hasMany(\app\model\GoodsSku::class, 'goods_id', 'id');
    }

    // 关联：商品图片
    public function images()
    {
        return $this->hasMany(\app\model\GoodsImage::class, 'goods_id', 'id');
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

    // 作用域：上架商品
    public function scopeOnSale($query)
    {
        return $query->where('status', 1)->where('stock', '>', 0);
    }
}