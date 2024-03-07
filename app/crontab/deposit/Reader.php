<?php

namespace app\crontab\deposit;

# library
use Webman\RedisQueue\Redis as RedisQueue;
use WebmanTech\CrontabTask\BaseTask;

class Reader extends BaseTask
{
    public function handle()
    {
        # [process with queue]
        RedisQueue::send("reader", [
            "type" => "deposit",
            "data" => []
        ]);
    }
}