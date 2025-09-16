<?php

namespace app\model;

use think\Model;
use think\model\concern\SoftDelete;

class Order extends Model
{
    use SoftDelete;

    // 订单状态常量
    const STATUS_PENDING = 0;    // 待支付
    const STATUS_PAID = 1;       // 已支付
    const STATUS_SHIPPED = 2;    // 已发货
    const STATUS_COMPLETED = 3;  // 已完成
    const STATUS_CANCELLED = 4;  // 已取消
    const STATUS_REFUNDED = 5;   // 已退款

    // 支付状态常量
    const PAY_STATUS_UNPAID = 0;   // 未支付
    const PAY_STATUS_PAID = 1;     // 已支付
    const PAY_STATUS_REFUND = 2;   // 已退款

    protected $name = 'order';
    protected $pk = 'id';

    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    protected $deleteTime = 'delete_time';

    // 状态字段类型转换
    protected $type = [
        'total_amount' => 'float',
        'pay_amount' => 'float',
        'freight_amount' => 'float',
        'discount_amount' => 'float',
        'status' => 'integer',
        'pay_status' => 'integer',
        'pay_time' => 'datetime',
        'delivery_time' => 'datetime',
        'complete_time' => 'datetime',
    ];

    // 获取器：状态描述
    public function getStatusTextAttr($value, $data)
    {
        $status = [
            self::STATUS_PENDING => '待支付',
            self::STATUS_PAID => '已支付',
            self::STATUS_SHIPPED => '已发货',
            self::STATUS_COMPLETED => '已完成',
            self::STATUS_CANCELLED => '已取消',
            self::STATUS_REFUNDED => '已退款',
        ];
        return $status[$data['status']] ?? '未知状态';
    }

    // 获取器：支付状态描述
    public function getPayStatusTextAttr($value, $data)
    {
        $status = [
            self::PAY_STATUS_UNPAID => '未支付',
            self::PAY_STATUS_PAID => '已支付',
            self::PAY_STATUS_REFUND => '已退款',
        ];
        return $status[$data['pay_status']] ?? '未知状态';
    }

    // 关联：订单商品
    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'id');
    }

    // 关联：用户
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    // 关联：收货地址
    public function address()
    {
        return $this->belongsTo(UserAddress::class, 'address_id', 'id');
    }

    // 关联：支付记录
    public function payments()
    {
        return $this->hasMany(PaymentRecord::class, 'order_id', 'id');
    }

    // 作用域：状态查询
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    // 作用域：用户订单
    public function scopeUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // 作用域：订单号查询
    public function scopeOrderNo($query, $orderNo)
    {
        return $query->where('order_no', $orderNo);
    }

    // 生成订单号
    public static function generateOrderNo()
    {
        return date('YmdHis') . substr(microtime(), 2, 6) . sprintf('%04d', mt_rand(0, 9999));
    }

    // 检查是否可支付
    public function canPay()
    {
        return $this->status === self::STATUS_PENDING && $this->pay_status === self::PAY_STATUS_UNPAID;
    }

    // 检查是否可取消
    public function canCancel()
    {
        return $this->status === self::STATUS_PENDING;
    }

    // 检查是否可退款
    public function canRefund()
    {
        return in_array($this->status, [self::STATUS_PAID, self::STATUS_SHIPPED]);
    }
}