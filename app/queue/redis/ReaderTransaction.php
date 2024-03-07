<?php

namespace app\queue\redis;

# system lib
use support\Db;
use Webman\RedisQueue\Consumer;
# database & logic
use app\model\database\AccountUserModel;
use app\model\database\SettingDepositModel;
use app\model\database\UserDepositModel;
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
                $this->deposit($queue["data"]);
                break;
            case "nft":
                $this->nft($queue["data"]);
                break;
        }
    }

    private function deposit($data)
    {
        $success = SettingLogic::get("operator", ["code" => "success"]);
        $topUp = SettingLogic::get("operator", ["code" => "top_up"]);

        $settingDeposits = SettingDepositModel::where("is_active", 1)->get();

        foreach ($settingDeposits as $settingDeposit) {
            // Get setting coin info
            $coin = SettingLogic::get("coin", ["id" => $settingDeposit["coin_id"]]);

            // Get setting network info
            $settingNetwork = SettingLogic::get("blockchainNetwork", ["id" => $settingDeposit["network"]]);
            if (!$settingNetwork["rpc_url"]) {
                continue;
            }

            // Get evm latest block & db latest block
            $endBlock = EvmLogic::getBlockNumber($settingNetwork["rpc_url"]);

            $startBlock = $settingDeposit["latest_block"];

            // if start block is 0 then push it to front
            if ($startBlock == 0) {
                $startBlock = $endBlock - 1000;
            }

            $differenceBlock = $endBlock - $startBlock;

            // If difference block more than 20 $endBlock will get $startBlock + 20
            if ($differenceBlock >= 30) {
                $endBlock = $startBlock + 30;
            }

            // Retrieve deposit records for the token address and network
            $recordReaders = EvmLogic::recordReader(
                $settingDeposit["token_address"],
                $settingNetwork["rpc_url"],
                null,
                $settingDeposit["address"],
                $startBlock + 1,
                $endBlock
            );

            echo $endBlock . "|" . $recordReaders . "\n";

            // if end block and record reader no error
            if ($endBlock && $recordReaders) {
                // Decode JSON string to object
                $recordLists = json_decode($recordReaders);

                if ($recordLists) {
                    foreach ($recordLists as $recordList) {
                        // Check if the user exists based on the 'from_address'
                        $user = AccountUserModel::where(
                            Db::raw("LOWER(web3_address)"),
                            "=",
                            strtolower($recordList->from_address)
                        )
                            ->where("status", "active")
                            ->first();

                        // Check if the user deposit exists based on the txid and log index
                        $userDeposit = UserDepositModel::where(["txid" => $recordList->txid, "log_index" => $recordList->meta->logIndex])->first();

                        // if user in account_user & txid and log index not exist in user_deposit
                        if ($user && !$userDeposit) {
                            # [create query]
                            $res = UserDepositModel::create([
                                "sn" => HelperLogic::randomCode(),
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

    private function nft($data)
    {
    }
}
