<?php

namespace Be\App\Payment\Service\Admin;

class Payment
{

    /**
     * 获取支付方式列表
     * @return array
     */
    public function getTypes(): array
    {
        return [
            'Paypal',
            'GooglePay',
            'ApplePay',
            'Alipay',
            'Wechat',
        ];
    }


}
