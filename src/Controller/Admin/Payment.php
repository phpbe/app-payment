<?php

namespace Be\App\Payment\Controller\Admin;

use Be\AdminPlugin\Detail\Item\DetailItemCode;
use Be\AdminPlugin\Detail\Item\DetailItemToggleIcon;
use Be\AdminPlugin\Form\Item\FormItemDatePickerRange;
use Be\AdminPlugin\Form\Item\FormItemSelect;
use Be\AdminPlugin\Table\Item\TableItemCustom;
use Be\AdminPlugin\Table\Item\TableItemImage;
use Be\AdminPlugin\Table\Item\TableItemLink;
use Be\AdminPlugin\Table\Item\TableItemSwitch;
use Be\AdminPlugin\Table\Item\TableItemToggleIcon;
use Be\AdminPlugin\Toolbar\Item\ToolbarItemDropDown;
use Be\AdminPlugin\Toolbar\Item\ToolbarItemLink;
use Be\App\ControllerException;
use Be\App\System\Controller\Admin\Auth;
use Be\Be;
use Be\Db\Tuple;

/**
 * 物流运费
 *
 */
class Payment extends Auth
{

    /**
     * 收款
     *
     * @BeMenu("支付方式", icon="el-icon-bank-card", ordering="7.2")
     * @BePermission("支付方式", ordering="7.2")
     */
    public function payments()
    {
        Be::getAdminPlugin('Curd')->setting([
            'label' => '收款',
            'table' => 'payment',
            'grid' => [
                'title' => '收款',
                'titleToolbar' => [
                    'items' => [
                        [
                            'label' => '收款记录',
                            'driver' => ToolbarItemLink::class,
                            'action' => 'logs',
                            'target' => 'self',
                            'ui' => [
                                'icon' => 'el-icon-tickets',
                            ],
                        ],
                    ],
                ],

                'table' => [

                    'items' => [
                        [
                            'name' => 'logo',
                            'label' => '',
                            'width' => '180',
                            'driver' => TableItemImage::class,
                            'ui' => [
                                'style' => 'width:150px; padding: 20px 10px',
                            ],
                            'value' => function($row) {
                                return Be::getProperty('App.Shop')->getWwwUrl() . '/images/payment/' . $row['logo'];
                            },
                        ],
                        [
                            'name' => 'label',
                            'label' => '收款方式',
                            'width' => '150',
                        ],
                        [
                            'name' => 'description',
                            'label' => '',
                            'align' => 'left',
                            'driver' => TableItemCustom::class,
                        ],
                        [
                            'name' => 'is_enable',
                            'label' => '是否启用',
                            'driver' => TableItemSwitch::class,
                            'target' => 'ajax',
                            'task' => 'fieldEdit',
                            'width' => '90',
                        ],
                    ],

                    'operation' => [
                        'label' => '管理',
                        'width' => '100',
                        'items' => [
                            [
                                'label' => '',
                                'tooltip' => '配置',
                                'action' => 'setting',
                                'target' => '_self',
                                'ui' => [
                                    'type' => 'primary',
                                    ':underline' => 'false',
                                    'style' => 'font-size: 20px;',
                                    'v-if' => 'scope.row.is_enable===\'1\' && scope.row.name!==\'cod\'',
                                ],
                                'icon' => 'el-icon-setting',
                            ],
                        ]
                    ],

                ],
            ],

            'fieldEdit' => [
                'events' => [
                    'before' => function ($tuple) {
                        $tuple->update_time = date('Y-m-d H:i:s');
                    },
                    'success' => function () {
                        $postData = Be::getRequest()->json();
                        $field = $postData['postData']['field'];
                        if ($field === 'is_enable') {
                            $value = (int)$postData['row']['is_enable'];
                            if ($value === 1) {
                                Be::getService('App.Shop.Admin.Store')->setUp(4);
                            }
                        }
                    },
                ],
            ],

        ])->execute();
    }

    /**
     * 收款记录
     *
     * @BePermission("收款记录", ordering="7.21")
     */
    public function logs()
    {
        Be::getAdminPlugin('Curd')->setting([

            'label' => '收款记录',
            'table' => 'payment_log',

            'grid' => [
                'title' => '收款记录',

                'filter' => [
                ],

                'orderBy' => 'create_time',
                'orderByDir' => 'DESC',

                'form' => [
                    'items' => [
                        [
                            'name' => 'order_sn',
                            'label' => '订单号',
                        ],
                        [
                            'name' => 'create_time',
                            'label' => '时间',
                            'driver' => FormItemDatePickerRange::class,
                        ],
                        [
                            'name' => 'complete',
                            'label' => '是否支付完成',
                            'driver' => FormItemSelect::class,
                            'keyValues' => [
                                '0' => '未完成',
                                '1' => '完成',
                            ],
                        ],
                    ],
                ],

                'titleToolbar' => [

                    'items' => [
                        [
                            'label' => '导出',
                            'driver' => ToolbarItemDropDown::class,
                            'ui' => [
                                'icon' => 'el-icon-download',
                            ],
                            'menus' => [
                                [
                                    'label' => 'CSV',
                                    'url' => beAdminUrl('Shop.Payment.logs', ['task' => 'export']),
                                    'postData' => [
                                        'driver' => 'csv',
                                    ],
                                    'target' => 'blank',
                                ],
                                [
                                    'label' => 'EXCEL',
                                    'url' => beAdminUrl('Shop.Payment.logs', ['task' => 'export']),
                                    'postData' => [
                                        'driver' => 'excel',
                                    ],
                                    'target' => 'blank',
                                ],
                            ],
                        ],
                    ]
                ],

                'titleRightToolbar' => [
                    'items' => [
                        [
                            'label' => '返回',
                            'url' => beAdminUrl('Shop.Payment.payments'),
                            'target' => 'self', // 'ajax - ajax请求 / dialog - 对话框窗口 / drawer - 抽屉 / self - 当前页面 / blank - 新页面'
                            'ui' => [
                                'icon' => 'el-icon-back'
                            ]
                        ],
                    ]
                ],

                'table' => [

                    // 未指定时取表的所有字段
                    'items' =>  [
                        [
                            'name' => 'payment_type',
                            'label' => '收款类型',
                            'width' => '90',
                        ],
                        [
                            'name' => 'payment_item_id',
                            'label' => '收款方式',
                            'value' => function ($row) {
                                switch ($row['payment_type']) {
                                    case 'paypal':
                                        $sql = 'SELECT `name` FROM payment_paypal WHERE id = ?';
                                        $paypalAccount = Be::getDb()->getValue($sql, [$row['payment_item_id']]);
                                        if ($paypalAccount) {
                                            return $paypalAccount;
                                        }
                                }
                                return $row['payment_item_id'];
                            },
                        ],
                        [
                            'name' => 'order_sn',
                            'label' => '订单号',
                            'width' => '150',
                            'sortable' => true,
                        ],
                        [
                            'name' => 'url',
                            'label' => '请求网址',
                        ],
                        [
                            'name' => 'complete',
                            'label' => '完成',
                            'driver' => TableItemToggleIcon::class,
                            'width' => '60',
                            'exportValue' => function ($row) {
                                return $row['complete'] ? '是' : '否';
                            },
                        ],
                        [
                            'name' => 'create_time',
                            'label' => '创建时间',
                            'width' => '150',
                            'sortable' => true,
                        ],
                        [
                            'name' => 'update_time',
                            'label' => '更新时间',
                            'width' => '150',
                            'sortable' => true,
                        ],
                    ],

                    'operation' => [
                        'label' => '操作',
                        'width' => '120',
                        'items' => [
                            [
                                'label' => '明细',
                                'url' => beAdminUrl('Shop.Payment.logs', ['task' => 'detail']),
                                'target' => 'drawer',
                                'ui' => [
                                    'type' => 'primary'
                                ]
                            ],
                        ]
                    ],
                ],
            ],

            'detail' => [
                'form' => [
                    'items' => [
                        [
                            'name' => 'id',
                            'label' => 'ID',
                        ],
                        [
                            'name' => 'payment_type',
                            'label' => '收款类型',
                        ],
                        [
                            'name' => 'payment_item_id',
                            'label' => '收款方式 - ID',
                        ],
                        [
                            'name' => 'payment_item_name',
                            'label' => '收款方式 - 名称',
                            'value' => function ($row) {
                                switch ($row['payment_type']) {
                                    case 'paypal':
                                        $sql = 'SELECT `name` FROM payment_paypal WHERE id = ?';
                                        $paypalAccount = Be::getDb()->getValue($sql, [$row['payment_item_id']]);
                                        if ($paypalAccount) {
                                            return $paypalAccount;
                                        }
                                }
                                return $row['payment_item_id'];
                            },
                        ],
                        [
                            'name' => 'order_id',
                            'label' => '订单ID',
                        ],
                        [
                            'name' => 'order_sn',
                            'label' => '订单号',
                        ],
                        [
                            'name' => 'url',
                            'label' => '请求网址',
                        ],
                        [
                            'name' => 'request',
                            'label' => '请求数据',
                            'driver' => DetailItemCode::class,
                            'language' => 'json',
                        ],
                        [
                            'name' => 'response',
                            'label' => '响应数据',
                            'driver' => DetailItemCode::class,
                            'language' => 'json',
                        ],
                        [
                            'name' => 'complete',
                            'label' => '是否完成',
                            'driver' => DetailItemToggleIcon::class,
                        ],
                        [
                            'name' => 'create_time',
                            'label' => '创建时间',
                        ],
                        [
                            'name' => 'update_time',
                            'label' => '更新时间',
                        ],
                    ]
                ],
            ],

            'export' => [],

        ])->execute();
    }

    /**
     * 收款设置跳转
     *
     * @BePermission("*")
     */
    public function setting()
    {
        $request = Be::getRequest();
        $response = Be::getResponse();
        $postData = $request->post('data', '', '');
        if ($postData) {
            $postData = json_decode($postData, true);
            if (isset($postData['row']['id']) && $postData['row']['id']) {
                switch ($postData['row']['name']) {
                    case 'paypal':
                        $response->redirect(beAdminUrl('Shop.Payment.paypal'));
                        break;
                    case 'cod':
                        break;
                }
            }
        }
    }

    /**
     * Paypal 收款设置
     *
     * @BePermission("Paypal 收款设置", ordering="7.22")
     */
    public function paypal()
    {
        Be::getAdminPlugin('Curd')->setting([
            'label' => 'PayPal 收款',
            'table' => 'payment_paypal',
            'grid' => [
                'title' => 'PPayPal 收款账号',

                'filter' => [
                ],

                'orderBy' => 'create_time',
                'orderByDir' => 'DESC',

                'form' => [
                    'items' => [
                        [
                            'name' => 'name',
                            'label' => '名称',
                        ],
                        [
                            'name' => 'is_enable',
                            'label' => '启用状态',
                            'driver' => FormItemSelect::class,
                            'keyValues' => [
                                '1' => '启用',
                                '0' => '禁用',
                            ],
                        ],
                    ],
                ],

                'titleToolbar' => [
                    'items' => [
                        [
                            'label' => '导出',
                            'driver' => ToolbarItemDropDown::class,
                            'ui' => [
                                'icon' => 'el-icon-download',
                            ],
                            'menus' => [
                                [
                                    'label' => 'CSV',
                                    'url' => beAdminUrl('Shop.Payment.paypal', ['task' => 'export']),
                                    'postData' => [
                                        'driver' => 'csv',
                                    ],
                                    'target' => 'blank',
                                ],
                                [
                                    'label' => 'EXCEL',
                                    'url' => beAdminUrl('Shop.Payment.paypal', ['task' => 'export']),
                                    'postData' => [
                                        'driver' => 'excel',
                                    ],
                                    'target' => 'blank',
                                ],
                            ]
                        ],
                        [
                            'label' => 'PayPal 收款记录',
                            'driver' => ToolbarItemLink::class,
                            'url' => beAdminUrl('Shop.Payment.paypalLogs'),
                            'target' => '_blank',
                            'ui' => [
                                'icon' => 'el-icon-tickets',
                            ],
                        ],
                        [
                            'label' => 'PayPal 收款设置',
                            'driver' => ToolbarItemLink::class,
                            'url' => beAdminUrl('Shop.Config.dashboard', ['configName' => 'PaymentPaypal']),
                            'target' => '_blank',
                            'ui' => [
                                'icon' => 'el-icon-setting',
                            ],
                        ],
                    ]
                ],

                'titleRightToolbar' => [
                    'items' => [
                        [
                            'label' => '返回',
                            'url' => beAdminUrl('Shop.Payment.payments'),
                            'target' => 'self', // 'ajax - ajax请求 / dialog - 对话框窗口 / drawer - 抽屉 / self - 当前页面 / blank - 新页面'
                            'ui' => [
                                'icon' => 'el-icon-back'
                            ]
                        ],
                        [
                            'label' => '新增PayPal收款账号',
                            'url' => beAdminUrl('Shop.Payment.paypal', ['task' => 'create']),
                            'target' => 'drawer', // 'ajax - ajax请求 / dialog - 对话框窗口 / drawer - 抽屉 / self - 当前页面 / blank - 新页面'
                            'ui' => [
                                'icon' => 'el-icon-plus',
                                'type' => 'primary',
                            ],
                            'drawer' => [
                                'width' => '60%',
                            ]
                        ],
                    ]
                ],

                'table' => [

                    // 未指定时取表的所有字段
                    'items' => [
                        [
                            'name' => 'name',
                            'label' => '名称',
                            'align' => 'left',
                            'driver' => TableItemLink::class,
                            'url' => beAdminUrl('Shop.Payment.paypal', ['task' => 'detail']),
                            'drawer' => [
                                'width' => '60%'
                            ],
                        ],
                        [
                            'name' => 'client_id',
                            'label' => '客户ID（Client ID）',
                        ],
                        [
                            'name' => 'is_enable',
                            'label' => '启用/禁用',
                            'driver' => TableItemToggleIcon::class,
                            'target' => 'ajax',
                            'url' => beAdminUrl('Shop.Payment.paypal', ['task' => 'fieldEdit']),
                            'width' => '120',
                            'exportValue' => function ($row) {
                                return $row['is_enable'] ? '启用' : '禁用';
                            },
                        ],
                        [
                            'name' => 'update_time',
                            'label' => '更新时间',
                            'width' => '180',
                            'sortable' => true,
                        ]
                    ],

                    'operation' => [
                        'label' => '操作',
                        'width' => '120',
                        'items' => [
                            [
                                'label' => '',
                                'tooltip' => '编辑',
                                'url' => beAdminUrl('Shop.Payment.paypal', ['task' => 'edit']),
                                'target' => 'drawer',
                                'drawer' => [
                                    'width' => '60%',
                                ],
                                'ui' => [
                                    ':underline' => 'false',
                                    'style' => 'font-size: 20px;',
                                ],
                                'icon' => 'el-icon-edit',
                            ],
                            [
                                'label' => '',
                                'tooltip' => '删除',
                                'url' => beAdminUrl('Shop.Payment.paypal', ['task' => 'delete']),
                                'target' => 'ajax',
                                'confirm' => '确认要删除么？',
                                'ui' => [
                                    'type' => 'danger',
                                    ':underline' => 'false',
                                    'style' => 'font-size: 20px;',
                                ],
                                'icon' => 'el-icon-delete',
                            ],
                        ]
                    ],
                ],
            ],

            'detail' => [
                'theme' => 'Blank',
                'form' => [
                    'ui' => [
                        'label-width' => '300px',
                    ],
                    'items' => [
                        [
                            'name' => 'id',
                            'label' => 'ID',
                        ],
                        [
                            'name' => 'name',
                            'label' => '名称',
                        ],
                        [
                            'name' => 'client_id',
                            'label' => '客户ID（Client ID）',
                        ],
                        [
                            'name' => 'secret',
                            'label' => '密钥（Secret）',
                        ],
                        [
                            'name' => 'is_enable',
                            'label' => '启用状态',
                            'driver' => DetailItemToggleIcon::class,
                        ],
                        [
                            'name' => 'create_time',
                            'label' => '创建时间',
                        ],
                        [
                            'name' => 'update_time',
                            'label' => '更新时间',
                        ],
                    ],
                ],
            ],

            'create' => [
                'title' => '新建Paypal账号',
                'theme' => 'Blank',
                'form' => [
                    'ui' => [
                        'label-width' => '300px',
                    ],
                    'items' => [
                        [
                            'name' => 'name',
                            'label' => '名称',
                            'description' => '给账号起个别名，方便管理多个账号',
                            'unique' => true,
                            'required' => true,
                        ],
                        [
                            'name' => 'client_id',
                            'label' => '客户ID（Client ID）',
                            'required' => true,
                        ],
                        [
                            'name' => 'secret',
                            'label' => '密钥（Secret）',
                            'required' => true,
                        ],
                    ]
                ],
                'events' => [
                    'before' => function (Tuple &$tuple) {
                        $tuple->is_enable = 0;
                        $tuple->create_time = date('Y-m-d H:i:s');
                        $tuple->update_time = date('Y-m-d H:i:s');
                    }
                ]
            ],

            'edit' => [
                'title' => '编辑Paypal账号',
                'theme' => 'Blank',
                'form' => [
                    'ui' => [
                        'label-width' => '300px',
                    ],
                    'items' => [
                        [
                            'name' => 'name',
                            'label' => '名称',
                            'description' => '给账号起个别名，方便管理多个账号',
                            'unique' => true,
                            'required' => true,
                        ],
                        [
                            'name' => 'client_id',
                            'label' => '客户ID（Client ID）',
                            'required' => true,
                        ],
                        [
                            'name' => 'secret',
                            'label' => '密钥（Secret）',
                            'required' => true,
                        ],
                    ]
                ],
                'events' => [
                    'before' => function (Tuple &$tuple) {
                        $tuple->update_time = date('Y-m-d H:i:s');
                    },
                ]
            ],

            'fieldEdit' => [
                'events' => [
                    'before' => function ($tuple, $postData) {
                        $now = date('Y-m-d H:i:s');
                        if ($postData['postData']['field'] === 'is_enable') {
                            if ($postData['row']['is_enable'] === '1') {

                                // 默认只能启用一个账号
                                Be::getTable('payment_paypal')
                                    ->where('id', '!=', $postData['row']['id'])
                                    ->where('is_enable', 1)
                                    ->update([
                                        'is_enable' => 0,
                                        'update_time' => $now
                                    ]);

                            } elseif ($postData['row']['is_enable'] === '0') {

                                if (Be::getTable('payment_paypal')
                                        ->where('id', '!=', $postData['row']['id'])
                                        ->where('is_enable', 1)
                                        ->count() === '0') {
                                    throw new ControllerException('至少需要启用一个账号！');
                                }

                            }
                        }

                        $tuple->update_time = $now;
                    },
                ],
            ],

            'delete' => [
            ],

            'export' => [],

        ])->execute();
    }

    /**
     * Paypal 收款记录
     *
     * @BePermission("Paypal 收款记录", ordering="7.23")
     */
    public function paypalLogs()
    {
        $paymentPaypalKeyValues = Be::getDb()->getKeyValues('SELECT id, `name` FROM payment_paypal');

        Be::getAdminPlugin('Curd')->setting([
            'label' => 'PayPal 收款记录',
            'table' => 'payment_paypal_order',
            'grid' => [
                'title' => 'PayPal 收款记录',
                'theme' => 'Blank',

                'orderBy' => 'create_time',
                'orderByDir' => 'DESC',

                'filter' => [
                ],

                'form' => [
                    'items' => [
                        [
                            'name' => 'payment_paypal_id',
                            'label' => 'Paypal账号',
                            'driver' => FormItemSelect::class,
                            'keyValues' => $paymentPaypalKeyValues,
                        ],
                        [
                            'name' => 'order_sn',
                            'label' => '订单号',
                        ],
                        [
                            'name' => 'create_time',
                            'label' => '创建时间',
                            'driver' => FormItemDatePickerRange::class,
                        ],
                        [
                            'name' => 'update_time',
                            'label' => '更新时间',
                            'driver' => FormItemDatePickerRange::class,
                        ],
                    ],
                ],

                'titleToolbar' => [

                    'items' => [
                        [
                            'label' => '导出',
                            'driver' => ToolbarItemDropDown::class,
                            'ui' => [
                                'icon' => 'el-icon-download',
                            ],
                            'menus' => [
                                [
                                    'label' => 'CSV',
                                    'url' => beAdminUrl('Shop.Payment.paypalogs', ['task' => 'export']),
                                    'postData' => [
                                        'driver' => 'csv',
                                    ],
                                    'target' => 'blank',
                                ],
                                [
                                    'label' => 'EXCEL',
                                    'url' => beAdminUrl('Shop.Payment.paypalogs', ['task' => 'export']),
                                    'postData' => [
                                        'driver' => 'excel',
                                    ],
                                    'target' => 'blank',
                                ],
                            ]
                        ],
                    ]
                ],

                /*
                 * Chrome 关不掉
                'titleRightToolbar' => [
                    'items' => [
                        [
                            'label' => '关闭页面',
                            'ui' => [
                                'type' => 'danger',
                                'icon' => 'el-icon-close',
                                '@click' => 'window.close()',
                            ]
                        ],
                    ]
                ],
                */

                'table' => [

                    // 未指定时取表的所有字段
                    'items' =>  [
                        [
                            'name' => 'payment_paypal_id',
                            'label' => 'Paypal账号',
                            'keyValues' => $paymentPaypalKeyValues,
                        ],
                        [
                            'name' => 'order_sn',
                            'label' => '订单号',
                            'width' => '150',
                        ],
                        [
                            'name' => 'paypal_payer',
                            'label' => '买家',
                            'value' => function ($row) {
                                $payer = '';
                                if ($row['paypal_payer_first_name']) {
                                    $payer = $row['paypal_payer_first_name'] . ' ';
                                }

                                if ($row['paypal_payer_last_name']) {
                                    $payer .= $row['paypal_payer_last_name'] . ' ';
                                }

                                if ($row['paypal_payer_email']) {
                                    $payer .= '(' . $row['paypal_payer_email'] . ')';
                                }

                                if (!$payer) {
                                    $payer = $row['paypal_payer_id'];
                                }

                                return $payer;
                            },
                        ],
                        [
                            'name' => 'paypal_status',
                            'label' => 'Paypal 状态',
                            'width' => '150',
                        ],
                        [
                            'name' => 'create_time',
                            'label' => '创建时间',
                            'width' => '150',
                            'sortable' => true,
                        ],
                        [
                            'name' => 'update_time',
                            'label' => '更新时间',
                            'width' => '150',
                        ],
                    ],

                    'operation' => [
                        'label' => '操作',
                        'width' => '120',
                        'items' => [
                            [
                                'label' => '明细',
                                'task' => 'detail',
                                'target' => 'drawer',
                                'ui' => [
                                    'type' => 'primary'
                                ]
                            ],
                        ]
                    ],
                ],
            ],

            'detail' => [
                'form' => [
                    'items' => [
                        [
                            'name' => 'id',
                            'label' => 'ID',
                        ],
                        [
                            'name' => 'payment_paypal_id',
                            'label' => 'Paypal账号 - ID',
                        ],
                        [
                            'name' => 'payment_name',
                            'label' => 'Paypal账号 - 名称',
                            'value' => function ($row) {
                                switch ($row['payment_type']) {
                                    case 'paypal':
                                        $sql = 'SELECT `name` FROM payment_paypal WHERE id = ?';
                                        $paypalAccount = Be::getDb()->getValue($sql, [$row['payment_paypal_id']]);
                                        if ($paypalAccount) {
                                            return $paypalAccount;
                                        }
                                }
                                return $row['payment_id'];
                            },
                        ],
                        [
                            'name' => 'order_id',
                            'label' => '订单ID',
                        ],
                        [
                            'name' => 'order_sn',
                            'label' => '订单号',
                        ],
                        [
                            'name' => 'paypal_order_id',
                            'label' => 'Paypal ID',
                        ],
                        [
                            'name' => 'paypal_status',
                            'label' => 'Paypal 状态',
                        ],
                        [
                            'name' => 'paypal_payer_id',
                            'label' => '买家ID',
                        ],
                        [
                            'name' => 'paypal_payer_first_name',
                            'label' => '买家名',
                        ],
                        [
                            'name' => 'paypal_payer_last_name',
                            'label' => '买家姓',
                        ],
                        [
                            'name' => 'paypal_payer_email',
                            'label' => '买家邮箱',
                        ],
                        [
                            'name' => 'paypal_payer_country_code',
                            'label' => '买家国家',
                        ],
                        [
                            'name' => 'create_time',
                            'label' => '时间',
                        ],
                        [
                            'name' => 'create_time',
                            'label' => '创建时间',
                        ],
                        [
                            'name' => 'update_time',
                            'label' => '更新时间',
                        ],
                    ]
                ],
            ],

            'export' => [],

        ])->execute();
    }

}
