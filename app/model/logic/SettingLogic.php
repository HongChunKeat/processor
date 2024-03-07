<?php

namespace app\model\logic;

use app\model\database\SettingBlockchainNetworkModel;
use app\model\database\SettingCoinModel;
use app\model\database\SettingOperatorModel;
use app\model\database\SettingGeneralModel;
use app\model\database\SettingItemModel;

class SettingLogic
{
    public static function get(string $table = "", array $params = [], bool $list = false)
    {
        $_response = false;

        switch ($table) {
            case "blockchainNetwork":
                $_response = SettingBlockchainNetworkModel::where($params);
                break;
            case "coin":
                $_response = SettingCoinModel::where($params);
                break;
            case "general":
                $_response = SettingGeneralModel::where($params)->where("is_show", 1);
                break;
            case "item":
                $_response = SettingItemModel::where($params);
                break;
            case "operator":
                $_response = SettingOperatorModel::where($params);
                break;
        }

        if ($list) {
            $_response = $_response->get();
        } else {
            $_response = $_response->first();
        }

        return $_response;
    }
}
