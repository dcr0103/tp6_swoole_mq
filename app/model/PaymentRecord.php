<?php

namespace app\model;

use think\Model;

class PaymentRecord extends Model
{
    // 支付记录状态常量
    const STATUS_PENDING = 0;    // 待支付
    const STATUS_SUCCESS = 1;    // 支付成功
    const STATUS_FAILED = 2;     // 支付失败
    const STATUS_REFUND = 3;     // 已退款

    protected $name = 'payment_record';
    protected $pk = 'id';

    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $type = [
        'amount' => 'float',
        'status' => 'integer',
        'pay_time' => 'datetime',
    ];

    // 关联：订单
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    // 作用域：支付方式
    public function scopePaymentMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }

    // 作用域：状态
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}