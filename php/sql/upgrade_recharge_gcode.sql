-- ============================================
-- 充值订单「支付通道」字段（FlowPay 多通道）
--
-- 背景：FlowPay 商户号 10000011 下开通了多条收单通道（gcode）：
--   666888 Amopay-applepay / 666777 Amopay-GGPay / 666999 Amopay-Visa
-- 三条通道金额与费率一致（同一收银台 ramp.amopay.io），只差发卡行侧收单路由。
-- 前台把它们平铺成多个「Pay with xxx」按钮由用户自选，因此订单需要记下
-- 本单实际走的 gcode，后台才能按通道对比成功率。
--   本脚本幂等，可重复执行。
-- ============================================

SET @tbl = 'lp_recharge_order';

SET @has = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @tbl AND `COLUMN_NAME` = 'pay_gcode');
SET @ddl = IF(@has = 0,
  'ALTER TABLE `lp_recharge_order` ADD COLUMN `pay_gcode` VARCHAR(32) NOT NULL DEFAULT '''' COMMENT ''网关收单通道（FlowPay gcode）'' AFTER `chain_type`',
  'SELECT ''lp_recharge_order.pay_gcode 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- 回填：历史 FlowPay 订单当时只有 666888 一条通道
UPDATE `lp_recharge_order`
   SET `pay_gcode` = '666888'
 WHERE `pay_channel` = 'flowpay' AND `pay_gcode` = '';

-- --------------------------------------------
-- 校验
-- --------------------------------------------
SELECT '--- lp_recharge_order 通道字段 ---' AS section;
SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT, COLUMN_COMMENT
  FROM `information_schema`.`COLUMNS`
 WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @tbl
   AND `COLUMN_NAME` IN ('pay_channel', 'chain_type', 'pay_gcode')
 ORDER BY `ORDINAL_POSITION`;

SELECT '--- 各通道订单量 ---' AS section;
SELECT `pay_channel`, `pay_gcode`, `status`, COUNT(*) AS c
  FROM `lp_recharge_order`
 GROUP BY `pay_channel`, `pay_gcode`, `status`
 ORDER BY `pay_channel`, `pay_gcode`, `status`;
