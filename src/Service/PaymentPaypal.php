<?php

namespace Be\App\Payment\Service;

use Be\App\ServiceException;
use Be\App\Payment\Shop;
use Be\Be;
use Be\Util\Net\Curl;

class PaymentPaypal extends PaymentBase
{

    protected $account = null;

    private $accessToken = null;

    private $baseUrl = 'https://api.paypal.com';
    //private $baseUrl = 'https://api.sandbox.paypal.com';


    /**
     * 标记指定订单为已支付
     *
     * @param $order
     */
    public function paid($order)
    {
        parent::paid($order);
    }

    /**
     * 获取账号
     *
     * @return object
     * @throws ServiceException
     * @throws \Be\Db\DbException
     * @throws \Be\Runtime\RuntimeException
     */
    public function getAccount(): object
    {
        if ($this->account === null) {
            $db = Be::getDb();
            $sql = 'SELECT * FROM shop_payment_paypal WHERE is_enable = 1';
            $account = $db->getObject($sql);

            if (!$account) {
                throw new ServiceException('No matched paypal payment account!');
            }

            $account->type = 'paypal';

            $this->account = $account;
        }

        return $this->account;
    }

    /**
     * 生成支付
     *
     * @param $order
     * @return mixed
     */
    public function create($order)
    {

        $postData = [];
        $postData['intent'] = 'CAPTURE';

        $postData['application_context'] = [
            //'brand_name' => 'EXAMPLE INC',
            //'locale' => 'en-US',
            //'landing_page' => 'BILLING',
            'shipping_preference' => 'SET_PROVIDED_ADDRESS',
            'user_action' => 'PAY_NOW',
        ];

        $configPaymentPaypal = Be::getConfig('App.Shop.PaymentPaypal');
        if (!$configPaymentPaypal->pop) {
            $postData['application_context']['return_url'] = beUrl('Shop.PaymentPaypal.approve', ['order_id' => $order->id]);
            $postData['application_context']['cancel_url'] = beUrl('Shop.PaymentPaypal.cancel', ['order_id' => $order->id]);
        }

        $items = [];
        foreach ($order->products as $product) {
            $items[] = [
                'name' => $product->product_name,
                'description' => $product->product_name,
                'sku' => $product->sku ?: ($product->spu ?: $product->product_id),
                'unit_amount' => [
                    'currency_code' => 'USD',
                    'value' => $product->price,
                ],
                'quantity' => $product->quantity,
                'category' => 'PHYSICAL_GOODS',
            ];
        }

        $postData['purchase_units'] = [
            [
                'reference_id' => $order->id,
                'description' => $order->order_sn,
                'invoice_id' => $order->id,
                'custom_id' => $order->id,
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => $order->amount,
                    'breakdown' => [
                        'item_total' => [
                            'currency_code' => 'USD',
                            'value' => $order->product_amount,
                        ],
                        'shipping' => [
                            'currency_code' => 'USD',
                            'value' => $order->shipping_fee,
                        ],
                        'tax_total' => [
                            'currency_code' => 'USD',
                            'value' => 0,
                        ],
                        'handling' => [
                            'currency_code' => 'USD',
                            'value' => 0,
                        ],
                        'shipping_discount' => [
                            'currency_code' => 'USD',
                            'value' => 0,
                        ],
                        'insurance' => [
                            'currency_code' => 'USD',
                            'value' => 0,
                        ]
                    ]
                ],

                'items' => $items,
                'shipping' => [
                    'name' => [
                        'full_name' => $order->shipping_address->first_name . ' ' . $order->shipping_address->last_name,
                    ],
                    'address' => [
                        'address_line_1' => $order->shipping_address->address,
                        'address_line_2' => $order->shipping_address->address2,
                        'admin_area_1' => $order->shipping_address->state_name,
                        'admin_area_2' => $order->shipping_address->city,
                        'postal_code' => $order->shipping_address->zip_code,
                        'country_code' => $order->shipping_address->country_code,
                    ]
                ]
            ]
        ];

        $url = $this->baseUrl . '/v2/checkout/orders';
        $headers = ['Authorization:Bearer ' . $this->getAccessToken()];
        $response = Curl::postJson($url, $headers, $postData);
        $response = json_decode($response);

        $this->paymentLog($order, $url, $postData, $response);

        if (!isset($response->status)) {
            if (isset($response->error_description) && is_string($response->error_description)) {
                $message = $response->error_description;
            } elseif (isset($response->error) && is_string($response->error)) {
                $message = $response->error;
            } else {
                $message = 'Paypal create order error!';
            }

            throw new ServiceException($message);
        }

        if ($response->status !== 'pending') {
            throw new ServiceException('Paypal create order status exception!');
        }

        if (!isset($response->id)) {
            throw new ServiceException('Paypal create order response data exception!');
        }

        if ($response) {
            $account = $this->getAccount();
            $tuple = Be::getTuple('shop_payment_paypal_order');
            $tuple->payment_paypal_id = $account->id;
            $tuple->order_id = $order->id;
            $tuple->order_sn = $order->order_sn;
            $tuple->paypal_order_id = $response->id;
            $tuple->paypal_status = $response->status;
            $tuple->create_time = date('Y-m-d H:i:s');
            $tuple->insert();
        }

        return $response;
    }

    /**
     * @param object $order
     * @param $paymentInfo
     */
    public function approve(object $order, string $paypalOrderId, string $paypalPayerId = '')
    {
        $tuplePaypalOrder = Be::getTuple('shop_payment_paypal_order');
        try {
            $tuplePaypalOrder->loadBy([
                'order_id' => $order->id,
                'paypal_order_id' => $paypalOrderId
            ]);
        } catch (\Throwable $t) {
            throw new ServiceException('Order #' . $order->id . ' (Sn: ' . $order->order_sn . ', Paypal order id: ' . $paypalOrderId . ') does not exists!');
        }

        if ($paypalPayerId) {
            $tuplePaypalOrder->paypal_payer_id = $paypalPayerId;
            $tuplePaypalOrder->update_time = date('Y-m-d H:i:s');
            $tuplePaypalOrder->update();
        }

        $url = $this->baseUrl . '/v2/checkout/orders/' . $paypalOrderId . '/capture';
        $headers = ['Authorization:Bearer ' . $this->getAccessToken()];
        $response = Curl::postJson($url, $headers, []);
        $response = json_decode($response);

        $tuplePaymentLog = $this->paymentLog($order, $url, [], $response);

        if (!isset($response->status)) {
            if (isset($response->error_description) && is_string($response->error_description)) {
                $message = $response->error_description;
            } elseif (isset($response->error) && is_string($response->error)) {
                $message = $response->error;
            } else {
                $message = 'Paypal capture order error!';
            }

            throw new ServiceException($message);
        }

        if ($response->status !== 'COMPLETED') {
            throw new ServiceException('Paypal capture order status exception!');
        }

        if (!isset($response->id)) {
            throw new ServiceException('Paypal capture order response data exception!');
        }

        $this->paid($order);

        $tuplePaypalOrder->paypal_status = $response->status;
        $tuplePaypalOrder->paypal_payer_first_name = $response->payer->name->given_name ?? '';
        $tuplePaypalOrder->paypal_payer_last_name = $response->payer->name->surname ?? '';
        $tuplePaypalOrder->paypal_payer_email = $response->payer->email_address ?? '';
        $tuplePaypalOrder->paypal_payer_country_code = $response->payer->address->country_code ?? '';
        $tuplePaypalOrder->update_time = date('Y-m-d H:i:s');
        $tuplePaypalOrder->update();

        $tuplePaymentLog->complete = 1;
        $tuplePaymentLog->update_time = date('Y-m-d H:i:s');
        $tuplePaymentLog->update();

        return $response;
    }

    /**
     * 获取 paypal 订单
     *
     * @param object $order 订单 信息
     * @param string $paypalOrderId paypal 订单ID
     * @return mixed
     * @throws ServiceException
     * @throws \Be\Db\TupleException
     */
    public function getOrder(object $order, string $paypalOrderId)
    {
        $url = $this->baseUrl . '/v2/checkout/orders/' . $paypalOrderId;
        $headers = [
            'Content-Type:application/json',
            'Authorization:Bearer ' . $this->getAccessToken(),
        ];
        $response = Curl::get($url, $headers);

        $response = json_decode($response, true);

        $this->paymentLog($order, $url, [], $response);

        if (!isset($response->status)) {
            if (isset($response->error_description) && is_string($response->error_description)) {
                $message = $response->error_description;
            } elseif (isset($response->error) && is_string($response->error)) {
                $message = $response->error;
            } else {
                $message = 'Paypal get order error!';
            }

            throw new ServiceException($message);
        }

        if (!isset($response->id)) {
            throw new ServiceException('Paypal get order response data exception!');
        }

        return $response;
    }

    /**
     * 更新 paypal 订单
     *
     * @param $array
     * @return mixed
     */
    public function updateOrder($paypalOrderId)
    {

    }

    /**
     * 获取有效的 Access Token
     * @return string
     * @throws \Be\Db\TupleException
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken === null) {
            $account = $this->getAccount();

            $tuple = Be::getTuple('shop_payment_paypal_token');
            try {
                $tuple->loadBy('payment_paypal_id', $account->id);
            } catch (\Throwable $t) {
                // Access Token 不存在，生成
                $accessToken = $this->newAccessToken();

                $tuple->payment_paypal_id = $account->id;
                $tuple->access_token = $accessToken->access_token;
                $tuple->expire_time = date('Y-m-d H:i:s', (time() + $accessToken->expires_in));
                $tuple->create_time = date('Y-m-d H:i:s');
                $tuple->update_time = date('Y-m-d H:i:s');
                $tuple->insert();

                $this->accessToken = $accessToken->access_token;
            }

            // Access Token 超时，更新
            if (strtotime($tuple->expire_time) <= time()) {
                $accessToken = $this->newAccessToken();
                $tuple->access_token = $accessToken->access_token;
                $tuple->expire_time = date('Y-m-d H:i:s', (time() + $accessToken->expires_in));
                $tuple->update_time = date('Y-m-d H:i:s');
                $tuple->update();
            }

            $this->accessToken = $tuple->access_token;
        }

        return $this->accessToken;
    }

    /**
     * 调用 Paypal 接口生成新的 Access Token
     */
    private function newAccessToken()
    {
        $url = $this->baseUrl . '/v1/oauth2/token';

        $data = [
            'grant_type' => 'client_credentials'
        ];

        $headers = ['Content-type: application/x-www-form-urlencoded'];

        $account = $this->getAccount();
        $options = [
            CURLOPT_USERPWD => $account->client_id . ':' . $account->secret
        ];

        $response = Curl::patch($url, $data, $headers, $options);

        return json_decode($response);
    }


}
