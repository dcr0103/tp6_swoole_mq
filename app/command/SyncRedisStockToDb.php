<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Cache;
use think\facade\Log;
use app\model\GoodsSku;

class SyncRedisStockToDb extends Command
{
    protected function configure()
    {
        $this->setName('sync:redis_stock_to_db')
            ->setDescription('Sync Redis stock to database');
    }

    protected function execute(Input $input, Output $output)
    {
        $keys = redis()->keys('stock:sku:*');

        foreach ($keys as $key) {
            $skuId = str_replace('stock:sku:', '', $key);
            $stock = (int)redis()->get($key);

            GoodsSku::where('id', $skuId)->update(['stock' => $stock]);

            Log::info("同步 Redis 库存到数据库", [
                'sku_id' => $skuId,
                'stock' => $stock,
            ]);
        }
    }
}