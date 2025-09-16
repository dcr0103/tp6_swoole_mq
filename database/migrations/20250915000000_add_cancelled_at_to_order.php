<?php

use think\migration\Migrator;

class AddCancelledAtToOrder extends Migrator
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
        // 为订单表添加取消时间字段
        $table = $this->table('order');
        $table->addColumn('cancelled_at', 'datetime', [
                'null' => true,
                'after' => 'cancel_reason',
                'comment' => '订单取消时间'
            ])
            ->update();
    }
}