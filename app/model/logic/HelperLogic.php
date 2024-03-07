<?php
declare(strict_types=1);

namespace app\model\logic;

final class HelperLogic
{
    # generate random keys
    public static function randomCode(int $length = 16, string $type = "mixed-upper"): int|string
    {
        switch ($type) {
            case "int":
                $selections = "0123456789";
                break;

            case "string":
                $selections = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
                break;

            case "string-upper":
                $selections = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
                break;

            case "string-lower":
                $selections = "abcdefghijklmnopqrstuvwxyz";
                break;

            case "mixed-upper":
                $selections = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";
                break;

            case "mixed-lower":
                $selections = "0123456789abcdefghijklmnopqrstuvwxyz";
                break;

            case "mixed":
            default:
                $selections = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
                break;
        }

        $range = strlen($selections);
        $random_string = "";
        for ($i = 0; $i < $length; $i++) {
            $random_string .= $selections[mt_rand(0, $range - 1)];
        }

        return $random_string;
    }

    # generate unique sn
    public static function generateUniqueSN(string $table)
    {
        $isUnique = false;
        $sn = "";
        $column = "sn";

        // sn column name
        if ($table == "account_admin") {
            $column = "admin_id";
        } else if ($table == "account_user") {
            $column = "user_id";
        }

        // Loop until a unique serial number is generated
        while (!$isUnique) {
            $sn = self::randomCode();

            $check = Db::table($table)->where($column, $sn)->first();
            if (!$check) {
                $isUnique = true;
            }
        }

        return $sn;
    }

    # encrypt ssl
    public static function encrypt($data)
    {
        $response = false;

        try {
            $method = "AES-256-CBC";
            $key = env("OPEN_SSL_KEY");
            $options = 0;
            $iv = env("OPEN_SSL_IV");

            $response = openssl_encrypt($data, $method, $key, $options, $iv);
        } catch (\Exception $e) {
        }

        return $response;
    }

    # decrypt ssl
    public static function decrypt($data)
    {
        $response = false;

        try {
            $method = "AES-256-CBC";
            $key = env("OPEN_SSL_KEY");
            $options = 0;
            $iv = env("OPEN_SSL_IV");

            $response = openssl_decrypt($data, $method, $key, $options, $iv);
        } catch (\Exception $e) {
        }

        return $response;
    }
}
