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
    public static function abi()
    {
        return '[{"inputs":[],"payable":false,"stateMutability":"nonpayable","type":"constructor"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"owner","type":"address"},{"indexed":true,"internalType":"address","name":"spender","type":"address"},{"indexed":false,"internalType":"uint256","name":"value","type":"uint256"}],"name":"Approval","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"previousOwner","type":"address"},{"indexed":true,"internalType":"address","name":"newOwner","type":"address"}],"name":"OwnershipTransferred","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"from","type":"address"},{"indexed":true,"internalType":"address","name":"to","type":"address"},{"indexed":false,"internalType":"uint256","name":"value","type":"uint256"}],"name":"Transfer","type":"event"},{"constant":true,"inputs":[],"name":"_decimals","outputs":[{"internalType":"uint8","name":"","type":"uint8"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"_name","outputs":[{"internalType":"string","name":"","type":"string"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"_symbol","outputs":[{"internalType":"string","name":"","type":"string"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[{"internalType":"address","name":"owner","type":"address"},{"internalType":"address","name":"spender","type":"address"}],"name":"allowance","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"internalType":"address","name":"spender","type":"address"},{"internalType":"uint256","name":"amount","type":"uint256"}],"name":"approve","outputs":[{"internalType":"bool","name":"","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[{"internalType":"address","name":"account","type":"address"}],"name":"balanceOf","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"decimals","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"internalType":"address","name":"spender","type":"address"},{"internalType":"uint256","name":"subtractedValue","type":"uint256"}],"name":"decreaseAllowance","outputs":[{"internalType":"bool","name":"","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[],"name":"getOwner","outputs":[{"internalType":"address","name":"","type":"address"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"internalType":"address","name":"spender","type":"address"},{"internalType":"uint256","name":"addedValue","type":"uint256"}],"name":"increaseAllowance","outputs":[{"internalType":"bool","name":"","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":false,"inputs":[{"internalType":"uint256","name":"amount","type":"uint256"}],"name":"mint","outputs":[{"internalType":"bool","name":"","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[],"name":"name","outputs":[{"internalType":"string","name":"","type":"string"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"owner","outputs":[{"internalType":"address","name":"","type":"address"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[],"name":"renounceOwnership","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[],"name":"symbol","outputs":[{"internalType":"string","name":"","type":"string"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"totalSupply","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"internalType":"address","name":"recipient","type":"address"},{"internalType":"uint256","name":"amount","type":"uint256"}],"name":"transfer","outputs":[{"internalType":"bool","name":"","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":false,"inputs":[{"internalType":"address","name":"sender","type":"address"},{"internalType":"address","name":"recipient","type":"address"},{"internalType":"uint256","name":"amount","type":"uint256"}],"name":"transferFrom","outputs":[{"internalType":"bool","name":"","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":false,"inputs":[{"internalType":"address","name":"newOwner","type":"address"}],"name":"transferOwnership","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"}]';
    }

    # convert hex decimal value to decimal
    public static function hexdecimalToDecimal(string $hexValue = "", int $decimalPlaces = 18)
    {
        list($bnq, $bnr) = Utils::fromWei(Utils::toBn($hexValue), "ether");
        return $bnq->toString() . "." . str_pad($bnr->toString(), $decimalPlaces, "0", STR_PAD_LEFT);
    }

    # get decimal of the token address / contract
    public static function getDecimals(string $rpcUrl, string $tokenAddress)
    {
        $web3 = new Web3(new HttpProvider(new HttpRequestManager($rpcUrl, 2)));
        $contract = new Contract($web3->provider, self::abi());

        $decimal = 0;
        $contract->at($tokenAddress)->call("decimals", function ($err, $data) use (&$decimal) {
            if ($err !== null) {
                Log::error("getDecimals err", ["err" => $err]);
            } else {
                $decimal = (int) $data[0]->toString();
            }
        });

        return $decimal;
    }

    # get balance of address in that contract (for token and ERC-721 nft only)
    # if token: count balance
    # if nft: ERC-721 (count number of nft)
    public static function getBalance(string $type, string $rpcUrl, string $tokenAddress, string $address)
    {
        $web3 = new Web3(new HttpProvider(new HttpRequestManager($rpcUrl, 2)));
        $contract = new Contract($web3->provider, self::abi());

        $balance = 0;
        $contract->at($tokenAddress)->call("balanceOf", $address, function ($err, $data) use (&$type, &$balance) {
            if ($err !== null) {
                Log::error("getBalance err", ["err" => $err]);
            } else {
                $value = gmp_strval($data[0]->value);
                if ($type == "token") {
                    $balance = self::hexdecimalToDecimal($value);
                } else if ($type == "nft") {
                    $balance = $value;
                }
            }
        });

        return $balance;
    }

    # get current block number of the rpc
    public static function getBlockNumber(string $rpcUrl)
    {
        $web3 = new Web3(new HttpProvider(new HttpRequestManager($rpcUrl, 2)));

        $block = 0;
        $web3->eth->blockNumber(function ($err, $data) use (&$block, &$success) {
            if ($err !== null) {
                Log::error("blockNumber err", ["err" => $err]);
            } else {
                $block = (int) $data->toString();
                $success++;
            }
        });

        return $block;
    }

    # get transaction info of the txid
    public static function getTransactionReceipt(string $rpcUrl, string $txid)
    {
        $web3 = new Web3(new HttpProvider(new HttpRequestManager($rpcUrl, 2)));

        $ret = [];
        $web3->eth->getTransactionReceipt($txid, function ($err, $data) use (&$ret) {
            if ($err !== null) {
                Log::error("getTransactionReceipt err", ["err" => $err]);
            } else {
                $ret = json_decode(json_encode($data, 1), true);
            }
        });

        return $ret;
    }

    # decode transaction info
    public static function decodeTransaction($receipt)
    {
        $status = hexdec($receipt["status"]); // 1:success
        $amount = EvmLogic::hexdecimalToDecimal($receipt["logs"][0]["data"]);
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

    # send to chain and return txid (withdraw)
    public static function transfer($rpcUrl, $chainId, $amount, $tokenAddress, $fromAddress, $privateKey, $toAddress)
    {
        $success = 0;
        $transactionHash = "";

        //check it is main coin or not
        $mainCoin = $tokenAddress ? false : true;
        $amount = Utils::toHex($amount, true);
        $web3 = new Web3(new HttpProvider(new HttpRequestManager($rpcUrl, 2)));
        $eth = $web3->eth;
        $contract = new Contract($web3->provider, self::abi());
        $nonce = 0;

        $web3->eth->getTransactionCount($fromAddress, "pending", function ($err, $result) use (&$nonce, &$success) {
            if ($err !== null) {
                Log::error("transaction count error: " . $err->getMessage());
            } else {
                $nonce = gmp_intval($result->value);
                $success++;
            }
        });

        $gasPrice = 0;
        $eth->gasPrice(function ($err, $resp) use (&$gasPrice, &$success) {
            if ($err !== null) {
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
                    if ($err !== null) {
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
            $gas = Utils::toHex(intval($gasPrice * $multiply), true);
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
                if ($err !== null) {
                    Log::error("send raw transaction error: " . $err->getMessage());
                } else {
                    $transactionHash = $data;
                }
            });
        }

        return $transactionHash;
    }

    # processor transaction reader
    public static function recordReader(
        string $tokenAddress = "",
        string $rpcUrl = "",
        int $startBlock = 0,
        int $endBlock = 0,
        string $action = "",
        string $fromAddress = "",
        string $toAddress = ""
    ) {
        $filter = null;
        $recordArray = [];
        $rawRecords = [];
        $success = 0;

        try {
            $web3 = new Web3(new HttpProvider(new HttpRequestManager($rpcUrl, 2)));

            // topics - act as a filter parameter
            // transfer (ERC-721 NFTs & common type of transfer) - 0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef
            // single transfer (ERC-1155 NFTs) = 0xc3d58168c5ae7397731d063d5bbf3d657854427343f4c083240f7aacaa2d0f62
            // if nft - from is 0x0000000000000000000000000000000000000000, to is user
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

            $web3->eth->newFilter($params, function ($err, $data) use (&$filter, &$success) {
                if ($err !== null) {
                    Log::error("web3 eth newFilter: " . $err);
                } else {
                    $filter = $data;
                    $success++;
                }
            });

            if (!empty($filter)) {
                $web3->eth->getFilterLogs($filter, function ($err, $data) use (&$rawRecords, &$success) {
                    if ($err !== null) {
                        // always trigger error due to rpc problem so commented out
                        // Log::error("web3 eth getFilterLogs: " . $err);
                    } else {
                        $rawRecords = $data;
                        $success++;
                    }
                });
            }

            if (count($rawRecords) > 0) {
                foreach ($rawRecords as $record) {
                    $value = self::hexdecimalToDecimal($record->data);

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
            }
        } catch (\Exception $e) {
        }

        if ($success == 2) {
            return json_encode($recordArray);
        } else {
            return false;
        }
    }

    # nft sign message
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
                        $hash = self::hashMessage($message);

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

    # hash the message
    private static function hashMessage(string $message)
    {
        // Prefix the message according to Ethereum Signed Message format
        $signMessage = "\x19Ethereum Signed Message:\n" . strlen($message) . strtolower($message);

        // Hash the prefixed message using Keccak-256 hash function
        $hash = "0x" . Keccak::hash($signMessage, 256);

        return $hash;
    }

    # check private key validity
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
