<?php

namespace app\model\logic;

# system lib
# database & logic
use app\model\database\WalletTransactionModel;
use app\model\database\WalletTransactionDetailModel;
use app\model\logic\HelperLogic;

class UserWalletLogic
{
    public static function getBalance(int $uid = 0, int $walletId = 0)
    {
        $_response = false;

        $_response = WalletTransactionModel::leftJoin(
            "wallet_transaction_detail",
            "wallet_transaction.id",
            "=",
            "wallet_transaction_detail.wallet_transaction_id"
        )
            ->where([
                "wallet_transaction.uid" => $uid,
                "wallet_transaction_detail.wallet_id" => $walletId,
            ])
            ->sum("wallet_transaction_detail.amount");

        return $_response;
    }

    // UserWalletLogic::add([
    //     "type" => 4,
    //     "uid" => $targetId,
    //     "distribution" => [1 => 150, 2 => 50],
    //     "refTable" => "account_user",
    //     "refId" => 1
    // ]);
    public static function add(array $params)
    {
        $type = $params["type"];
        $uid = $params["uid"];
        $fromUid = $params["fromUid"] ?? 0;
        $toUid = $params["toUid"] ?? 0;
        $distribution = $params["distribution"];
        $refTable = $params["refTable"] ?? "";
        $refId = $params["refId"] ?? 0;
        $total = 0;

        $_response = false;

        $_response = WalletTransactionModel::create([
            "sn" => HelperLogic::generateUniqueSN("wallet_transaction"),
            "transaction_type" => $type,
            "uid" => $uid,
            "from_uid" => $fromUid,
            "to_uid" => $toUid,
            "distribution" => json_encode($distribution),
            "ref_table" => $refTable,
            "ref_id" => $refId,
            "used_at" => date("Ymd"),
        ]);

        $total = self::distribution($distribution, $_response, $uid, true);

        WalletTransactionModel::where("id", $_response["id"])->update(["amount" => $total]);

        return $_response;
    }

    public static function deduct(array $params)
    {
        $type = $params["type"];
        $uid = $params["uid"];
        $fromUid = $params["fromUid"] ?? 0;
        $toUid = $params["toUid"] ?? 0;
        $distribution = $params["distribution"];
        $refTable = $params["refTable"] ?? "";
        $refId = $params["refId"] ?? 0;
        $total = 0;

        $_response = false;

        $_response = WalletTransactionModel::create([
            "sn" => HelperLogic::generateUniqueSN("wallet_transaction"),
            "transaction_type" => $type,
            "uid" => $uid,
            "from_uid" => $fromUid,
            "to_uid" => $toUid,
            "distribution" => json_encode($distribution),
            "ref_table" => $refTable,
            "ref_id" => $refId,
            "used_at" => date("Ymd"),
        ]);

        $total = self::distribution($distribution, $_response, $uid, false);

        WalletTransactionModel::where("id", $_response["id"])->update(["amount" => $total]);

        return $_response;
    }

    private static function distribution($distribution, $_response, $uid, $positive)
    {
        $total = 0;
        foreach ($distribution as $walletId => $amount) {
            $total += abs($amount);

            $userbalance = self::getBalance($uid, $walletId);

            WalletTransactionDetailModel::create([
                "wallet_transaction_id" => $_response["id"],
                "wallet_id" => $walletId,
                "amount" => ($positive)
                    ? $amount
                    : -$amount,
                "before_amount" => $userbalance,
                "after_amount" => ($positive)
                    ? $userbalance + $amount
                    : $userbalance - $amount,
            ]);
        }

        return $total;
    }
}