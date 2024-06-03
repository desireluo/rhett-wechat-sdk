<?php
declare(strict_types=1);

namespace RhettWechatSdk;

use RhettWechatSdk\support\ErrorHandler;
use WeChatPay\Builder;
use WeChatPay\BuilderChainable;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Util\PemUtil;

class RhettWeChatFactory
{
    /** @var BuilderChainable $instance */
    private static $instance;
    private static $platformCertificateSerial;
    public static $config = [
        'app_id' => '',
        'mch_id' => '',
        'key' => '',  // API v3 密钥
        // API证书路径
        'cert_path' => '', //绝对路径
        'key_path' => '',      //绝对路径
        'platformCertificateFilePath' => '',
        'notify_url' => '',
    ];

    private static $platformPublicKeyInstance;


    /**
     * @param $config
     */
    public static function payment($config)
    {
        if (isset(self::$instance)) {
            return self::$instance;
        }
        self::$config = $config;

        $merchantId = self::$config['mch_id'];

// 从本地文件中加载「商户API私钥」，「商户API私钥」会用来生成请求的签名
        $merchantPrivateKeyFilePath = 'file://' . self::$config['key_path'];
        $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath, Rsa::KEY_TYPE_PRIVATE);

// 「商户API证书」的「证书序列号」
        $merchantPublicKeyFilePath = 'file://' . self::$config['cert_path'];
        $merchantCertificateSerial = PemUtil::parseCertificateSerialNo($merchantPublicKeyFilePath);

// 从本地文件中加载「微信支付平台证书」，用来验证微信支付应答的签名
        $platformCertificateFilePath = 'file://' . self::$config['platformCertificateFilePath'];
        $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);

// 从「微信支付平台证书」中获取「证书序列号」
        $platformCertificateSerial = PemUtil::parseCertificateSerialNo($platformCertificateFilePath);
        self::$platformCertificateSerial = $platformCertificateSerial;
        self::$platformPublicKeyInstance = $platformPublicKeyInstance;
// 构造一个 APIv3 客户端实例
        $instance = Builder::factory([
            'mchid' => $merchantId,
            'serial' => $merchantCertificateSerial,
            'privateKey' => $merchantPrivateKeyInstance,
            'certs' => [
                $platformCertificateSerial => $platformPublicKeyInstance,
            ],
        ]);
        self::$instance = $instance;
        return new self();
    }

    public function native($outTradeNo, $amount, $description = '', $notify_url = '', $currency = 'CNY'): array
    {
        try {

            // 发送请求
            $resp = self::$instance->chain('v3/pay/transactions/native')->post(['json' => [
                'mchid' => self::$config['mch_id'],
                'out_trade_no' => $outTradeNo,
                'appid' => self::$config['app_id'],
                'description' => $description,
                'notify_url' => $notify_url ?: self::$config['notify_url'],
                'amount' => [
                    'total' => $amount,
                    'currency' => $currency
                ],
            ]]);
            return ['status' => true, 'body' => $resp->getBody()->getContents(), 'message' => ''];
        } catch (\Throwable $e) {
            return ErrorHandler::handleException($e);
        }
    }

    public function prepay($outTradeNo, $openId, $amount, $description = '', $notify_url = '', $currency = 'CNY'): array
    {
        try {

            // 发送请求
            $resp = self::$instance->chain('/v3/pay/transactions/jsapi')->post(['json' => [
                'mchid' => self::$config['mch_id'],
                'out_trade_no' => $outTradeNo,
                'appid' => self::$config['app_id'],
                'description' => $description ?: '下单',
                'notify_url' => $notify_url ?: self::$config['notify_url'],
                'amount' => [
                    'total' => $amount,
                    'currency' => $currency
                ],
                'payer' => [
                    'openid' => $openId,
                ]
            ]]);
            return ['status' => true, 'body' => $resp->getBody()->getContents(), 'message' => ''];
        } catch (\Throwable $e) {
            return ErrorHandler::handleException($e);
        }
    }

    public function transfer($outBatchNo, $batchName, $batchRemark, $totalAmount, $totalNum, $transferDetailList): array
    {
        $platformPublicKeyInstance = self::$platformPublicKeyInstance;
        $encryptor = static function (string $msg) use ($platformPublicKeyInstance): string {
            return Rsa::encrypt($msg, $platformPublicKeyInstance);
        };
        try {
            // 发送请求
            $resp = self::$instance->chain('/v3/transfer/batches')->post(['json' => [
                'mchid' => self::$config['mch_id'],
                'out_batch_no' => $outBatchNo,
                'appid' => self::$config['app_id'],
                'batch_name' => $batchName,
                'batch_remark' => $batchRemark,
                'total_amount' => $totalAmount,
                'total_num' => $totalNum,
                'transfer_detail_list' => $transferDetailList,
            ], 'headers' => [
                'Wechatpay-Serial' => self::$platformCertificateSerial,
            ],]);
            return ['status' => true, 'body' => $resp->getBody()->getContents(), 'message' => ''];
        } catch (\Throwable $e) {
            return ErrorHandler::handleException($e);
        }

    }

}