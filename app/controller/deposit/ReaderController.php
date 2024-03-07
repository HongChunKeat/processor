<?php

namespace app\controller\deposit;

use support\Request;
use Web3\Web3;
use Web3\Utils;

class ReaderController
{
    public function index(Request $request)
    {
        $decimal = 18;
        $depositRecords = [];
        // $web3 = new Web3("https://bsc-dataseed4.ninicoin.io");
        // $usdt_address = "0x55d398326f99059fF775485246999027B3197955";
        // $web3 = new Web3("https://data-seed-prebsc-1-s2.binance.org:8545");
        $web3 = new Web3("https://bsc-dataseed1.binance.org");
        $usdt_address = "0x55d398326f99059fF775485246999027B3197955";
        $action = "0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef"; // transfer

        $params = [
            "fromBlock" => "0x" . dechex(34346580),
            "toBlock" => "0x" . dechex(34346590),
            "address" => $usdt_address,
            "topics" => [$action, null, "0x0000000000000000000000009d0ee38d849341d4bbc69187ac302ccbee1ac1f7"],
        ];

        $filter = null;
        $web3->eth->newFilter($params, function ($err, $data) use (&$filter) {
            if ($err) {
                // var_export($err);
                return "false";
            }
            $filter = $data;
        });

        $rawRecords = [];
        if ($filter) {
            $web3->eth->getFilterLogs($filter, function ($err, $data) use (&$rawRecords) {
                if ($err) {
                    var_export($err);
                    return "false2";
                }
                $rawRecords = $data;
            });
        }

        // $depositRecords = count($rawRecords);

        foreach ($rawRecords as $deposit) {
            $value = $this->hexdec2dec($deposit->data, $decimal);

            $depositRecords[] = [
                "txid" => $deposit->transactionHash,
                "block" => hexdec($deposit->blockNumber),
                "event_name" => $deposit->topics[0],
                "from_address" => strtolower(str_replace("0x000000000000000000000000", "0x", $deposit->topics[1])),
                "to_address" => strtolower(str_replace("0x000000000000000000000000", "0x", $deposit->topics[2])),
                "value" => $value,
                "meta" => $deposit,
            ];
        }

        # 充值注意事项

        /**
         * - 同一个 txid 可能会有好几次的转账
         * - 需要根据 txid + logIndex + removed 做判断
         */

        return json($depositRecords, JSON_PRETTY_PRINT);

        # records in blocks
        // return json($depositRecords, JSON_PRETTY_PRINT);
    }

    private function hexdec2dec(string $hexValue = "", int $decimalPlaces = 18)
    {
        list($bnq, $bnr) = Utils::fromWei(Utils::toBn($hexValue), "ether");
        return $bnq->toString() . "." . str_pad($bnr->toString(), $decimalPlaces, "0", STR_PAD_LEFT);
    }
}
