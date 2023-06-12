<?php

namespace Be\App\Payment\Controller;

use Be\Be;

class PaymentPaypal extends Base
{

    /**
     *
     * @BeRoute("/payment/paypal")
     */
    public function pay()
    {
        $request = Be::getRequest();
        $response = Be::getResponse();

        $orderId = $request->get('order_id');
        $order = Be::getService('App.Shop.Order')->getOrder($orderId, ['shipping_address' => 1, 'billing_address' => 1, 'products' => 1]);
        if ($order->status != 'pending') {
            $response->redirect(beUrl('Shop.Order.detail', ['order_id' => $orderId]));
            return;
        }

        $servicePaymentPaypal = Be::getService('App.Shop.PaymentPaypal');
        $configPaymentPaypal = $servicePaymentPaypal->getConfig();
        if ($configPaymentPaypal->pop) {
            $response->set('order', $order);

            $account = $servicePaymentPaypal->getAccount();
            $response->set('account', $account);

            $response->display();
        } else {
            $response->redirect(beUrl('Shop.PaymentPaypal.create', ['order_id' => $orderId]));
        }
    }

    /**
     * 支付
     *
     * @BeRoute("/payment/paypal/create")
     */
    public function create()
    {
        $request = Be::getRequest();
        $response = Be::getResponse();

        $orderId = $request->get('order_id');
        $order = Be::getService('App.Shop.Order')->getOrder($orderId, ['shipping_address' => 1, 'billing_address' => 1, 'products' => 1]);

        $servicePaymentPaypal = Be::getService('App.Shop.PaymentPaypal');
        $configPaymentPaypal = $servicePaymentPaypal->getConfig();
        if ($configPaymentPaypal->pop) {
            // JS ajax 请求 createOrder
            if ($order->status != 'pending') {
                $response->set('success', false);
                $response->set('message', 'Order status exception!');
                $response->json();
                return;
            }

            try {
                $paypalResponse = $servicePaymentPaypal->create($order);
                $response->set('success', true);
                $response->set('message', '');
                $response->set('data', $paypalResponse);
                $response->json();
            } catch (\Throwable $t) {
                $response->set('success', false);
                $response->set('message', $t->getMessage());
                $response->json();
            }
        } else {
            if ($order->status != 'pending') {
                $response->redirect(beUrl('Shop.Order.detail', ['order_id' => $orderId]));
                return;
            }

            $paypalResponse = $servicePaymentPaypal->create($order);
            $response->redirect($paypalResponse['links'][1]['href']);
        }
    }

    /**
     * 支付成功
     *
     * @BeRoute("/payment/paypal/approve")
     */
    public function approve()
    {
        $request = Be::getRequest();
        $response = Be::getResponse();

        $orderId = $request->get('order_id');
        $order = Be::getService('App.Shop.Order')->getOrder($orderId);

        $servicePaymentPaypal = Be::getService('App.Shop.PaymentPaypal');
        $configPaymentPaypal = $servicePaymentPaypal->getConfig();
        if ($configPaymentPaypal->pop) {

            try {

                $paypalOrderId = $request->json('paypal_order_id');
                $paypalPayerID = $request->json('paypal_payer_id');

                $paypalResponse = $servicePaymentPaypal->approve($order, $paypalOrderId, $paypalPayerID);
                $response->set('success', true);
                $response->set('message', '');
                $response->set('data', $paypalResponse);
                $response->json();
            } catch (\Throwable $t) {
                $response->set('success', false);
                $response->set('message', $t->getMessage());
                $response->json();
            }
        } else {

            $response->set('order', $order);

            try {
                $paypalOrderId = $request->get('token');
                $paypalPayerID = $request->get('PayerID');

                // 确认订单已支付
                $paypalResponse = $servicePaymentPaypal->approve($order, $paypalOrderId, $paypalPayerID);

                $response->set('success', true);
                $response->set('message', '');
                $response->set('data', $paypalResponse);

            } catch (\Throwable $t) {
                $response->set('success', false);
                $response->set('message', $t->getMessage());
            }

            $response->display();
        }
    }


}

