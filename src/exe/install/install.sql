
CREATE TABLE `payment` (
`id` varchar(36) NOT NULL DEFAULT 'uuid()' COMMENT 'UUID',
`type` varchar(60) NOT NULL DEFAULT '' COMMENT '支付方式（Paypal/Alipay/Wechat 等）',
`logo` varchar(30) NOT NULL DEFAULT '' COMMENT 'LOGO图标',
`name` varchar(60) NOT NULL DEFAULT '' COMMENT '名称（展示给用户）',
`label` varchar(60) NOT NULL DEFAULT '' COMMENT '中文标签（方便后台识别）',
`description` text NOT NULL COMMENT '描述（展示给用户）',
`config` text NOT NULL COMMENT '配置项（对象 serialize）',
`data` text NOT NULL COMMENT '暂存数据（对象 serialize）',
`is_enable` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否启用',
`create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
`update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='支付方式';

ALTER TABLE `payment`
ADD PRIMARY KEY (`id`),
ADD KEY `type` (`type`);


CREATE TABLE `payment_log` (
`id` varchar(36) NOT NULL DEFAULT 'uuid()' COMMENT 'UUID',
`payment_id` varchar(36) NOT NULL DEFAULT '' COMMENT '店铺ID',
`data` text NOT NULL COMMENT '业务数据（对象 serialize）',
`url` varchar(600) NOT NULL DEFAULT '' COMMENT '请求网址',
`request` mediumtext NOT NULL COMMENT '请求参数',
`response` mediumtext NOT NULL COMMENT '响应参数',
`success` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否成功',
`result` mediumtext NOT NULL COMMENT '支付结果（对象 serialize）',
`callback` text NOT NULL COMMENT '回调代码（成功时调用，传入两个参数 $data - 业务数据对象, $result - 支付结果对象）',
`callback_result` mediumtext NOT NULL COMMENT '调用回调代码的返回的结果（serialize）',
`callback_success` tinyint(4) NOT NULL DEFAULT '0' COMMENT '调用回调代码是否成功（未抛异常即认为成功）',
`callback_exception` text NOT NULL COMMENT '调用回调代码势出异常的 message 信息（异常详情记录日志）',
`create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
`update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='收款日志';

ALTER TABLE `payment_log`
ADD PRIMARY KEY (`id`),
ADD KEY `payment_id` (`payment_id`);


