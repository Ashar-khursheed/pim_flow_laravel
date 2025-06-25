<?php

namespace App\Services;

class CCAvenueService
{
    private $workingKey;
    private $accessCode;
    private $merchantId;
    private $baseUrl;

    public function __construct()
    {
        $this->workingKey = config('ccavenue.working_key');
        $this->accessCode = config('ccavenue.access_code');
        $this->merchantId = config('ccavenue.merchant_id');
        
        $environment = config('ccavenue.environment');
        $this->baseUrl = $environment === 'production' 
            ? config('ccavenue.production_url')
            : config('ccavenue.test_url');
    }

    /**
     * Encrypt payment data using AES-128-CBC
     */
    public function encrypt($plainText)
    {
        $secretKey = $this->hextobin(md5($this->workingKey));
        $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
        
        $plainPad = $this->pkcs5Pad($plainText, 16);
        $encryptedText = openssl_encrypt($plainPad, 'AES-128-CBC', $secretKey, OPENSSL_RAW_DATA, $initVector);
        
        return bin2hex($encryptedText);
    }

    /**
     * Decrypt response data using AES-128-CBC
     */
    public function decrypt($encryptedText)
    {
        $secretKey = $this->hextobin(md5($this->workingKey));
        $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
        
        $encryptedText = $this->hextobin($encryptedText);
        $decryptedText = openssl_decrypt($encryptedText, 'AES-128-CBC', $secretKey, OPENSSL_RAW_DATA, $initVector);
        
        return rtrim($decryptedText, "\0");
    }

    /**
     * PKCS5 Padding
     */
    private function pkcs5Pad($plainText, $blockSize)
    {
        $pad = $blockSize - (strlen($plainText) % $blockSize);
        return $plainText . str_repeat(chr($pad), $pad);
    }

    /**
     * Convert hex to binary
     */
    private function hextobin($hexString)
    {
        $length = strlen($hexString);
        $binString = "";
        $count = 0;
        
        while ($count < $length) {
            $subString = substr($hexString, $count, 2);
            $packedString = pack("H*", $subString);
            
            if ($count == 0) {
                $binString = $packedString;
            } else {
                $binString .= $packedString;
            }
            
            $count += 2;
        }
        
        return $binString;
    }

    /**
     * Generate payment URL
     */
    public function generatePaymentUrl($merchantData)
    {
        $encryptedData = $this->encrypt($merchantData);
        return $this->baseUrl . '?command=initiateTransaction&encRequest=' . $encryptedData . '&access_code=' . $this->accessCode;
    }

    /**
     * Parse payment response
     */
    public function parseResponse($encryptedResponse)
    {
        $decryptedString = $this->decrypt($encryptedResponse);
        $decryptValues = explode('&', $decryptedString);
        
        $responseData = [];
        foreach ($decryptValues as $value) {
            $information = explode('=', $value, 2);
            if (count($information) == 2) {
                $responseData[$information[0]] = urldecode($information[1]);
            }
        }
        
        return $responseData;
    }

    /**
     * Get merchant ID
     */
    public function getMerchantId()
    {
        return $this->merchantId;
    }
}
