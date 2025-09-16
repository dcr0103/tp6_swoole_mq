<?php
namespace app\command;

use think\facade\Log;
use think\facade\Db;    
use app\common\service\MessageProducerService;


class SendLocalMessages
{
    protected $retryDelays = [10, 30, 60, 300, 600]; // 秒
    protected $maxRetry = 5;

    public function handle()
    {
        while (true) {
            $messages = Db::table('local_message')
                ->where('status', 0)
                ->where('next_retry_time', '<=', date('Y-m-d H:i:s'))
                ->limit(100)
                ->get();

            foreach ($messages as $msg) {
                try {
                    $producer = new MessageProducerService();
                    $producer->rawPublish(
                        $msg->exchange,
                        $msg->routing_key,
                        json_decode($msg->body, true)
                    );

                    Db::table('local_message')
                        ->where('id', $msg->id)
                        ->update(['status' => 1, 'updated_at' => date('Y-m-d H:i:s')]);

                    Log::info("本地消息投递成功", ['message_id' => $msg->message_id]);

                } catch (\Exception $e) {
                    $nextDelay = $msg->try_count < count($this->retryDelays)
                        ? $this->retryDelays[$msg->try_count]
                        : 600;

                    $nextRetry = date('Y-m-d H:i:s', time() + $nextDelay);

                    Db::table('local_message')
                        ->where('id', $msg->id)
                        ->update([
                            'status' => 2,
                            'try_count' => $msg->try_count + 1,
                            'next_retry_time' => $nextRetry,
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);

                    Log::error('本地消息投递失败', [
                        'message_id' => $msg->message_id,
                        'error' => $e->getMessage(),
                        'next_retry' => $nextRetry,
                    ]);
                }
            }

            if (count($messages) < 100) {
                sleep(1);
            }
        }
    }
}