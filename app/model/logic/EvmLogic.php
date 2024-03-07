<?php

declare(strict_types=1);

namespace app\model\logic;

use Exception;
use support\Log;
use Web3\Contract;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;
use Web3\Utils;
use Web3\Web3;
use Elliptic\EC;
use kornrunner\Ethereum\Transaction;
use kornrunner\Keccak;

final class EvmLogic
{
    public static function getBlockNumber(string $rpc_url)
    {
        $error = 0;
        $web3 = new Web3(new HttpProvider(new HttpRequestManager($rpc_url, 2)));

        $block = 0;
        $web3->eth->blockNumber(function ($err, $data) use (&$block, &$error) {
            if ($err) {
                Log::error("blockNumber err", ["err" => $err]);
                $error++;
            } else {
                $block = (int) $data->toString();
            }
        });

        if ($error > 0) {
            return false;
        } else {
            return $block;
        }
    }

    public function getTransactionLog(
        string $rpc_url,
        string $contract_address,
        int $start_block,
        int $block_range = 30
    ): array {
        $web3 = new Web3(new HttpProvider(new HttpRequestManager($rpc_url, 2)));

        $now_block = $this->getBlockNumber($rpc_url);
        $end_block = $start_block + $block_range;
        if ($end_block > $now_block) {
            $end_block = $now_block;
        }

        $decimal = $this->getDecimals($rpc_url, $contract_address);

        $params = [
            "fromBlock" => "0x" . dechex($start_block),
            "toBlock" => "0x" . dechex($end_block),
            "address" => $contract_address,
        ];

        $filter = null;
        $web3->eth->newFilter($params, function ($err, $data) use (&$filter) {
            if ($err) {
                Log::error("newFilter err", ["err" => $err]);
            } else {
                $filter = $data;
            }
        });

        $logs = [];
        $web3->eth->getFilterLogs($filter, function ($err, $data) use (&$logs) {
            if ($err) {
                Log::error("getFilterLogs err", ["err" => $err]);
            } else {
                $logs = $data;
            }
        });

        $list = [];
        foreach ($logs as $log) {
            $event_name = $log->topics[0];
            $from_address = strtolower(str_replace("0x000000000000000000000000", "0x", $log->topics[1]));
            $to_address = strtolower(str_replace("0x000000000000000000000000", "0x", $log->topics[2]));
            $value = $this->hexdec2dec($log->data);
            $txid = $log->transactionHash;
            $block = hexdec($log->blockNumber);

            $list[] = [
                "txid" => $txid,
                "block" => $block,
                "event_name" => $event_name,
                "from_address" => $from_address,
                "to_address" => $to_address,
                "value" => bcdiv(strval($value), bcpow("10", $decimal), intval($decimal)),
                "meta" => $log,
            ];
        }

        $ret["list"] = $list;

        $ret["end_block"] = $end_block;

        return $ret;
    }

    public static function hexdec2dec(string $hexValue = "", int $decimalPlaces = 18)
    {
        list($bnq, $bnr) = Utils::fromWei(Utils::toBn($hexValue), "ether");
        return $bnq->toString() . "." . str_pad($bnr->toString(), $decimalPlaces, "0", STR_PAD_LEFT);
    }

    public function getDecimals(string $rpc_url, string $token_address)
    {
        $web3 = new Web3(new HttpProvider(new HttpRequestManager($rpc_url, 2)));
        $contract = new Contract($web3->provider, $this->abi());

        $decimal = "0";
        $contract->at($token_address)->call("decimals", function ($err, $data) use (&$decimal) {
            if ($err) {
                Log::error("getDecimals err", ["err" => $err]);
            } else {
                $decimal = $data[0]->toString();
            }
        });

        return $decimal;
    }

    public function abi()
    {
        return '[{"inputs":[],"payable":false,"stateMutability":"nonpayable","type":"constructor"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"owner","type":"address"},{"indexed":true,"internalType":"address","name":"spender","type":"address"},{"indexed":false,"internalType":"uint256","name":"value","type":"uint256"}],"name":"Approval","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"previousOwner","type":"address"},{"indexed":true,"internalType":"address","name":"newOwner","type":"address"}],"name":"OwnershipTransferred","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"from","type":"address"},{"indexed":true,"internalType":"address","name":"to","type":"address"},{"indexed":false,"internalType":"uint256","name":"value","type":"uint256"}],"name":"Transfer","type":"event"},{"constant":true,"inputs":[],"name":"_decimals","outputs":[{"internalType":"uint8","name":"","type":"uint8"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"_name","outputs":[{"internalType":"string","name":"","type":"string"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"_symbol","outputs":[{"internalType":"string","name":"","type":"string"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[{"internalType":"address","name":"owner","type":"address"},{"internalType":"address","name":"spender","type":"address"}],"name":"allowance","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"internalType":"address","name":"spender","type":"address"},{"internalType":"uint256","name":"amount","type":"uint256"}],"name":"approve","outputs":[{"internalType":"bool","name":"","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[{"internalType":"address","name":"account","type":"address"}],"name":"balanceOf","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"decimals","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"internalType":"address","name":"spender","type":"address"},{"internalType":"uint256","name":"subtractedValue","type":"uint256"}],"name":"decreaseAllowance","outputs":[{"internalType":"bool","name":"","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[],"name":"getOwner","outputs":[{"internalType":"address","name":"","type":"address"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"internalType":"address","name":"spender","type":"address"},{"internalType":"uint256","name":"addedValue","type":"uint256"}],"name":"increaseAllowance","outputs":[{"internalType":"bool","name":"","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":false,"inputs":[{"internalType":"uint256","name":"amount","type":"uint256"}],"name":"mint","outputs":[{"internalType":"bool","name":"","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[],"name":"name","outputs":[{"internalType":"string","name":"","type":"string"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"owner","outputs":[{"internalType":"address","name":"","type":"address"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[],"name":"renounceOwnership","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[],"name":"symbol","outputs":[{"internalType":"string","name":"","type":"string"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"totalSupply","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"internalType":"address","name":"recipient","type":"address"},{"internalType":"uint256","name":"amount","type":"uint256"}],"name":"transfer","outputs":[{"internalType":"bool","name":"","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":false,"inputs":[{"internalType":"address","name":"sender","type":"address"},{"internalType":"address","name":"recipient","type":"address"},{"internalType":"uint256","name":"amount","type":"uint256"}],"name":"transferFrom","outputs":[{"internalType":"bool","name":"","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":false,"inputs":[{"internalType":"address","name":"newOwner","type":"address"}],"name":"transferOwnership","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"}]';
    }

    public static function getTransactionReceipt(string $rpc_url, string $hash)
    {
        $web3 = new Web3(new HttpProvider(new HttpRequestManager($rpc_url, 2)));
        $ret = [];
        $web3->eth->getTransactionReceipt($hash, function ($err, $data) use (&$ret) {
            if (!$data) {
                Log::error("getTransactionReceipt err", ["err" => $err]);
            } else {
                $ret = json_decode(json_encode($data, 1), true);
            }
        });
        return $ret;
    }

    public static function decodeTransaction($receipt)
    {
        $status = hexdec($receipt["status"]); // 1:success
        $amount = EvmLogic::hexdec2dec($receipt["logs"][0]["data"]);
        $tokenAddress = $receipt["logs"][0]["address"];
        $fromAddress = strtolower(str_replace("0x000000000000000000000000", "0x", $receipt["logs"][0]["topics"][1]));
        $toAddress = strtolower(str_replace("0x000000000000000000000000", "0x", $receipt["logs"][0]["topics"][2]));
        $logIndex = $receipt["logs"][0]["logIndex"];

        return [
            "status" => $status,
            "amount" => $amount,
            "tokenAddress" => $tokenAddress,
            "fromAddress" => $fromAddress,
            "toAddress" => $toAddress,
            "logIndex" => $logIndex,
        ];
    }

    public function transfer($rpc_url, $chain_id, $amount, $token_address, $from_address, $private_key, $to_address)
    {
        //check it is main coin or not
        $main_coin = $token_address ? false : true;
        $amount = Utils::toHex($amount, true);
        $web3 = new Web3(new HttpProvider(new HttpRequestManager($rpc_url, 2)));
        $eth = $web3->eth;
        $contract = new Contract($web3->provider, $this->abi());
        $nonce = 0;

        $web3->eth->getTransactionCount($from_address, "pending", function ($err, $result) use (&$nonce) {
            if ($err !== null) {
                Log::error("transaction count error: " . $err->getMessage());
            } else {
                $nonce = gmp_intval($result->value);
            }
        });

        $gas_price = 0;
        $eth->gasPrice(function ($err, $resp) use (&$gas_price) {
            if ($err !== null) {
                Log::error("gas price error: " . $err->getMessage());
            } else {
                $gas_price = gmp_intval($resp->value);
            }
        });

        $params = [
            "nonce" => $nonce,
            "from" => $from_address,
            "to" => !$main_coin ? $token_address : $to_address,
        ];

        if ($main_coin) {
            $params["to"] = $to_address;
        } else {
            $data = $contract->at($token_address)->getData("transfer", $to_address, $amount);
            $params["to"] = $token_address;
            $params["data"] = $data;
        }

        $es = null;
        if ($main_coin) {
            $es = "21000";
        } else {
            $contract
                ->at($token_address)
                ->estimateGas("transfer", $to_address, $amount, $params, function ($err, $resp) use (&$es) {
                    if ($err) {
                        Log::error("estimate gas error: " . $err->getMessage());
                    } else {
                        $es = $resp->toString();
                    }
                });
        }

        // withdraw gas price multiplier from setting general
        $setting = SettingLogic::get("general", ["category" => "withdraw", "code" => "withdraw_gasprice_multiplier"]);
        $multiply = $setting["value"] ?? 1;

        $nonce = Utils::toHex($nonce, true);
        $gas = Utils::toHex(intval($gas_price * $multiply), true);
        $gas_limit = Utils::toHex($es, true);

        if ($main_coin) {
            $to = $to_address;
            $value = $amount;
            $data = "";
        } else {
            $to = $token_address;
            $value = Utils::toHex(0, true);
            $data = sprintf("0x%s", $data);
        }

        $transaction = new Transaction($nonce, $gas, $gas_limit, $to, $value, $data);

        $signedTransaction = "0x" . $transaction->getRaw($private_key, $chain_id);

        // send signed transaction
        $transactionHash = "";
        $web3->eth->sendRawTransaction($signedTransaction, function ($err, $data) use (&$transactionHash) {
            if ($err != null) {
                Log::error("send raw transaction error: " . $err->getMessage());
            } else {
                $transactionHash = $data;
            }
        });

        return $transactionHash;
    }

    public static function recordReader(
        string $tokenAddress = "",
        string $rpcUrl = "",
        string $fromAddress = null,
        string $toAddress = null,
        int $startBlock = 0,
        int $endBlock = 0
    ) {
        $depositRecords = [];
        $error = 0;

        try {
            $decimal = 18;
            $web3 = new Web3($rpcUrl);
            $action = "0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef"; // transfer

            $topics = [$action];

            $targetFrom = $fromAddress == null
                ? null
                : "0x000000000000000000000000" . str_replace("0x", "", $fromAddress);
            $targetTo = $toAddress == null
                ? null
                : "0x000000000000000000000000" . str_replace("0x", "", $toAddress);
            $topics = [$action, $targetFrom, $targetTo];

            $params = [
                "fromBlock" => "0x" . dechex($startBlock),
                "toBlock" => "0x" . dechex($endBlock),
                "address" => $tokenAddress,
                "topics" => $topics,
            ];

            $filter = null;
            $web3->eth->newFilter($params, function ($err, $data) use (&$filter, &$error) {
                if ($err) {
                    Log::error("web3 eth newFilter: " . $err);
                    $error++;
                } else {
                    $filter = $data;
                }
            });

            $rawRecords = [];
            $web3->eth->getFilterLogs($filter, function ($err, $data) use (&$rawRecords, &$error) {
                if ($err) {
                    Log::error("web3 eth getFilterLogs: " . $err);
                    $error++;
                } else {
                    $rawRecords = $data;
                }
            });

            foreach ($rawRecords as $deposit) {
                $value = self::hexdec2dec($deposit->data, $decimal);

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
        } catch (\Exception $e) {
        }

        if ($error > 0) {
            return false;
        } else {
            return json_encode($depositRecords);
        }
    }

    public static function getHash(string $message)
    {
        // Prefix the message according to Ethereum Signed Message format
        $signMessage = "\x19Ethereum Signed Message:\n" . strlen($message) . strtolower($message);

        // Hash the prefixed message using Keccak-256 hash function
        $hash = "0x" . Keccak::hash($signMessage, 256);

        return $hash;
    }

    public static function signMessage(string $message)
    {
        try {
            $signature = false;

            $contractId = SettingLogic::get("general", ["code" => "nft_contract_id", "category" => "onboarding"]);
            if ($contractId) {

                $contract = SettingLogic::get("nft", ["id" => $contractId["value"]]);
                if ($contract) {
                    $privateKey = HelperLogic::decrypt($contract["private_key"]);

                    // Validate the private key format
                    if (self::isValidPrivateKeyFormat($privateKey)) {
                        $hash = self::getHash($message);

                        // Instantiate Elliptic Curve library with 'secp256k1' curve
                        $ec = new EC('secp256k1');

                        // Convert the private key from hex format to a key object
                        $ecPrivateKey = $ec->keyFromPrivate($privateKey, 'hex');

                        // Sign the hash with the private key using canonical mode
                        $signature = $ecPrivateKey->sign($hash, ['canonical' => true]);

                        // Convert the signature components (r, s, v) to hexadecimal strings
                        $r = str_pad($signature->r->toString(16), 64, '0', STR_PAD_LEFT);
                        $s = str_pad($signature->s->toString(16), 64, '0', STR_PAD_LEFT);
                        $v = dechex($signature->recoveryParam + 27);

                        // Combine r, s, and v components to create the final signature string
                        $signature = "0x" . $r . $s . $v;
                    }
                }
            }

            return $signature;
        } catch (Exception $e) {
            return false;
        }
    }

    private static function isValidPrivateKeyFormat($privateKey)
    {
        $response = false;
        // Check if the string is a valid hexadecimal string
        if (is_string($privateKey)) {
            if (preg_match('/^(0x)?[0-9a-fA-F]{64}$/', $privateKey)) {
                $response = true;
            }
        }
        return $response;
    }
}
