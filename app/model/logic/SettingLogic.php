<?php

namespace app\model\logic;

use app\model\database\SettingBlockchainNetworkModel;
use app\model\database\SettingCoinModel;
use app\model\database\SettingDepositModel;
use app\model\database\SettingGeneralModel;
use app\model\database\SettingLevelModel;
use app\model\database\SettingNftModel;
use app\model\database\SettingOperatorModel;

class SettingLogic
{
    public static function get(string $table = "", array $params = [], bool $list = false)
    {
        $_response = false;

        switch ($table) {
            case "blockchain_network":
                $_response = SettingBlockchainNetworkModel::where($params);
                break;
            case "coin":
                $_response = SettingCoinModel::where($params);
                break;
            case "deposit":
                $_response = SettingDepositModel::where($params);
                break;
            case "general":
                $_response = SettingGeneralModel::where($params)->where("is_show", 1);
                break;
            case "level":
                $_response = SettingLevelModel::where($params);
                break;
            case "nft":
                $_response = SettingNftModel::where($params);
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
