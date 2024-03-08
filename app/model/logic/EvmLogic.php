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
    public static function getBlockNumber(string $rpcUrl)
    {
        $success = 0;
        $web3 = new Web3(new HttpProvider(new HttpRequestManager($rpcUrl, 2)));

        $block = 0;
        $web3->eth->blockNumber(function ($err, $data) use (&$block, &$success) {
            if ($err) {
                Log::error("blockNumber err", ["err" => $err]);
            } else {
                $block = (int) $data->toString();
                $success++;
            }
        });

        if ($success == 1) {
            return $block;
        } else {
            return false;
        }
    }

    public static function hexdec2dec(string $hexValue = "", int $decimalPlaces = 18)
    {
        list($bnq, $bnr) = Utils::fromWei(Utils::toBn($hexValue), "ether");
        return $bnq->toString() . "." . str_pad($bnr->toString(), $decimalPlaces, "0", STR_PAD_LEFT);
    }

    public function getDecimals(string $rpcUrl, string $tokenAddress)
    {
        $web3 = new Web3(new HttpProvider(new HttpRequestManager($rpcUrl, 2)));
        $contract = new Contract($web3->provider, $this->abi());

        $decimal = "0";
        $contract->at($tokenAddress)->call("decimals", function ($err, $data) use (&$decimal) {
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

    public static function getTransactionReceipt(string $rpcUrl, string $hash)
    {
        $web3 = new Web3(new HttpProvider(new HttpRequestManager($rpcUrl, 2)));
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

    public function transfer($rpcUrl, $chainId, $amount, $tokenAddress, $fromAddress, $privateKey, $toAddress)
    {
        $success = 0;
        $transactionHash = "";

        //check it is main coin or not
        $mainCoin = $tokenAddress ? false : true;
        $amount = Utils::toHex($amount, true);
        $web3 = new Web3(new HttpProvider(new HttpRequestManager($rpcUrl, 2)));
        $eth = $web3->eth;
        $contract = new Contract($web3->provider, $this->abi());
        $nonce = 0;

        $web3->eth->getTransactionCount($fromAddress, "pending", function ($err, $result) use (&$nonce, &$success) {
            if ($err) {
                Log::error("transaction count error: " . $err->getMessage());
            } else {
                $nonce = gmp_intval($result->value);
                $success++;
            }
        });

        $gasPrice = 0;
        $eth->gasPrice(function ($err, $resp) use (&$gasPrice, &$success) {
            if ($err) {
                Log::error("gas price error: " . $err->getMessage());
            } else {
                $gasPrice = gmp_intval($resp->value);
                $success++;
            }
        });

        $params = [
            "nonce" => $nonce,
            "from" => $fromAddress,
            "to" => $mainCoin ? $toAddress : $tokenAddress,
        ];

        if (!$mainCoin) {
            $data = $contract->at($tokenAddress)->getData("transfer", $toAddress, $amount);
            $params["data"] = $data;
        }

        $es = null;
        if ($mainCoin) {
            $es = "21000";
        } else {
            $contract
                ->at($tokenAddress)
                ->estimateGas("transfer", $toAddress, $amount, $params, function ($err, $resp) use (&$es, &$success) {
                    if ($err) {
                        Log::error("estimate gas error: " . $err->getMessage());
                    } else {
                        $es = $resp->toString();
                        $success++;
                    }
                });
        }

        if ($success == 3) {
            // withdraw gas price multiplier from setting general
            $setting = SettingLogic::get("general", ["category" => "withdraw", "code" => "withdraw_gasprice_multiplier"]);
            $multiply = $setting["value"] ?? 1;

            $nonce = Utils::toHex($nonce, true);
            $gas = Utils::toHex(intval($gas_price * $multiply), true);
            $gasLimit = Utils::toHex($es, true);

            if ($mainCoin) {
                $to = $toAddress;
                $value = $amount;
                $data = "";
            } else {
                $to = $tokenAddress;
                $value = Utils::toHex(0, true);
                $data = sprintf("0x%s", $data);
            }

            $transaction = new Transaction($nonce, $gas, $gasLimit, $to, $value, $data);

            $signedTransaction = "0x" . $transaction->getRaw($privateKey, $chainId);

            // send signed transaction
            $web3->eth->sendRawTransaction($signedTransaction, function ($err, $data) use (&$transactionHash) {
                if ($err) {
                    Log::error("send raw transaction error: " . $err->getMessage());
                } else {
                    $transactionHash = $data;
                }
            });
        }

        return $transactionHash;
    }

    public static function recordReader(
        string $tokenAddress = "",
        string $rpcUrl = "",
        int $startBlock = 0,
        int $endBlock = 0,
        string $action = "",
        string $fromAddress = "",
        string $toAddress = ""
    ) {
        $recordArray = [];
        $success = 0;

        try {
            $web3 = new Web3(new HttpProvider(new HttpRequestManager($rpcUrl, 2)));

            // topic - works like a filter
            $action = !empty($action) ? $action : null;
            $fromAddress = !empty($fromAddress) ? "0x000000000000000000000000" . str_replace("0x", "", $fromAddress) : null;
            $toAddress = !empty($toAddress) ? "0x000000000000000000000000" . str_replace("0x", "", $toAddress) : null;
            $topics = [$action, $fromAddress, $toAddress];

            $params = [
                "fromBlock" => "0x" . dechex($startBlock),
                "toBlock" => "0x" . dechex($endBlock),
                "address" => $tokenAddress,
                "topics" => $topics,
            ];

            $filter = null;
            $web3->eth->newFilter($params, function ($err, $data) use (&$filter, &$success) {
                if ($err) {
                    Log::error("web3 eth newFilter: " . $err);
                } else {
                    $filter = $data;
                    $success++;
                }
            });

            $rawRecords = [];
            $web3->eth->getFilterLogs($filter, function ($err, $data) use (&$rawRecords, &$success) {
                if ($err) {
                    Log::error("web3 eth getFilterLogs: " . $err);
                } else {
                    $rawRecords = $data;
                    $success++;
                }
            });

            foreach ($rawRecords as $record) {
                $value = self::hexdec2dec($record->data);

                $recordArray[] = [
                    "txid" => $record->transactionHash,
                    "block" => hexdec($record->blockNumber),
                    "event_name" => $record->topics[0],
                    "from_address" => strtolower(str_replace("0x000000000000000000000000", "0x", $record->topics[1])),
                    "to_address" => strtolower(str_replace("0x000000000000000000000000", "0x", $record->topics[2])),
                    "value" => $value,
                    "meta" => $record,
                ];
            }
        } catch (\Exception $e) {
        }

        if ($success == 2) {
            return json_encode($recordArray);
        } else {
            return false;
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
