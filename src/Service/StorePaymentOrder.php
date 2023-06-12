<?php

namespace Be\App\Payment\Service;


use Be\App\ServiceException;
use Be\App\Payment\ShopFaiAdmin;
use Be\Be;
use Be\Runtime\RuntimeException;

/**
 * 店铺支出
 */
class StorePaymentOrder
{

    /**
     * 获取状态键值对
     *
     * @return string[]
     */
    public function getStatusKeyValues()
    {
        return [
            'pending' => '待付款',
            'paid' => '已付款',
            'fail' => '付款失败',
            'exception' => '付款成功，但有异常',
            'expired' => '超时',
            'cancelled' => '已取消',
        ];
    }

    /**
     * 是否有指定状态的订单
     *
     * @param $type
     * @return bool
     */
    public function has($type, $status='pending'): bool
    {
        $store = ShopFaiAdmin::getStore();
        $tableStorePaymentOrder = Be::getTable('shopfai_store_payment_order', 'shopfai');
        $tableStorePaymentOrder->where('store_id', $store->id);
        $tableStorePaymentOrder->where('store_order_type', $type);
        $tableStorePaymentOrder->where('status', $status);
        return $tableStorePaymentOrder->count() > 0;
    }

    /**
     * 创建支付单
     *
     * @param string $storeOrderType 店铺订单类型
     * @param string $storeOrderId 店铺订单ID
     * @return Object
     * @throws \Be\Runtime\RuntimeException
     */
    public function create(string $storeOrderType, string $storeOrderId)
    {
        $store = ShopFaiAdmin::getStore();

        $name = null;
        $amount = null;
        $payment = null;
        switch ($storeOrderType) {
            case 'store_vip_order':
                $tupleStoreVipOrder = Be::getTuple('shopfai_store_vip_order', 'shopfai');
                try {
                    $tupleStoreVipOrder->load($storeOrderId);
                } catch (\Throwable $t) {
                    throw new RuntimeException('套餐订单（#' . $storeOrderId . '）不存在！');
                }
                $amount = $tupleStoreVipOrder->amount;
                $payment = $tupleStoreVipOrder->payment;

                $config = Be::getConfig('App.ShopFaiAdmin.Vip');
                $fieldVip = 'vip_' . $tupleStoreVipOrder->vip;
                if ($tupleStoreVipOrder->type === 'change') {
                    $name = '套餐变更为 ' . $config->$fieldVip . ' 并续费' . $tupleStoreVipOrder->months . '个月';
                } else {
                    $name = $config->$fieldVip . ' 套餐续费' . $tupleStoreVipOrder->months . '个月';
                }
                break;
            case 'store_commission_order':
                $tupleStoreCommissionOrder = Be::getTuple('store_commission_order', 'shopfai');
                try {
                    $tupleStoreCommissionOrder->load($storeOrderId);
                } catch (\Throwable $t) {
                    throw new RuntimeException('佣金订单（#' . $storeOrderId . '）不存在！');
                }

                $name = '';
                $amount = $tupleStoreCommissionOrder->amount;
                $payment = $tupleStoreCommissionOrder->payment;
                break;
            default:
                throw new RuntimeException('不支持的单据类型：' . $storeOrderType . '！');
        }

        $now = date('Y-m-d H:i:s');

        $tupleStorePaymentOrder = Be::getTuple('shopfai_store_payment_order', 'shopfai');
        $tupleStorePaymentOrder->store_id = $store->id;
        $tupleStorePaymentOrder->store_order_type = $storeOrderType;
        $tupleStorePaymentOrder->store_order_id = $storeOrderId;
        $tupleStorePaymentOrder->name = $name;
        $tupleStorePaymentOrder->amount = $amount;
        $tupleStorePaymentOrder->payment = $payment;
        $tupleStorePaymentOrder->status = 'pending';
        $tupleStorePaymentOrder->message = '';
        $tupleStorePaymentOrder->create_time = $now;
        $tupleStorePaymentOrder->update_time = $now;
        $tupleStorePaymentOrder->insert();

        return $tupleStorePaymentOrder->toObject();
    }

    /**
     * @param string $paymentOrderId 店铺支付单ID
     * @param string $amount 金额
     * @return void
     * @throws ServiceException
     * @throws \Be\Db\DbException
     * @throws \Be\Runtime\RuntimeException
     */
    public function paid(string $paymentOrderId, string $amount)
    {
        $tupleStorePaymentOrder = Be::getTuple('shopfai_store_payment_order', 'shopfai');
        try {
            $tupleStorePaymentOrder->load($paymentOrderId);
        } catch (\Throwable $t) {
            throw new ServiceException('订单 ' . $paymentOrderId . ' 不存在！');
        }

        // 重复调用
        if ($tupleStorePaymentOrder->status === 'paid') {
            return;
        }

        if ($tupleStorePaymentOrder->amount !== $amount) {
            $tupleStorePaymentOrder->status = 'fail';
            $tupleStorePaymentOrder->message = '实际支付金额与订单金额不匹配！';
            $tupleStorePaymentOrder->update_time = date('Y-m-d H:i:s');
            $tupleStorePaymentOrder->update();
            return;
        }

        try {
            switch ($tupleStorePaymentOrder->store_order_type) {
                case 'store_vip_order':
                    $storeVipOrder = Be::getService('App.ShopFaiAdmin.StoreVipOrder');
                    $storeVipOrder->paid($tupleStorePaymentOrder->store_order_id);
                    break;
                case 'store_commission_order':
                    $storeCommissionOrder = Be::getService('App.ShopFaiAdmin.StoreCommissionOrder');
                    $storeCommissionOrder->paid($tupleStorePaymentOrder->store_order_id);
                    break;
            }

            $tupleStorePaymentOrder->status = 'paid';
            $tupleStorePaymentOrder->message = '支付成功';
            $tupleStorePaymentOrder->update_time = date('Y-m-d H:i:s');
            $tupleStorePaymentOrder->update();

        } catch (\Throwable $t) {
            $tupleStorePaymentOrder->status = 'exception';
            $tupleStorePaymentOrder->message = '支付成功，但后续处理异常：' . $t->getMessage();
            $tupleStorePaymentOrder->update_time = date('Y-m-d H:i:s');
            $tupleStorePaymentOrder->update();
        }

    }

    /**
     * 取消
     *
     * @param string $paymentOrderId 店铺支付单ID
     */
    public function cancel(string $paymentOrderId): bool
    {
        $tupleStorePaymentOrder = Be::getTuple('shopfai_store_payment_order', 'shopfai');
        try {
            $tupleStorePaymentOrder->load($paymentOrderId);
        } catch (\Throwable $t) {
            throw new ServiceException('订单 ' . $paymentOrderId . ' 不存在！');
        }

        if ($tupleStorePaymentOrder->status === 'cancelled') {
            return true;
        }

        if ($tupleStorePaymentOrder->status !== 'pending') {
            throw new ServiceException('订单当前状态不可取消！');
        }

        switch ($tupleStorePaymentOrder->store_order_type) {
            case 'store_vip_order':
                $storeVipOrder = Be::getService('App.ShopFaiAdmin.StoreVipOrder');
                $storeVipOrder->cancel($tupleStorePaymentOrder->store_order_id);
                break;
            case 'store_commission_order':
                $storeCommissionOrder = Be::getService('App.ShopFaiAdmin.StoreCommissionOrder');
                $storeCommissionOrder->cancel($tupleStorePaymentOrder->store_order_id);
                break;
        }

        $result = false;
        switch ($tupleStorePaymentOrder->payment) {
            case 'wechat':
                $serviceStorePaymentWechat = Be::getService('App.ShopFaiAdmin.StorePaymentWechat');
                $result = $serviceStorePaymentWechat->close($tupleStorePaymentOrder->id);
                break;
            case 'alipay':
                // TODO
                //$serviceStorePaymentAlipay = Be::getService('App.ShopFaiAdmin.StorePaymentAlipay');
                //$result = $serviceStorePaymentAlipay->cancel($tupleStorePaymentOrder->id);
                break;
        }

        if ($result) {
            $tupleStorePaymentOrder->status = 'cancelled';
            $tupleStorePaymentOrder->message = '';
            $tupleStorePaymentOrder->update_time = date('Y-m-d H:i:s');
            $tupleStorePaymentOrder->update();
        }

        return $result;
    }

}
