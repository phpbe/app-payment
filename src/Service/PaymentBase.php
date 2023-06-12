<?php

namespace Be\App\Payment\Service;

use Be\App\ServiceException;
use Be\Be;
use Be\Db\Tuple;

abstract class PaymentBase
{

    /**
     * 获取账号
     *
     * @return object
     */
    abstract function getAccount(): object;

    /**
     * 标记指定订单为已支付
     *
     * @param object $order
     */
    public function paid(object $order)
    {
        $tupleOrder = Be::getTuple('shop_order');
        try {
            $tupleOrder->load($order->id);
        } catch (\Throwable $t) {
            throw new ServiceException('Order #' . $order->id . ' - ' . $order->order_sn . ' does not exists!');
        }


        if ($tupleOrder->status === 'expired') {

            $serviceOrder = Be::getService('App.Shop.Order');
            $orderConfig = $serviceOrder->getConfig();

            // 订单超时一倍时间，仍可以正常支付成功
            // 订单超时二倍时间，抛出异常
            if (strtotime($tupleOrder->pay_expire_time) + $orderConfig->pay_expire_time < time()) {
                throw new ServiceException('Order #' . $order->id . ' - ' . $order->order_sn . ' payment overtime!');
            }

        } else {
            if ($tupleOrder->status != 'pending') {
                throw new ServiceException('Order #' . $order->id . ' - ' . $order->order_sn . ' status exception!');
            }
        }

        $tupleOrder->is_paid = 1;
        $tupleOrder->status = 'paid';
        $tupleOrder->pay_time = date('Y-m-d H:i:s');
        $tupleOrder->update_time = date('Y-m-d H:i:s');
        $tupleOrder->update();
    }

    /**
     * 支付调用日志
     *
     * @param object $order 订单
     * @param string $url 请求网址
     * @param mixed $request 请求参数
     * @param mixed $response 响应参数
     * @param int $complete 是否支付完成
     * @return Tuple
     */
    public function paymentLog(object $order, string $url, $request, $response, $complete = 0)
    {
        $account = $this->getAccount();

        $tuple = Be::getTuple('shop_payment_log');
        $tuple->payment_type = $account->type ?? '';
        $tuple->payment_id = $account->id;
        $tuple->order_id = $order->id;
        $tuple->order_sn = $order->order_sn;
        $tuple->url = $url;
        $tuple->request = json_encode($request);
        $tuple->response = json_encode($response);
        $tuple->complete = $complete;
        $tuple->create_time = date('Y-m-d H:i:s');
        $tuple->update_time = date('Y-m-d H:i:s');
        $tuple->insert();

        return $tuple;
    }

}
