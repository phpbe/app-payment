<?php

namespace Be\App\Payment\Controller;

use Be\Be;

class PaymentCod extends Base
{

    /**
     *
     * @BeRoute("/payment/cod")
     */
    public function pay()
    {
        $request = Be::getRequest();
        $response = Be::getResponse();
        $orderId = $request->get('order_id');
        $response->redirect(beUrl('Shop.Order.detail', ['order_id' => $orderId]));
    }


}

