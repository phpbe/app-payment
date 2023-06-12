<?php
namespace Be\App\Payment\Config;

/**
 * @BeConfig("PayPal支付")
 */
class Paypal
{

    /**
     * @BeConfigItem("REST API 客户ID", driver="FormItemInput")
     */
    public string $clientId = '';

    /**
     * @BeConfigItem("REST API 密钥", driver="FormItemInput")
     */
    public string $secret = '';

    /**
     * @BeConfigItem("弹窗支付", description="开启后，使用Paypal支付时，页面不跳转，在弹出的窗口中进行PayPal支付", driver="FormItemSwitch")
     */
    public int $pop = 0;


}
