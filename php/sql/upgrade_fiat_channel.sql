-- ============================================
-- 法币支付通道（单个国家按本币收款）
--
-- 背景：站内网关（star-pay / FlowPay）此前只按 USD 下单，用户看到与实付的
--   本币金额对不上。这里把「结算币种 + 兑 USD 汇率」做成后台可配的通道：
--   用户订阅 $15 时，后台配 1 USD = 4.7020 MYR，下单就自动变成 RM 70.53，
--   汇率在下单那一刻锁定并写进订单，后台改汇率不会影响已生成的订单。
--
--   本脚本幂等，可重复执行。
-- ============================================

-- --------------------------------------------
-- 1) 法币通道表
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `lp_fiat_channel` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `name` varchar(64) NOT NULL DEFAULT '' COMMENT '前台展示名，如 Malaysia · FPX',
  `gateway` varchar(32) NOT NULL DEFAULT 'manual' COMMENT '对接通道 code：manual=人工审核 / starpay / flowpay，其它 code 视为未对接',
  `gateway_ref` varchar(64) NOT NULL DEFAULT '' COMMENT '网关侧标识（starpay 填 product，flowpay 填 gcode）',
  `currency` varchar(8) NOT NULL DEFAULT '' COMMENT '结算币种代码，如 MYR',
  `currency_symbol` varchar(8) NOT NULL DEFAULT '' COMMENT '金额前缀符号，如 RM',
  `usd_rate` decimal(18,8) NOT NULL DEFAULT 0 COMMENT '1 USD = ? 本币（后台手填，下单时锁定）',
  `decimals` tinyint(4) NOT NULL DEFAULT 2 COMMENT '本币金额小数位（VND/IDR 这类无小数可填 0）',
  `min_amount` decimal(18,2) NOT NULL DEFAULT 0 COMMENT '该币种最小支付金额（本币）',
  `diamond_rate` decimal(18,2) NOT NULL DEFAULT 100 COMMENT '每 1 USD 到账代币数（充值代币单用）',
  `pay_instructions` varchar(512) NOT NULL DEFAULT '' COMMENT '人工通道付款说明（收款账号/操作指引）',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序值',
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '状态:0下架 1启用',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_status_sort`(`status` ASC, `sort` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '法币支付通道（单国本币收款）' ROW_FORMAT = Dynamic;

-- --------------------------------------------
-- 2) 订单表补本币字段
--    pay_amount 对法币单存「本币金额」，usd_amount 存原始 USD 标价，
--    fx_rate 存下单时锁定的汇率，用于对账与后续改汇率不串账。
-- --------------------------------------------
SET @tbl = 'lp_recharge_order';

SET @has = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @tbl AND `COLUMN_NAME` = 'fiat_channel_id');
SET @ddl = IF(@has = 0,
  'ALTER TABLE `lp_recharge_order` ADD COLUMN `fiat_channel_id` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT ''法币通道ID''',
  'SELECT ''lp_recharge_order.fiat_channel_id 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

SET @has = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @tbl AND `COLUMN_NAME` = 'pay_currency');
SET @ddl = IF(@has = 0,
  'ALTER TABLE `lp_recharge_order` ADD COLUMN `pay_currency` varchar(8) NOT NULL DEFAULT '''' COMMENT ''实付币种：链上=币种代号，法币=本币代码，空=USD''',
  'SELECT ''lp_recharge_order.pay_currency 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

SET @has = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @tbl AND `COLUMN_NAME` = 'usd_amount');
SET @ddl = IF(@has = 0,
  'ALTER TABLE `lp_recharge_order` ADD COLUMN `usd_amount` decimal(18,2) NOT NULL DEFAULT 0 COMMENT ''原始 USD 标价（折算前）''',
  'SELECT ''lp_recharge_order.usd_amount 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

SET @has = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @tbl AND `COLUMN_NAME` = 'fx_rate');
SET @ddl = IF(@has = 0,
  'ALTER TABLE `lp_recharge_order` ADD COLUMN `fx_rate` decimal(18,8) NOT NULL DEFAULT 0 COMMENT ''下单时锁定的 1 USD = ? 本币''',
  'SELECT ''lp_recharge_order.fx_rate 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- --------------------------------------------
-- 3) 后台不单开菜单：法币通道列表并到「充值渠道」页面
--    （live.RechargeChannel/index 里一并维护，新增/编辑/启停走 live.FiatChannel）
--    早期版本曾插过一条独立菜单，这里删掉；新库执行也无副作用。
-- --------------------------------------------
DELETE FROM `ba_admin_rule` WHERE `name` = 'live/fiatChannel';

-- --------------------------------------------
-- 4) 校验
-- --------------------------------------------
SELECT '--- lp_fiat_channel ---' AS section;
SELECT `id`, `name`, `gateway`, `currency`, `currency_symbol`, `usd_rate`, `decimals`, `min_amount`, `diamond_rate`, `status`, `sort`
  FROM `lp_fiat_channel` ORDER BY `sort`, `id`;

SELECT '--- 后台菜单（应为空）---' AS section;
SELECT `id`, `pid`, `title`, `name`, `url`, `status` FROM `ba_admin_rule` WHERE `name` = 'live/fiatChannel';
