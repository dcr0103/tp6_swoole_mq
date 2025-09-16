<?php

namespace app\model;

use think\Model;
use think\model\concern\SoftDelete;

class GoodsImage extends Model
{
    use SoftDelete;

    protected $name = 'goods_image';
    protected $pk = 'id';

    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    protected $deleteTime = 'delete_time';

    // 关联：商品
    public function goods()
    {
        return $this->belongsTo(Goods::class, 'goods_id', 'id');
    }
}