<?php

namespace Be\App\Payment\Controller;

use Be\Be;

class Payment extends Base
{

    /**
     * 选择支付方式
     *
     * @BeRoute("/payment/pay")
     */
    public function pay()
    {
        $request = Be::getRequest();
        $response = Be::getResponse();

        $orderId = $request->get('order_id');
        $order = Be::getService('App.Shop.Order')->getOrder($orderId);

        if ($order->status != 'pending') {
            $response->redirect(beUrl('Shop.Order.detail', ['order_id' => $orderId]));
            return;
        }

        $response->set('order', $order);

        $servicePayment = Be::getService('App.Shop.Payment');

        if ($order->payment_id !== '') {
            $storePayment = $servicePayment->getStorePayment($order->payment_id);
            $url = $servicePayment->getPaymentUrl($storePayment->name, $orderId);
            $response->redirect($url);
            return;
        }

        $storePayments = $servicePayment->getStorePaymentsByOrderId($orderId);
        $count = count($storePayments);
        if ($count === 0) {
            $response->error('No available payments!');
            return;
        } elseif ($count === 1) {
            $storePayment = $storePayments[0];
            Be::getService('App.Shop.Order')->setPayment($orderId, $storePayment->id, $storePayment->item->id);
            $response->redirect($storePayment->url);
            return;
        }

        $response->set('storePayments', $storePayments);

        $response->display();
    }

    /**
     * 改变支付方式
     *
     * @BeRoute("/payment/change")
     */
    public function change()
    {
        $request = Be::getRequest();
        $response = Be::getResponse();

        $orderId = $request->get('order_id');
        $order = Be::getService('App.Shop.Order')->getOrder($orderId);

        if ($order->status != 'pending') {
            $response->redirect(beUrl('Shop.Order.detail', ['order_id' => $orderId]));
            return;
        }

        $response->set('order', $order);

        $servicePayment = Be::getService('App.Shop.Payment');

        $storePayments = $servicePayment->getStorePaymentsByOrderId($orderId);
        $count = count($storePayments);
        if ($count === 0) {
            $response->error('No available payments!');
            return;
        }

        $response->set('storePayments', $storePayments);

        $response->display();
    }

    /**
     * 支付方式确认
     *
     * @BeRoute("/payment/confirm")
     */
    public function confirm()
    {
        $request = Be::getRequest();
        $response = Be::getResponse();

        $orderId = $request->post('order_id');
        $paymentId = $request->post('payment_id');
        $paymentItemId = $request->post('payment_item_id');

        try {
            $storePayment = Be::getService('App.Shop.Order')->setPayment($orderId, $paymentId, $paymentItemId);
            $response->set('success', true);
            $response->set('message', 'Confirm payment success!');

            $redirectUrl = Be::getService('App.Shop.Payment')->getPaymentUrl($storePayment->name, $orderId);
            $response->set('redirectUrl', $redirectUrl);

            $response->json();
        } catch (\Throwable $t) {
            $response->set('success', false);
            $response->set('message', $t->getMessage());
            $response->json();
        }
    }

    /**
     * 支付完成页面
     *
     * @BeRoute("/payment/success")
     */
    public function success()
    {
        $request = Be::getRequest();
        $response = Be::getResponse();

        $orderId = $request->get('order_id');
        $order = Be::getService('App.Shop.Order')->getOrder($orderId);
        $response->set('order', $order);

        $response->display();
    }

    /**
     * Ajax 获取支付方式
     *
     * @BeRoute("/payment/get-store-payments-by-shipping-plan-id")
     */
    public function getStorePaymentsByShippingPlanId()
    {
        $request = Be::getRequest();
        $response = Be::getResponse();

        $shippingPlanId = $request->post('shipping_plan_id');

        $servicePayment = Be::getService('App.Shop.Payment');
        $storePayments = $servicePayment->getStorePaymentsByShippingPlanId($shippingPlanId);
        $count = count($storePayments);
        if ($count === 0) {
            $response->set('success', false);
            $response->set('message', 'No available payments!');
            $response->json();
            return;
        }

        $response->set('storePayments', $storePayments);

        $response->set('success', true);
        $response->set('message', 'Fetch store payments success!');
        $response->json();
    }

}

