<?php

namespace Be\App\Payment\Service;

use Be\App\ServiceException;
use Be\App\Payment\ShopFaiAdmin;
use Be\Be;
use Be\Util\Str\Uuid;
use WeChatPay\Builder;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;
use WeChatPay\Util\PemUtil;

/**
 * 微信支付
 */
class PaymentWechat
{

    /**
     * 发起支付
     *
     * @param string $paymentOrderId 店铺支付订单ID
     * @return array
     * @throws ServiceException
     * @throws \Be\Db\TupleException
     * @throws \Be\Runtime\RuntimeException
     * @throws \Throwable
     */
    public function pay(string $paymentOrderId)
    {
        $tupleStorePaymentOrder = Be::getTuple('shopfai_store_payment_order', 'shopfai');
        try {
            $tupleStorePaymentOrder->load($paymentOrderId);
        } catch (\Throwable $t) {
            throw new ServiceException('店铺支付单' . $paymentOrderId . '不存在！');
        }

        // 状态检查
        if ($tupleStorePaymentOrder->status !== 'pending') {
            throw new ServiceException('店铺支付单' . $paymentOrderId . '状态异常！');
        }

        $store = ShopFaiAdmin::getStore();
        $tupleStorePaymentWechatLog = Be::getTuple('shopfai_store_payment_wechat_log', 'shopfai');
        $tupleStorePaymentWechatLog->store_id = $store->id;
        $tupleStorePaymentWechatLog->store_payment_order_id = $paymentOrderId;

        $config = Be::getConfig('App.ShopFaiAdmin.Wechat');
        $outTradeNo = Uuid::strip($paymentOrderId);
        $requestUrl = 'v3/pay/transactions/native';
        $requestData = ['json' => [
            'mchid' => $config->merchantId,
            'out_trade_no' => $outTradeNo,
            'appid' => $config->appId,
            'description' => $tupleStorePaymentOrder->name,
            'notify_url' => $config->notifyUrl,
            'amount' => [
                'total' => (int)($tupleStorePaymentOrder->amount * 100),
                'currency' => 'CNY'
            ],
        ]];

        $tupleStorePaymentWechatLog->request_url = $requestUrl;
        $tupleStorePaymentWechatLog->request_data = json_encode($requestData);
        $tupleStorePaymentWechatLog->response_data = '';
        $tupleStorePaymentWechatLog->status = 'fail';
        $tupleStorePaymentWechatLog->message = '';

        $now = date('Y-m-d H:i:s');
        $tupleStorePaymentWechatLog->create_time = $now;
        $tupleStorePaymentWechatLog->update_time = $now;

        $return = [];
        try {

            // 从本地文件中加载「微信支付平台证书」，用来验证微信支付应答的签名
            $platformPublicKeyInstance = Rsa::from($config->platformPublicKey, Rsa::KEY_TYPE_PUBLIC);

            // 从「微信支付平台证书」中获取「证书序列号」
            $platformCertificateSerial = PemUtil::parseCertificateSerialNo($config->platformPublicKey);

            // 构造一个 APIv3 客户端实例
            $instance = Builder::factory([
                'mchid' => $config->merchantId,
                'serial' => $config->merchantCertSerial,
                'privateKey' => Rsa::from($config->merchantPrivateKey, Rsa::KEY_TYPE_PRIVATE),
                'certs' => [
                    $platformCertificateSerial => $platformPublicKeyInstance,
                ],
            ]);

            $res = $instance
                ->chain($requestUrl)
                ->post($requestData);
            $resStr = $res->getBody()->getContents();

            $tupleStorePaymentWechatLog->response_data = $resStr;

            $resData = json_decode($resStr, true);
            if ($resData && isset($resData['code_url']) && is_string($resData['code_url'])) {
                $return = $resData;
                $tupleStorePaymentWechatLog->status = 'success';
            } else {
                $tupleStorePaymentWechatLog->status = 'fail';
                $tupleStorePaymentWechatLog->message = '微信支付接口返回的数据无法识别';
            }

            $tupleStorePaymentWechatLog->insert();

        } catch (\Throwable $t) {
            Be::getLog()->error($t);

            $tupleStorePaymentWechatLog->status = 'fail';

            $message = $t->getMessage();
            if (mb_strlen($message) > 200) {
                $message = mb_substr($message, 0, 200);
            }
            $tupleStorePaymentWechatLog->message = $message;

            $tupleStorePaymentWechatLog->insert();

            throw $t;
        }

        return $return;
    }

    /**
     * 关闭
     *
     * @param string $paymentOrderId 店铺支付订单ID
     * @return bool
     */
    public function close(string $paymentOrderId): bool
    {
        $tupleStorePaymentOrder = Be::getTuple('shopfai_store_payment_order', 'shopfai');
        try {
            $tupleStorePaymentOrder->load($paymentOrderId);
        } catch (\Throwable $t) {
            throw new ServiceException('店铺支付单' . $paymentOrderId . '不存在！');
        }

        // 状态检查
        if ($tupleStorePaymentOrder->status !== 'pending') {
            throw new ServiceException('店铺支付单' . $paymentOrderId . '状态异常！');
        }

        $tupleStorePaymentWechatLog = Be::getTuple('shopfai_store_payment_wechat_log', 'shopfai');
        $tupleStorePaymentWechatLog->store_id = $tupleStorePaymentOrder->store_id;
        $tupleStorePaymentWechatLog->store_payment_order_id = $paymentOrderId;

        $config = Be::getConfig('App.ShopFaiAdmin.Wechat');
        $outTradeNo = Uuid::strip($paymentOrderId);
        $requestUrl = 'v3/pay/transactions/out-trade-no/' . $outTradeNo . '/close';
        $requestData = ['mchid' => $config->merchantId];

        $tupleStorePaymentWechatLog->request_url = $requestUrl;
        $tupleStorePaymentWechatLog->request_data = json_encode($requestData);
        $tupleStorePaymentWechatLog->response_data = '';
        $tupleStorePaymentWechatLog->status = 'fail';
        $tupleStorePaymentWechatLog->message = '';

        $now = date('Y-m-d H:i:s');
        $tupleStorePaymentWechatLog->create_time = $now;
        $tupleStorePaymentWechatLog->update_time = $now;

        $return = false;
        try {

            // 从本地文件中加载「微信支付平台证书」，用来验证微信支付应答的签名
            $platformPublicKeyInstance = Rsa::from($config->platformPublicKey, Rsa::KEY_TYPE_PUBLIC);

            // 从「微信支付平台证书」中获取「证书序列号」
            $platformCertificateSerial = PemUtil::parseCertificateSerialNo($config->platformPublicKey);

            // 构造一个 APIv3 客户端实例
            $instance = Builder::factory([
                'mchid' => $config->merchantId,
                'serial' => $config->merchantCertSerial,
                'privateKey' => Rsa::from($config->merchantPrivateKey, Rsa::KEY_TYPE_PRIVATE),
                'certs' => [
                    $platformCertificateSerial => $platformPublicKeyInstance,
                ],
            ]);

            $res = $instance
                ->chain($requestUrl)
                ->post($requestData);

            $statusCode = $res->getStatusCode();
            $resStr = $res->getBody()->getContents();
            $tupleStorePaymentWechatLog->response_data = $statusCode . ': ' .$resStr;

            if ($statusCode === 204) {
                $return = true;

                $tupleStorePaymentWechatLog->status = 'success';
                $tupleStorePaymentWechatLog->message = '关闭成功';
            } else {
                $tupleStorePaymentWechatLog->status = 'fail';
                $tupleStorePaymentWechatLog->message = '关闭失败：错误码：' . $statusCode;
            }

            $tupleStorePaymentWechatLog->insert();

        } catch (\Throwable $t) {

            $tupleStorePaymentWechatLog->status = 'fail';

            $message = '';
            if ($t instanceof \GuzzleHttp\Exception\RequestException && $t->hasResponse()) {
                $r = $t->getResponse();
                if ($r->getStatusCode() === 400) {
                    $tupleStorePaymentWechatLog->status = 'success';
                    $tupleStorePaymentWechatLog->message = '关闭成功';
                    $tupleStorePaymentWechatLog->insert();
                    return true;
                }

                $message = '# ' . $r->getStatusCode();
            } else {
                Be::getLog()->error($t);

                $message = $t->getMessage();
                if (mb_strlen($message) > 200) {
                    $message = mb_substr($message, 0, 200);
                }
            }

            $tupleStorePaymentWechatLog->message = $message;
            $tupleStorePaymentWechatLog->insert();

            throw $t;
        }

        if ($tupleStorePaymentWechatLog->status !== 'success') {
            throw new ServiceException($tupleStorePaymentWechatLog->message);
        }

        return $return;
    }

    /**
     * 检查
     *
     * @param string $paymentOrderId 店铺支付订单ID
     * @return bool
     */
    public function check(string $paymentOrderId): bool
    {
        $tupleStorePaymentOrder = Be::getTuple('shopfai_store_payment_order', 'shopfai');
        try {
            $tupleStorePaymentOrder->load($paymentOrderId);
        } catch (\Throwable $t) {
            throw new ServiceException('店铺支付单' . $paymentOrderId . '不存在！');
        }

        // 重复调用
        if ($tupleStorePaymentOrder->status === 'paid') {
            return true;
        }

        $tupleStorePaymentWechatLog = Be::getTuple('shopfai_store_payment_wechat_log', 'shopfai');
        $tupleStorePaymentWechatLog->store_id = $tupleStorePaymentOrder->store_id;
        $tupleStorePaymentWechatLog->store_payment_order_id = $paymentOrderId;

        $config = Be::getConfig('App.ShopFaiAdmin.Wechat');
        $outTradeNo = Uuid::strip($paymentOrderId);
        $requestUrl = 'v3/pay/transactions/out-trade-no/' . $outTradeNo;
        $requestData = [
            'query' => ['mchid' => $config->merchantId],
        ];

        $tupleStorePaymentWechatLog->request_url = $requestUrl;
        $tupleStorePaymentWechatLog->request_data = json_encode($requestData);
        $tupleStorePaymentWechatLog->status = 'fail';
        $tupleStorePaymentWechatLog->message = '';

        $now = date('Y-m-d H:i:s');
        $tupleStorePaymentWechatLog->create_time = $now;
        $tupleStorePaymentWechatLog->update_time = $now;

        $return = false;
        try {

            // 从本地文件中加载「微信支付平台证书」，用来验证微信支付应答的签名
            $platformPublicKeyInstance = Rsa::from($config->platformPublicKey, Rsa::KEY_TYPE_PUBLIC);

            // 从「微信支付平台证书」中获取「证书序列号」
            $platformCertificateSerial = PemUtil::parseCertificateSerialNo($config->platformPublicKey);

            // 构造一个 APIv3 客户端实例
            $instance = Builder::factory([
                'mchid' => $config->merchantId,
                'serial' => $config->merchantCertSerial,
                'privateKey' => Rsa::from($config->merchantPrivateKey, Rsa::KEY_TYPE_PRIVATE),
                'certs' => [
                    $platformCertificateSerial => $platformPublicKeyInstance,
                ],
            ]);

            $res = $instance
                ->chain($requestUrl)
                ->get($requestData);
            $resStr = $res->getBody()->getContents();

            $tupleStorePaymentWechatLog->response_data = $resStr;

           // {"amount":{"payer_currency":"CNY","total":6000},"appid":"wx01b9966bdabe253e","mchid":"1622988185","out_trade_no":"ff39ecfb4333457480c083c88bf291f2","promotion_detail":[],"scene_info":{"device_id":""},"trade_state":"NOTPAY","trade_state_desc":"订单未支付"}
            $resData = json_decode($resStr, true);
            if (is_array($resData) && isset($resData['trade_state']) && is_string($resData['trade_state'])) {
                if ($resData['trade_state'] === 'SUCCESS') {
                    $amount = number_format(((int)$resData['amount']['total']) / 100, 2, '.', '');
                    try {
                        // 防止短时间内并发 重复执行
                        $redis = Be::getRedis();
                        $key = 'ShopFaiAdmin:StorePaymentWechat:paid:' . $paymentOrderId;
                        if ($redis->set($key, 1, ['nx', 'ex' => 600])) {
                            $paymentOrder = Be::getService('App.ShopFaiAdmin.StorePaymentOrder');
                            $paymentOrder->paid($paymentOrderId, $amount);
                        }

                        $tupleStorePaymentWechatLog->status = 'success';;
                        $tupleStorePaymentWechatLog->message = '支付成功';

                        $return = true;
                    } catch (\Throwable $t) {
                        $tupleStorePaymentWechatLog->status = 'exception';;
                        $tupleStorePaymentWechatLog->message = '支付成功，但后续处理异常' . $t->getMessage();
                    }
                } else {
                    $tupleStorePaymentWechatLog->status = 'fail';

                    if (isset($resData['trade_state_desc'])) {
                        $message = $resData['trade_state_desc'];
                    } else {
                        $message = '订单未支付';
                    }
                    $tupleStorePaymentWechatLog->message = $message;
                }
            } else {
                $tupleStorePaymentWechatLog->status = 'fail';
                $tupleStorePaymentWechatLog->message = '微信支付接口返回的数据无法识别';
            }

            $tupleStorePaymentWechatLog->insert();

        } catch (\Throwable $t) {

            Be::getLog()->error($t);

            $tupleStorePaymentWechatLog->status = 'fail';

            $message = $t->getMessage();
            if (strlen($message) > 200) {
                $message = substr($message, 0, 200);
            }
            $tupleStorePaymentWechatLog->message = $message;

            $tupleStorePaymentWechatLog->insert();

            throw $t;
        }

        if ($tupleStorePaymentWechatLog->status !== 'success') {
            throw new ServiceException($tupleStorePaymentWechatLog->message);
        }

        return $return;
    }


    /**
     * 回调
     *
     * @param array $headers 请求头
     * @param string $body 请求体内容
     * @return void
     */
    public function notify(array $headers, string $body)
    {

        $now = date('Y-m-d H:i:s');

        $tupleStorePaymentWechatNotifyLog = Be::getTuple('shopfai_store_payment_wechat_notify_log', 'shopfai');
        $tupleStorePaymentWechatNotifyLog->store_id = '';
        $tupleStorePaymentWechatNotifyLog->store_payment_order_id = '';
        $tupleStorePaymentWechatNotifyLog->header = json_encode($headers);
        $tupleStorePaymentWechatNotifyLog->body = $body;
        $tupleStorePaymentWechatNotifyLog->data = '';
        $tupleStorePaymentWechatNotifyLog->status = 'unknown';   // 状态
        $tupleStorePaymentWechatNotifyLog->message = '';   // 消息
        $tupleStorePaymentWechatNotifyLog->create_time = $now;
        $tupleStorePaymentWechatNotifyLog->update_time = $now;
        $tupleStorePaymentWechatNotifyLog->insert();

        $config = Be::getConfig('App.ShopFaiAdmin.Wechat');

        /*
         * header 数据：
        [accept] => * / *
        [wechatpay-signature-type] => WECHATPAY2-SHA256-RSA2048
        [wechatpay-nonce] => iUeBszoWLtVnnpP1n6CkwL9CLoMsgJDp
        [wechatpay-serial] => 7D2BABF84AEEB14C64A495EB00EC86C5FA5AB9FA
        [connection] => Keep-Alive
        [wechatpay-signature] => uyomsLkkkrtPYnH52vBL2ckBlLJ7nbb2PjF9yCgCjdbEqUg0geCKI+/Id7HkogGGD/0BuZesa96whSJE1j8AKrVJeLyTl4KIoMOm4SX7MaY8mNXHKzHVxwPHLyf108klhCtTkjChOfvmbLFDUVmR4r1k47i5fH9z+LJQJpsnbm1ke+X2HnYL0OXfqJzzKPWVhn6XFzbRizgA5NqVsBOy7pZVMwAtZW6ueFh/ox/fGepHPuy+iqw3Ir8cgvfGlEE9KlTXDGanJWcdctbKRqqzmZcebrHqLeBWT/xhAKo6W+Jch68R4jDrHpWB91QI0VAb5f0sf7NaVYEqKS7T/9CQ6g==
        [wechatpay-timestamp] => 1647437308
        [pragma] => no-cache
        [user-agent] => Mozilla/4.0
        [host] => 24118ze59.qicp.vip
        */

        $inWechatpaySignature = $headers['wechatpay-signature'] ?? ''; // 请根据实际情况获取
        $inWechatpayTimestamp = $headers['wechatpay-timestamp'] ?? ''; // 请根据实际情况获取
        $inWechatpaySerial = $headers['wechatpay-serial'] ?? ''; // 请根据实际情况获取，适用于有多个证书时，
        $inWechatpayNonce = $headers['wechatpay-nonce'] ?? ''; // 请根据实际情况获取
        $inBody = $body;; // 请根据实际情况获取，例如: file_get_contents('php://input');

        $apiv3Key = $config->apiv3Key;// 在商户平台上设置的APIv3密钥

        $platformPublicKeyInstance = Rsa::from($config->platformPublicKey, Rsa::KEY_TYPE_PUBLIC);

        // 检查通知时间偏移量，允许5分钟之内的偏移
        $timeOffsetStatus = 300 >= abs(Formatter::timestamp() - (int)$inWechatpayTimestamp);
        if (!$timeOffsetStatus) {
            $tupleStorePaymentWechatNotifyLog->status = 'fail';
            $tupleStorePaymentWechatNotifyLog->message = '通知时间异常';
            $tupleStorePaymentWechatNotifyLog->update();

            throw new ServiceException('通知时间异常');
        }

        $verifiedStatus = Rsa::verify(
        // 构造验签名串
            Formatter::joinedByLineFeed($inWechatpayTimestamp, $inWechatpayNonce, $inBody),
            $inWechatpaySignature,
            $platformPublicKeyInstance
        );

        if (!$verifiedStatus) {
            $tupleStorePaymentWechatNotifyLog->status = 'fail';
            $tupleStorePaymentWechatNotifyLog->message = '证书验签失败！';
            $tupleStorePaymentWechatNotifyLog->update();

            throw new ServiceException('证书验签失败');
        }

        // 转换通知的JSON文本消息为PHP Array数组
        $inBodyArray = (array)json_decode($inBody, true);
        // 使用PHP7的数据解构语法，从Array中解构并赋值变量
        ['resource' => [
            'ciphertext' => $ciphertext,
            'nonce' => $nonce,
            'associated_data' => $aad
        ]] = $inBodyArray;
        // 加密文本消息解密
        $inBodyResource = AesGcm::decrypt($ciphertext, $apiv3Key, $nonce, $aad);
        // 把解密后的文本转换为PHP Array数组
        $inBodyResourceArray = (array)json_decode($inBodyResource, true);
        /*
         *
$inBodyResourceArray: Array
(
    [mchid] => 1622988185
    [appid] => wx01b9966bdabe253e
    [out_trade_no] => shopfai-20220317103831
    [transaction_id] => 4200001305202203177491830178
    [trade_type] => NATIVE
    [trade_state] => SUCCESS
    [trade_state_desc] => 支付成功
    [bank_type] => OTHERS
    [attach] =>
    [success_time] => 2022-03-17T10:38:48+08:00
    [payer] => Array
        (
            [openid] => ohU5i6E4sZ2Ruz6RV4QMH8bxT-fM
        )

    [amount] => Array
        (
            [total] => 1
            [payer_total] => 1
            [currency] => CNY
            [payer_currency] => CNY
        )

)
         */

        $tupleStorePaymentWechatNotifyLog->data = json_encode($inBodyResourceArray);

        // 支付成功
        if ($inBodyResourceArray['trade_state'] === 'SUCCESS') {
            $outTradeNo = $inBodyResourceArray['out_trade_no'];
            $paymentOrderId = Uuid::restore($outTradeNo);
            $tupleStorePaymentWechatNotifyLog->store_payment_order_id = $paymentOrderId;

            try {
                $tupleStorePaymentOrder = Be::getTuple('shopfai_store_payment_order', 'shopfai');
                $tupleStorePaymentOrder->load($paymentOrderId);
                $tupleStorePaymentWechatNotifyLog->store_id = $tupleStorePaymentOrder->store_id;
            } catch (\Throwable $t) {
            }

            $amount = number_format(((int)$inBodyResourceArray['amount']['total']) / 100, 2, '.', '');
            try {

                // 防止短时间内并发 重复执行
                $redis = Be::getRedis();
                $key = 'ShopFaiAdmin:StorePaymentWechat:paid:' . $paymentOrderId;
                if ($redis->set($key, 1, ['nx', 'ex' => 600])) {
                    $paymentOrder = Be::getService('App.ShopFaiAdmin.StorePaymentOrder');
                    $paymentOrder->paid($paymentOrderId, $amount);
                }

                $tupleStorePaymentWechatNotifyLog->status = 'success';
                $tupleStorePaymentWechatNotifyLog->message = '支付成功';
            } catch (\Throwable $t) {
                $tupleStorePaymentWechatNotifyLog->status = 'exception';
                $tupleStorePaymentWechatNotifyLog->message = '支付成功，但后续处理异常：' . $t->getMessage();
            }
        } else {
            $tupleStorePaymentWechatNotifyLog->status = 'fail';
            $tupleStorePaymentWechatNotifyLog->message = '支付失败：微信支付返回结果中状态（trade_state）非 SUCCESS';
        }

        $tupleStorePaymentWechatNotifyLog->update();

        if ($tupleStorePaymentWechatNotifyLog->status !== 'success') {
            throw new ServiceException($tupleStorePaymentWechatNotifyLog->message);
        }
    }

}
