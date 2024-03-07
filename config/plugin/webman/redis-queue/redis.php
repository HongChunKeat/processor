<?php
return [
    "default" => [
        "host" => "redis://".env("REDIS_HOST").":".env("REDIS_PORT"),
        "options" => [
            "auth" => null,       // 密码，字符串类型，可选参数
            "db" => 1,            // 数据库
            "prefix" => "",       // key 前缀
            "max_attempts"  => 0, // 消费失败后，重试次数
            "retry_seconds" => 5, // 重试间隔，单位秒
        ]
    ],
];
