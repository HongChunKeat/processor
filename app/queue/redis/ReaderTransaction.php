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
            $settingNetwork = SettingLogic::get("blockchain_network", ["id" => $settingDeposit["network"]]);
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
            $recordLists = EvmLogic::recordReader(
                $settingDeposit["token_address"],
                $settingNetwork["rpc_url"],
                ($startBlock < $endBlock) ? $startBlock + 1 : $startBlock,
                $endBlock,
                "0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef",
                "",
                $settingDeposit["address"]
            );

            // echo $startBlock . "|" . $endBlock . "|" . json_encode($recordLists) . "\n";

            // if end block not 0 and record reader is array
            if ($endBlock > 0 && is_array($recordLists)) {
                foreach ($recordLists as $record) {
                    // Check if the user exists based on the 'from_address'
                    $user = AccountUserModel::where(Db::raw("LOWER(web3_address)"), strtolower($record["fromAddress"]))
                        ->where("status", "active")
                        ->first();

                    // Check if the user deposit exists based on the txid and log index
                    $userDeposit = UserDepositModel::where(["txid" => $record["txid"], "log_index" => $record["logIndex"]])->first();

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
                            "amount" => $record["amount"],
                            "status" => $success["id"],
                            "coin_id" => $settingDeposit["coin_id"],
                            "txid" => $record["txid"],
                            "log_index" => $record["logIndex"],
                            "from_address" => $user["web3_address"],
                            "to_address" => $settingDeposit["address"],
                            "network" => $settingNetwork["id"],
                            "token_address" => $settingDeposit["token_address"],
                            "completed_at" => date("Y-m-d H:i:s"),
                        ]);

                        if ($res) {
                            // Add Wallet
                            UserWalletLogic::add([
                                "type" => $topUp["id"],
                                "uid" => $user["id"],
                                "fromUid" => $user["id"],
                                "toUid" => $user["id"],
                                "distribution" => [$coin["wallet_id"] => $record["amount"]],
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
            $settingNetwork = SettingLogic::get("blockchain_network", ["id" => $settingNft["network"]]);
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
            $recordLists = EvmLogic::recordReader(
                $settingNft["token_address"],
                $settingNetwork["rpc_url"],
                ($startBlock < $endBlock) ? $startBlock + 1 : $startBlock,
                $endBlock,
                "0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef"
            );

            // echo $startBlock . "|" . $endBlock . "|" . json_encode($recordLists) . "\n";

            // if end block not 0 and record reader is array
            if ($endBlock > 0 && is_array($recordLists)) {
                foreach ($recordLists as $record) {
                    /* 
                        a. phase 1: only newly minted seed able to register in phase 1, so need record in user nft if got newly minted seed
                        - recorded data = to address is lowercase, uid and ref id 0
                        - recorded data need used in auth to find address exist or not for register
                        - record once per address only, because mint seed is only one per address
                        b. if user have seed and seed is claimable 0, and user receive new seed then update claimed_at = now and claimable = 1
                        - if claimable = 1 nothing happened, because reward countdown is calculated based on the first seed they got
                    */
                    if ($settingNft["name"] == "plant") {
                        $toUser = AccountUserModel::where(Db::raw("LOWER(web3_address)"), strtolower($record["toAddress"]))
                            ->where("status", "active")
                            ->first();

                        // if to_user exist
                        if ($toUser) {
                            $seed = UserSeedModel::where(["uid" => $toUser["id"], "claimable" => 0])->first();
                            if ($seed) {
                                // if user have seed and seed is claimable 0, and user receive new seed then update
                                UserSeedModel::where("id", $seed["id"])->update([
                                    "claimed_at" => date("Y-m-d H:i:s"),
                                    "claimable" => 1
                                ]);
                            }
                        } else {
                            // check phase and if seed is newly minted
                            $phaseOpen = SettingLogic::get("general", ["category" => "version", "code" => "phase_1", "value" => 1]);
                            if ($phaseOpen && $record["fromAddress"] == "0x0000000000000000000000000000000000000000") {
                                $exist = UserNftModel::where([
                                    "from_address" => "0x0000000000000000000000000000000000000000",
                                    "to_address" => $record["toAddress"],
                                    "network" => $settingNetwork["id"],
                                    "token_address" => $settingNft["token_address"],
                                    "ref_table" => "account_user"
                                ])->first();

                                // if address not exist then proceed
                                if (!$exist) {
                                    $success = SettingLogic::get("operator", ["code" => "success"]);

                                    // record newly minted seed for register usage at auth, to address will be lowercase, uid and ref id = 0
                                    UserNftModel::create([
                                        "sn" => HelperLogic::generateUniqueSN("user_nft"),
                                        "status" => $success["id"],
                                        "txid" => $record["txid"],
                                        "log_index" => $record["logIndex"],
                                        "from_address" => $record["fromAddress"],
                                        "to_address" => $record["toAddress"],
                                        "network" => $settingNetwork["id"],
                                        "token_address" => $settingNft["token_address"],
                                        "completed_at" => date("Y-m-d H:i:s"),
                                        "ref_table" => "account_user"
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
