<?php

namespace Be\App\Payment;


class Property extends \Be\App\Property
{

    protected string $label = '支付';
    protected string $icon = 'bi-currency-yen';
    protected string $description = '支付管理系统';

    public function __construct() {
        parent::__construct(__FILE__);
    }

}
