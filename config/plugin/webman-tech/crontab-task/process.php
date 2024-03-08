<?php

use WebmanTech\CrontabTask\Schedule;

// 添加单个定时任务，独立进程
// ->addTask('task1', '*/1 * * * * *', \WebmanTech\CrontabTask\Tasks\SampleTask::class)
// 添加多个定时任务，在同个进程中（注意会存在阻塞）
// ->addTasks('task2', [
//     ['*/1 * * * * *', \WebmanTech\CrontabTask\Tasks\SampleTask::class],
//     ['*/1 * * * * *', \WebmanTech\CrontabTask\Tasks\SampleTask::class],
// ])

return (new Schedule())
    ->addTask("deposit_reader", "*/30 * * * * *", \app\crontab\deposit\Reader::class)
    ->addTask("nft_reader", "*/30 * * * * *", \app\crontab\nft\Reader::class)
    ->buildProcesses();
