<?php
namespace Be\App\Payment\Config;

/**
 * @BeConfig("微信支付")
 */
class Wechat
{

    /**
     * @BeConfigItem("商户号", driver="FormItemInput")
     */
    public string $merchantId = '';

    /**
     * @BeConfigItem("商户的证书序列号",  description="在证书管理中查看，https://pay.weixin.qq.com/index.php/core/cert/api_cert#/api-cert-manage",  driver="FormItemInput")
     */
    public string $merchantCertSerial = '';

    /**
     * @BeConfigItem("商户的证书私钥", description="即通过证书工具生成的 apiclient_key.pem", driver="FormItemInputTextArea")
     */
    public string $merchantPrivateKey = '';

    /**
     * @BeConfigItem("微信支付平台证书公钥", description="可通过SDK中的微信支付平台证书下载器生成，https://github.com/wechatpay-apiv3/wechatpay-php#%E5%A6%82%E4%BD%95%E4%B8%8B%E8%BD%BD%E5%B9%B3%E5%8F%B0%E8%AF%81%E4%B9%A6", driver="FormItemInputTextArea")
     */
    public string $platformPublicKey = '';

    /**
     * @BeConfigItem("APIv3密钥", description="在 商户平台->账户中心->API安全 中设置，https://pay.weixin.qq.com/index.php/core/cert/api_cert#/", driver="FormItemInput")
     */
    public string $apiv3Key = '';

    /**
     * @BeConfigItem("公众号的APPID", driver="FormItemInput")
     */
    public string $appId = '';

    /**
     * @BeConfigItem("回调地址", driver="FormItemInput")
     */
    public string $notifyUrl = '';


}
