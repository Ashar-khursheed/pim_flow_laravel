<?php
namespace App\Helpers;

class CcavenueHelper
{
    public static function encrypt($plainText, $workingKey)
    {
        $key = hex2bin(md5($workingKey));
        $initVector = pack("C*", ...range(0, 15));
        $encryptedText = openssl_encrypt($plainText, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $initVector);
        return bin2hex($encryptedText);
    }

    public static function decrypt($encryptedText, $workingKey)
    {
        $key = hex2bin(md5($workingKey));
        $initVector = pack("C*", ...range(0, 15));
        $encryptedText = hex2bin($encryptedText);
        return openssl_decrypt($encryptedText, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $initVector);
    }
}
