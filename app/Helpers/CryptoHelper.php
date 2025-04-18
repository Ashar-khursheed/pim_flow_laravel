<?php

namespace App\Helpers;

class CryptoHelper
{
    public static function encrypt($plainText, $key)
    {
        $key = self::hextobin(md5($key));
        $iv = "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0"; // 16-byte zero IV

        $encryptedText = openssl_encrypt($plainText, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($encryptedText);
    }

    public static function decrypt($encryptedText, $key)
    {
        $key = self::hextobin(md5($key));
        $iv = "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0"; // 16-byte zero IV

        $encryptedText = base64_decode($encryptedText);
        $decryptedText = openssl_decrypt($encryptedText, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return $decryptedText;
    }

    private static function hextobin($hexString)
    {
        $bin = "";
        for ($i = 0; $i < strlen($hexString); $i += 2) {
            $bin .= chr(hexdec(substr($hexString, $i, 2)));
        }
        return $bin;
    }
}
