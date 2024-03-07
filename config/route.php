<?php

/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use Webman\Route;

// turn off auto route
Route::disableDefaultRoute();

// start self-defined route path
Route::get("/deposit", [app\crontab\deposit\Reader::class, "handle"]);
// Route::get("/nft", [app\crontab\nft\Reader::class, "handle"]);
Route::get("/reader", [app\controller\deposit\ReaderController::class, "index"]);
