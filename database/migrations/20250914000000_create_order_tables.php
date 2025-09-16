<?php

use think\migration\Migrator;

class CreateOrderTables extends Migrator
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with existing tables.
     */
    public function change()
    {
        // 创建用户表
        $table = $this->table('user', ['engine' => 'InnoDB', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci']);
        $table->addColumn('username', 'string', ['limit' => 50, 'default' => '', 'comment' => '用户名'])
              ->addColumn('email', 'string', ['limit' => 100, 'default' => '', 'comment' => '邮箱'])
              ->addColumn('phone', 'string', ['limit' => 20, 'default' => '', 'comment' => '手机号'])
              ->addColumn('password', 'string', ['limit' => 255, 'default' => '', 'comment' => '密码'])
              ->addColumn('nickname', 'string', ['limit' => 50, 'default' => '', 'comment' => '昵称'])
              ->addColumn('avatar', 'string', ['limit' => 255, 'default' => '', 'comment' => '头像'])
              ->addColumn('balance', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '余额'])
              ->addColumn('status', 'integer', ['limit' => 1, 'default' => 1, 'comment' => '状态:1正常,0禁用'])
              ->addColumn('create_time', 'datetime', ['null' => true, 'comment' => '创建时间'])
              ->addColumn('update_time', 'datetime', ['null' => true, 'comment' => '更新时间'])
              ->addIndex(['username'], ['unique' => true])
              ->addIndex(['email'], ['unique' => true])
              ->addIndex(['phone'], ['unique' => true])
              ->create();

        // 创建用户地址表
        $table = $this->table('user_address', ['engine' => 'InnoDB', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci']);
        $table->addColumn('user_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '用户ID'])
              ->addColumn('name', 'string', ['limit' => 50, 'default' => '', 'comment' => '收货人姓名'])
              ->addColumn('phone', 'string', ['limit' => 20, 'default' => '', 'comment' => '手机号'])
              ->addColumn('province', 'string', ['limit' => 50, 'default' => '', 'comment' => '省份'])
              ->addColumn('city', 'string', ['limit' => 50, 'default' => '', 'comment' => '城市'])
              ->addColumn('district', 'string', ['limit' => 50, 'default' => '', 'comment' => '区县'])
              ->addColumn('address', 'string', ['limit' => 255, 'default' => '', 'comment' => '详细地址'])
              ->addColumn('zip_code', 'string', ['limit' => 10, 'default' => '', 'comment' => '邮编'])
              ->addColumn('is_default', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '是否默认地址'])
              ->addColumn('create_time', 'datetime', ['null' => true, 'comment' => '创建时间'])
              ->addColumn('update_time', 'datetime', ['null' => true, 'comment' => '更新时间'])
              ->addIndex(['user_id'])
              ->create();

        // 创建商品表
        $table = $this->table('goods', ['engine' => 'InnoDB', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci']);
        $table->addColumn('name', 'string', ['limit' => 100, 'default' => '', 'comment' => '商品名称'])
              ->addColumn('description', 'text', ['null' => true, 'comment' => '商品描述'])
              ->addColumn('cover_image', 'string', ['limit' => 255, 'default' => '', 'comment' => '封面图片'])
              ->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '商品价格'])
              ->addColumn('stock', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '库存数量'])
              ->addColumn('sales', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '销量'])
              ->addColumn('status', 'integer', ['limit' => 1, 'default' => 1, 'comment' => '状态:1上架,0下架'])
              ->addColumn('sort', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '排序'])
              ->addColumn('create_time', 'datetime', ['null' => true, 'comment' => '创建时间'])
              ->addColumn('update_time', 'datetime', ['null' => true, 'comment' => '更新时间'])
              ->addColumn('delete_time', 'datetime', ['null' => true, 'comment' => '删除时间'])
              ->addIndex(['status'])
              ->addIndex(['sort'])
              ->create();

        // 创建商品SKU表
        $table = $this->table('goods_sku', ['engine' => 'InnoDB', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci']);
        $table->addColumn('goods_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商品ID'])
              ->addColumn('specs', 'text', ['null' => true, 'comment' => '规格值(JSON)'])
              ->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '价格'])
              ->addColumn('stock', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '库存数量'])
              ->addColumn('sales', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '销量'])
              ->addColumn('image', 'string', ['limit' => 255, 'default' => '', 'comment' => '图片'])
              ->addColumn('create_time', 'datetime', ['null' => true, 'comment' => '创建时间'])
              ->addColumn('update_time', 'datetime', ['null' => true, 'comment' => '更新时间'])
              ->addColumn('delete_time', 'datetime', ['null' => true, 'comment' => '删除时间'])
              ->addIndex(['goods_id'])
              ->create();

        // 创建订单表
        $table = $this->table('order', ['engine' => 'InnoDB', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci']);
        $table->addColumn('order_no', 'string', ['limit' => 32, 'default' => '', 'comment' => '订单号'])
              ->addColumn('user_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '用户ID'])
              ->addColumn('address_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '收货地址ID'])
              ->addColumn('total_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '订单总金额'])
              ->addColumn('pay_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '实际支付金额'])
              ->addColumn('freight_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '运费'])
              ->addColumn('discount_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '优惠金额'])
              ->addColumn('status', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '订单状态:0待支付,1已支付,2已发货,3已完成,4已取消,5已退款'])
              ->addColumn('pay_status', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '支付状态:0未支付,1已支付,2已退款'])
              ->addColumn('pay_time', 'datetime', ['null' => true, 'comment' => '支付时间'])
              ->addColumn('delivery_time', 'datetime', ['null' => true, 'comment' => '发货时间'])
              ->addColumn('complete_time', 'datetime', ['null' => true, 'comment' => '完成时间'])
              ->addColumn('cancel_reason', 'string', ['limit' => 255, 'default' => '', 'comment' => '取消原因'])
              ->addColumn('remark', 'string', ['limit' => 500, 'default' => '', 'comment' => '订单备注'])
              ->addColumn('create_time', 'datetime', ['null' => true, 'comment' => '创建时间'])
              ->addColumn('update_time', 'datetime', ['null' => true, 'comment' => '更新时间'])
              ->addColumn('delete_time', 'datetime', ['null' => true, 'comment' => '删除时间'])
              ->addIndex(['order_no'], ['unique' => true])
              ->addIndex(['user_id'])
              ->addIndex(['status'])
              ->addIndex(['create_time'])
              ->create();

        // 创建订单商品表
        $table = $this->table('order_item', ['engine' => 'InnoDB', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci']);
        $table->addColumn('order_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '订单ID'])
              ->addColumn('goods_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商品ID'])
              ->addColumn('sku_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => 'SKU ID'])
              ->addColumn('goods_name', 'string', ['limit' => 100, 'default' => '', 'comment' => '商品名称'])
              ->addColumn('sku_specs', 'string', ['limit' => 500, 'default' => '', 'comment' => 'SKU规格'])
              ->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '单价'])
              ->addColumn('quantity', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '购买数量'])
              ->addColumn('total_price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '小计金额'])
              ->addColumn('create_time', 'datetime', ['null' => true, 'comment' => '创建时间'])
              ->addColumn('update_time', 'datetime', ['null' => true, 'comment' => '更新时间'])
              ->addIndex(['order_id'])
              ->addIndex(['goods_id'])
              ->addIndex(['sku_id'])
              ->create();

        // 创建支付记录表
        $table = $this->table('payment_record', ['engine' => 'InnoDB', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci']);
        $table->addColumn('order_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '订单ID'])
              ->addColumn('payment_no', 'string', ['limit' => 32, 'default' => '', 'comment' => '支付单号'])
              ->addColumn('payment_method', 'string', ['limit' => 20, 'default' => '', 'comment' => '支付方式'])
              ->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '支付金额'])
              ->addColumn('status', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '支付状态'])
              ->addColumn('pay_time', 'datetime', ['null' => true, 'comment' => '支付时间'])
              ->addColumn('create_time', 'datetime', ['null' => true, 'comment' => '创建时间'])
              ->addColumn('update_time', 'datetime', ['null' => true, 'comment' => '更新时间'])
              ->addIndex(['payment_no'], ['unique' => true])
              ->addIndex(['order_id'])
              ->addIndex(['payment_method'])
              ->addIndex(['status'])
              ->create();
    }
}