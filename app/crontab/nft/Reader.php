<?php

namespace app\crontab\nft;

# library
use Webman\RedisQueue\Redis as RedisQueue;
use WebmanTech\CrontabTask\BaseTask;

class Reader extends BaseTask
{
    public function handle()
    {
        # [process with queue]
        RedisQueue::send("reader", [
            "type" => "nft",
            "data" => []
        ]);
    }
}