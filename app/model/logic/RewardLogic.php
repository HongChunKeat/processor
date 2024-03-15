<?php

namespace app\model\logic;

# database & logic
use app\model\database\AccountUserModel;
use app\model\database\RewardRecordModel;
use app\model\database\NetworkSponsorModel;
use app\model\logic\HelperLogic;
use app\model\logic\SettingLogic;
use app\model\logic\EvmLogic;
use app\model\logic\UserWalletLogic;

class RewardLogic
{
    public static function rewardRecord(array $params)
    {
        $res = RewardRecordModel::create([
            "sn" => HelperLogic::generateUniqueSN("reward_record"),
            "pay_at" => $params["payAt"] ?? null,
            "used_at" => $params["usedAt"] ?? 0,
            "uid" => $params["uid"] ?? 0,
            "user_tree_id" => $params["userTreeId"] ?? 0,
            "from_uid" => $params["fromUid"] ?? 0,
            "from_user_tree_id" => $params["fromUserTreeId"] ?? 0,
            "reward_type" => $params["rewardType"] ?? 0,
            "amount" => $params["amount"] ?? 0,
            "rate" => $params["rate"] ?? 0,
            "distribution" => !empty($params["distribution"])
                ? json_encode($params["distribution"])
                : null,
            "ref_table" => $params["refTable"] ?? "",
            "ref_id" => $params["refId"] ?? 0,
        ]);

        return $res;
    }


    public static function countMultiplier($uid)
    {
        $response = 0;

        $user = AccountUserModel::where("id", $uid)->first();
        if ($user) {
            $multiplier1 = SettingLogic::get("general", ["category" => "reward", "code" => "gen1_nft_multiplier"]);
            $multiplier2 = SettingLogic::get("general", ["category" => "reward", "code" => "gen2_nft_multiplier"]);

            // count gen 1 nft
            $gen1Nft = SettingLogic::get("nft", ["name" => "gen1_nft"]);
            $gen1Network = SettingLogic::get("blockchain_network", ["id" => $gen1Nft["network"]]);
            $gen1NftCount = EvmLogic::getBalance("nft", $gen1Network["rpc_url"], $gen1Nft["token_address"], $user["web3_address"]);
            $gen1Multiplier = $gen1NftCount * $multiplier1["value"];

            // count gen 2 nft
            $gen2Nft = SettingLogic::get("nft", ["name" => "gen2_nft"]);
            $gen2Network = SettingLogic::get("blockchain_network", ["id" => $gen2Nft["network"]]);
            $gen2NftCount = EvmLogic::getBalance("nft", $gen2Network["rpc_url"], $gen2Nft["token_address"], $user["web3_address"]);
            $gen2Multiplier = $gen2NftCount * $multiplier2["value"];

            $response = $gen1Multiplier + $gen2Multiplier;
        }

        return $response;
    }

    /*** 
        check seed need run first cause reward got delay, the function is in queue
     
        claim point didnt see how many day, as long as u got over 24 h then able to claim
            - so 2 day no claim then claim the point will still be same as 1 day de reward
        
        count for user first then from there give some percent to upline
            - user take 100%, upline take 10% then 5% (reward distribution = 100, 10, 5)
            - user got 5000(100%), then upline lvl 1 got 500(10%), upline lvl 2 got 250(5%)
            - give user first then give upline
     ***/
    public static function seedReward($uid, $seed)
    {
        $pointRewardOperator = SettingLogic::get("operator", ["code" => "point_reward"]);
        $pointWallet = SettingLogic::get("general", ["category" => "seed", "code" => "reward_wallet"]);
        $pointAmount = SettingLogic::get("general", ["category" => "seed", "code" => "reward_amount"]);
        $rewardDistribution = SettingLogic::get("general", ["category" => "seed", "code" => "reward_distribution"]);
        $rewardDistribution = HelperLogic::explodeParams($rewardDistribution["value"]);

        if ($pointRewardOperator && $pointWallet && $pointAmount && $rewardDistribution) {
            // need plus 1 cause 1 is default, treat multiplier as extra addon on top of default multiplier
            // default : 100 * (1 + 0) = 100
            // with multiplier : 100 * (1 + 1) = 200
            $multiplier = 1 + self::countMultiplier($uid);
            $userReward = $pointAmount["value"] * $multiplier;

            $count = 0;
            $curUser = $uid;
            do {
                $amount = $userReward * ($rewardDistribution[$count] / 100);

                $res = self::rewardRecord([
                    "payAt" => date("Y-m-d H:i:s"),
                    "usedAt" => date("Ymd"),
                    "uid" => $curUser,
                    "fromUid" => $uid,
                    "rewardType" => $pointRewardOperator["id"],
                    "amount" => $amount,
                    "rate" => $rewardDistribution[$count],
                    "distribution" => [$pointWallet["value"] => round($amount, 8)],
                    "refTable" => "user_seed",
                    "refId" => $seed["id"],
                ]);

                UserWalletLogic::add([
                    "type" => $pointRewardOperator["id"],
                    "uid" => $curUser,
                    "fromUid" => $uid,
                    "toUid" => $curUser,
                    "distribution" => [$pointWallet["value"] => round($amount, 8)],
                    "refTable" => "reward_record",
                    "refId" => $res["id"],
                ]);

                // find upline
                $userNetwork = NetworkSponsorModel::select("uid", "upline_uid")
                    ->where("uid", $curUser)
                    ->first();

                // go to next rate
                $count++;

                // if have upline and have next rate
                if ($userNetwork && $userNetwork["upline_uid"] > 0 && $rewardDistribution[$count]) {
                    $curUser = $userNetwork["upline_uid"];
                } else {
                    $curUser = false;
                }
            } while ($curUser);
        }
    }
}
