<?php

namespace Be\App\Payment\Service;

use Be\App\ServiceException;
use Be\Be;

class Payment
{

    /**
     * 获取支付方式
     * @param string $paymentId
     * @return object
     */
    public function getPayment(string $paymentId): object
    {
        $cacht = Be::getCache();
        $key = 'App:Shop:Payment:' . $paymentId;
        $payment = $cacht->get($key);

        if (!$payment) {
            $payment = $this->getPaymentFromDb($paymentId);
            $cacht->set($key, $payment, 600);
        }

        return $payment;
    }

    /**
     * 获取支付方式
     * @param string $paymentId
     * @return object
     */
    public function getPaymentFromDb(string $paymentId): object
    {
        $tuple = Be::getTuple('shop_payment');
        try {
            $tuple->load($paymentId);
        } catch (\Throwable $t) {
            throw new ServiceException('Payment (#' . $paymentId . ') does not exist!');
        }

        $payment = $tuple->toObject();

        $payment->logo = Be::getProperty('App.Shop')->getWwwUrl() . '/images/payment/' . $payment->logo;

        return $payment;
    }

    /**
     * 获取店铺支付方式
     * @param string $paymentId
     * @param string $paymentItemId
     * @return object
     */
    public function getStorePayment(string $paymentId, string $paymentItemId = ''): object
    {
        $payment = $this->getPayment($paymentId);
        if ($payment->is_enable === 0) {
            throw new ServiceException('Current store does support payment (' . $payment->label . ') !');
        }

        $item = null;
        switch ($payment->name) {
            case 'paypal':
                if ($paymentItemId !== '') {
                    $item = Be::getTable('shop_payment_paypal')
                        ->where('is_enable', 1)
                        ->where('id', $paymentItemId)
                        ->getObject();
                } else {
                    $item = Be::getTable('shop_payment_paypal')
                        ->where('is_enable', 1)
                        ->getObject();
                }
                break;
            case 'cod':
                if ($paymentItemId !== '') {
                    $item = Be::getTable('shop_payment_cod')
                        ->where('id', $paymentItemId)
                        ->getObject();
                } else {
                    $item = Be::getTable('shop_payment_cod')
                        ->getObject();
                }
                break;
            default:
                throw new ServiceException('Unknown payment (' . $payment->label . ')!');
        }

        if (!$item) {
            throw new ServiceException('Payment (' . $payment->label . ') does not exist!');
        }

        $payment->item = $item;

        return $payment;
    }

    /**
     * 获取店铺可用的支付方法
     *
     * @param string $shippingPlanId 物流汇道ID
     * @return array
     * @throws \Be\Runtime\RuntimeException
     */
    public function getStorePaymentsByShippingPlanId(string $shippingPlanId): array
    {
        $paymentIds = Be::getTable('shop_payment')
            ->where('is_enable', 1)
            ->getValues('id');

        $payments = [];
        if ($paymentIds) {
            foreach ($paymentIds as $paymentId) {
                $payment = $this->getPayment($paymentId);

                $item = null;
                switch ($payment->name) {
                    case 'paypal':
                        $item = Be::getTable('shop_payment_paypal')
                            ->where('is_enable', 1)
                            ->getObject();
                        break;
                    case 'cod':
                        $shippingPlanCod = Be::getTable('shop_shipping_plan')
                            ->where('id', $shippingPlanId)
                            ->getValue('cod');
                        if ($shippingPlanCod) {
                            $item = Be::getTable('shop_payment_cod')
                                ->getObject();
                        }
                        break;
                    default:
                        throw new ServiceException('Unknown payment (' . $payment->label . ')!');
                }

                if ($item !== null) {
                    $payment->item = $item;
                    $payments[] = $payment;
                }
            }
        }

        return $payments;
    }

    /**
     * 获取店铺可用的支付方法
     *
     * @param string $orderId 订单ID
     * @return array
     * @throws \Be\Runtime\RuntimeException
     */
    public function getStorePaymentsByOrderId(string $orderId = ''): array
    {
        $paymentIds = Be::getTable('shop_payment')
            ->where('is_enable', 1)
            ->getValues('id');

        $payments = [];
        if ($paymentIds) {
            foreach ($paymentIds as $paymentId) {
                $payment = $this->getPayment($paymentId);

                $item = null;
                switch ($payment->name) {
                    case 'paypal':
                        $item = Be::getTable('shop_payment_paypal')
                            ->where('is_enable', 1)
                            ->getObject();
                        break;
                    case 'cod':
                        $shippingPlanId = Be::getTable('shop_order')
                            ->where('id', $orderId)
                            ->getValue('shipping_plan_id');
                        if ($shippingPlanId) {
                            $shippingPlanCod = Be::getTable('shop_shipping_plan')
                                ->where('id', $shippingPlanId)
                                ->getValue('cod');
                            if ($shippingPlanCod) {
                                $item = Be::getTable('shop_payment_cod')
                                    ->getObject();
                            }
                        }
                        break;
                    default:
                        throw new ServiceException('Unknown payment (' . $payment->label . ')!');
                }

                if ($item !== null) {
                    $payment->item = $item;
                    $payment->url = $this->getPaymentUrl($payment->name, $orderId);
                    $payments[] = $payment;
                }
            }
        }

        return $payments;
    }

    /**
     * 获取支付网址
     *
     * @param string $paymentName
     * @param string $orderId
     * @return string
     */
    public function getPaymentUrl(string $paymentName, string $orderId): string
    {
        switch ($paymentName) {
            case 'paypal':
                return beUrl('Shop.PaymentPaypal.pay', ['order_id' => $orderId]);
            case 'cod':
                return beUrl('Shop.PaymentCod.pay', ['order_id' => $orderId]);
            default:
                throw new ServiceException('Unknown payment (' . $paymentName . ')!');
        }
    }

}
