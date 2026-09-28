-- ============================================
-- 充值订单「支付状态」字段
--
-- 背景：lp_recharge_order.status 只表达订单生命周期
--   （0 待审核 / 1 已通过 / 2 已拒绝 / 3 已关闭），
--   其中「已关闭」只说明订单过期关掉了，看不出用户到底付没付钱。
--   这里补一个与订单状态独立的支付状态维度：
--     pay_state 0=未确认 1=已支付 2=未支付
--   由三处写入：链上扫链入账、网关回调/查单补单、后台人工审核。
--   本脚本幂等，可重复执行。
-- ============================================

SET @tbl = 'lp_recharge_order';

SET @has = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @tbl AND `COLUMN_NAME` = 'pay_state');
SET @ddl = IF(@has = 0,
  'ALTER TABLE `lp_recharge_order` ADD COLUMN `pay_state` TINYINT NOT NULL DEFAULT 0 COMMENT ''支付状态 0未确认 1已支付 2未支付''',
  'SELECT ''lp_recharge_order.pay_state 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

SET @has = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @tbl AND `COLUMN_NAME` = 'pay_checked_at');
SET @ddl = IF(@has = 0,
  'ALTER TABLE `lp_recharge_order` ADD COLUMN `pay_checked_at` DATETIME NULL DEFAULT NULL COMMENT ''支付状态最后确认时间''',
  'SELECT ''lp_recharge_order.pay_checked_at 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- 回填：已通过的单一定是已支付
UPDATE `lp_recharge_order`
   SET `pay_state` = 1,
       `pay_checked_at` = COALESCE(`paid_at`, `reviewed_at`, `updated_at`, `created_at`)
 WHERE `status` = 1 AND `pay_state` <> 1;

-- 回填：网关下单就失败的单，用户根本没机会付款
UPDATE `lp_recharge_order`
   SET `pay_state` = 2,
       `pay_checked_at` = COALESCE(`updated_at`, `created_at`)
 WHERE `status` = 3 AND `admin_remark` LIKE '网关下单失败%' AND `pay_state` = 0;

-- --------------------------------------------
-- 校验
-- --------------------------------------------
SELECT '--- lp_recharge_order 支付状态字段 ---' AS section;
SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT, COLUMN_COMMENT
  FROM `information_schema`.`COLUMNS`
 WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @tbl
   AND `COLUMN_NAME` IN ('status', 'pay_state', 'pay_checked_at', 'paid_at', 'reviewed_at')
 ORDER BY `ORDINAL_POSITION`;

SELECT '--- 支付状态分布 ---' AS section;
SELECT `status`, `pay_state`, COUNT(*) AS c
  FROM `lp_recharge_order`
 GROUP BY `status`, `pay_state`
 ORDER BY `status`, `pay_state`;
