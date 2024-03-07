<?php

return [
    // default selection
    "default" => "mysql",

    // options
    "connections" => [
        "mysql" => [
            "driver" => "mysql",
            "host" => env("DB_HOST"),
            "port" => env("DB_PORT"),
            "database" => env("DB_DATABASE"),
            "username" => env("DB_USERNAME"),
            "password" => env("DB_PASSWORD"),
            "unix_socket" => "",
            "charset" => "utf8mb4",
            "collation" => "utf8mb4_general_ci",
            "prefix" => "sw_",
            "strict" => true,
            "engine" => "INNODB",
        ],
    ],
];
