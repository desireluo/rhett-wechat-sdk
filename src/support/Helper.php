<?php
declare(strict_types=1);

namespace RhettWechatSdk\support;

use WeChatPay\Crypto\Rsa;

class Helper
{
    public static function getCertSerialNo($filePath)
    {
        $cert = file_get_contents('1900009191_20180326_cert.pem');

        $certData = openssl_x509_parse($cert);

        if ($certData !== false && isset($certData['serialNumberHex'])) {
            $serialNumber = $certData['serialNumberHex'];
            echo $serialNumber;
        } else {
            echo 'Failed to retrieve serial number';
        }

    }
}