<?php

namespace app\queue\redis;

# system lib
use support\Db;
use Webman\RedisQueue\Consumer;
# database & logic
use app\model\database\AccountUserModel;
use app\model\database\SettingDepositModel;
use app\model\database\SettingNftModel;
use app\model\database\UserDepositModel;
use app\model\database\UserNftModel;
use app\model\database\UserSeedModel;
use app\model\logic\EvmLogic;
use app\model\logic\HelperLogic;
use app\model\logic\SettingLogic;
use app\model\logic\UserWalletLogic;

class ReaderTransaction implements Consumer
{
    // queue name
    public $queue = "reader";

    // connection name refer config/plugin/webman/redis-queue/redis.php
    public $connection = "default";

    // process
    public function consume($queue)
    {
        switch ($queue["type"]) {
            case "deposit":
                $this->deposit();
                break;
            case "nft":
                $this->nft();
                break;
        }
    }

    private function deposit()
    {
        $settingDeposits = SettingDepositModel::where("is_active", 1)->get();

        foreach ($settingDeposits as $settingDeposit) {
            // Get setting network info
            $settingNetwork = SettingLogic::get("blockchainNetwork", ["id" => $settingDeposit["network"]]);
            if (!$settingNetwork || empty($settingNetwork["rpc_url"])) {
                continue;
            }

            // Get contract address latest block & db latest block
            $endBlock = EvmLogic::getBlockNumber($settingNetwork["rpc_url"]);

            $startBlock = $settingDeposit["latest_block"];

            // if start block is 0 then push it to front
            if ($startBlock == 0) {
                $startBlock = $endBlock - 1000;
            }

            // If difference block more than 30, then $endBlock = $startBlock + 30
            $differenceBlock = $endBlock - $startBlock;
            if ($differenceBlock > 30) {
                $endBlock = $startBlock + 30;
            }

            // Retrieve records from the contract address and network
            $recordReaders = EvmLogic::recordReader(
                $settingDeposit["token_address"],
                $settingNetwork["rpc_url"],
                ($startBlock < $endBlock) ? $startBlock + 1 : $startBlock,
                $endBlock,
                "0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef",
                "",
                $settingDeposit["address"]
            );

            // echo $startBlock . "|" . $endBlock . "|" . $recordReaders . "\n";

            // if end block and record reader no error
            if ($endBlock > 0 && $recordReaders) {
                // Decode JSON string to object
                $recordLists = json_decode($recordReaders);

                if ($recordLists) {
                    foreach ($recordLists as $recordList) {
                        // Check if the user exists based on the 'from_address'
                        $user = AccountUserModel::where(Db::raw("LOWER(web3_address)"), strtolower($recordList->from_address))
                            ->where("status", "active")
                            ->first();

                        // Check if the user deposit exists based on the txid and log index
                        $userDeposit = UserDepositModel::where(["txid" => $recordList->txid, "log_index" => $recordList->meta->logIndex])->first();

                        // if user in account_user & txid and log index not exist in user_deposit
                        if ($user && !$userDeposit) {
                            $success = SettingLogic::get("operator", ["code" => "success"]);
                            $topUp = SettingLogic::get("operator", ["code" => "top_up"]);

                            // Get setting coin info
                            $coin = SettingLogic::get("coin", ["id" => $settingDeposit["coin_id"]]);

                            # [create query]
                            $res = UserDepositModel::create([
                                "sn" => HelperLogic::generateUniqueSN("user_deposit"),
                                "uid" => $user["id"],
                                "amount" => $recordList->value,
                                "status" => $success["id"],
                                "coin_id" => $settingDeposit["coin_id"],
                                "txid" => $recordList->txid,
                                "log_index" => $recordList->meta->logIndex,
                                "from_address" => $user["web3_address"],
                                "to_address" => $settingDeposit["address"],
                                "network" => $settingNetwork["id"],
                                "token_address" => $settingDeposit["token_address"],
                                "completed_at" => date("Y-m-d H:i:s"),
                            ]);

                            // Add Wallet
                            UserWalletLogic::add([
                                "type" => $topUp["id"],
                                "uid" => $user["id"],
                                "fromUid" => $user["id"],
                                "toUid" => $user["id"],
                                "distribution" => [$coin["wallet_id"] => $recordList->value],
                                "refTable" => "user_deposit",
                                "refId" => $res["id"],
                            ]);
                        }
                    }
                }

                // Update latest block
                SettingDepositModel::where("id", $settingDeposit["id"])->update(["latest_block" => $endBlock]);
            }
        }
    }

    private function nft()
    {
        $settingNfts = SettingNftModel::where("is_active", 1)->get();

        foreach ($settingNfts as $settingNft) {
            // Get setting network info
            $settingNetwork = SettingLogic::get("blockchainNetwork", ["id" => $settingNft["network"]]);
            if (!$settingNetwork || empty($settingNetwork["rpc_url"])) {
                continue;
            }

            // Get contract address latest block & db latest block
            $endBlock = EvmLogic::getBlockNumber($settingNetwork["rpc_url"]);

            $startBlock = $settingNft["latest_block"];

            // if start block is 0 then push it to front
            if ($startBlock == 0) {
                $startBlock = $endBlock - 1000;
            }

            // If difference block more than 30, then $endBlock = $startBlock + 30
            $differenceBlock = $endBlock - $startBlock;
            if ($differenceBlock > 30) {
                $endBlock = $startBlock + 30;
            }

            // Retrieve records from the contract address and network
            // if mint nft - from is 0x0000000000000000000000000000000000000000, to is user
            $recordReaders = EvmLogic::recordReader(
                $settingNft["token_address"],
                $settingNetwork["rpc_url"],
                ($startBlock < $endBlock) ? $startBlock + 1 : $startBlock,
                $endBlock,
                "0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef"
            );

            // echo $startBlock . "|" . $endBlock . "|" . $recordReaders . "\n";

            // if end block and record reader no error
            if ($endBlock > 0 && $recordReaders) {
                // Decode JSON string to object
                $recordLists = json_decode($recordReaders);

                if ($recordLists) {
                    foreach ($recordLists as $recordList) {
                        // seed check transfer between user only
                        // for the purpose of if the to_user seed is claimable 0 then they got a new seed then we need to make it claimable = 1 and claimed_at = now
                        if ($settingNft["name"] == "seed") {
                            $fromUser = AccountUserModel::where(Db::raw("LOWER(web3_address)"), strtolower($recordList->from_address))
                                ->where("status", "active")
                                ->first();

                            $toUser = AccountUserModel::where(Db::raw("LOWER(web3_address)"), strtolower($recordList->to_address))
                                ->where("status", "active")
                                ->first();

                            // if from user and to user exist in platform then proceed
                            if ($fromUser && $toUser) {
                                //check if to_user seed is claimable 0, if yes then update claimed at = now and claimable = 1
                                $seed = UserSeedModel::where(["uid" => $toUser["id"], "claimable" => 0])->first();
                                if ($seed) {
                                    UserSeedModel::where("id", $seed["id"])->update([
                                        "claimed_at" => date("Y-m-d H:i:s"),
                                        "claimable" => 1
                                    ]);
                                }
                            }
                        }
                    }
                }

                // Update latest block
                SettingNftModel::where("id", $settingNft["id"])->update(["latest_block" => $endBlock]);
            }
        }
    }
}
