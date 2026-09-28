-- ============================================
-- 充值回调日志表补列 + ETH 渠道开启链上自动确认
--
-- 背景（线上实测）：
--   1) lp_payment_callback_log 最早在 live_platform.sql 里按 7 列建过，
--      而 upgrade_usdt_auto.sql 用的是 CREATE TABLE IF NOT EXISTS → 整段静默跳过，
--      于是 amount / from_address / to_address / block_timestamp 四列从未加上。
--      两个扫链任务的 logCallback() 插入必然报 1054，又被 catch 静默吞掉，
--      结果「未匹配的链上转账」（转错金额 / 订单已过期才到账）后台完全看不到。
--   2) ETH 渠道线上是 confirm_mode='manual'，而 recharge:scan-eth 只扫 auto 渠道，
--      每分钟空转 channels=0。现已为 ETH 扫链补上 Blockscout 免 key 兜底，
--      扫链可用，因此把 ETH 切到 auto（见文末说明，可注释掉该行回滚）。
--
-- 本脚本幂等，可重复执行。
-- ============================================

SET @tbl = 'lp_payment_callback_log';

SET @has = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @tbl AND `COLUMN_NAME` = 'amount');
SET @ddl = IF(@has = 0,
  'ALTER TABLE `lp_payment_callback_log` ADD COLUMN `amount` DECIMAL(18,6) NOT NULL DEFAULT 0 COMMENT ''链上转账金额''',
  'SELECT ''lp_payment_callback_log.amount 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

SET @has = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @tbl AND `COLUMN_NAME` = 'from_address');
SET @ddl = IF(@has = 0,
  'ALTER TABLE `lp_payment_callback_log` ADD COLUMN `from_address` VARCHAR(64) NOT NULL DEFAULT '''' COMMENT ''付款地址''',
  'SELECT ''lp_payment_callback_log.from_address 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

SET @has = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'to_address');
SET @ddl = IF(@has = 0,
  'ALTER TABLE `lp_payment_callback_log` ADD COLUMN `to_address` VARCHAR(64) NOT NULL DEFAULT '''' COMMENT ''收款地址''',
  'SELECT ''lp_payment_callback_log.to_address 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

SET @has = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'block_timestamp');
SET @ddl = IF(@has = 0,
  'ALTER TABLE `lp_payment_callback_log` ADD COLUMN `block_timestamp` BIGINT NOT NULL DEFAULT 0 COMMENT ''链上时间戳''',
  'SELECT ''lp_payment_callback_log.block_timestamp 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- --------------------------------------------
-- ETH 渠道开启链上自动确认
--
-- auto 模式下应付金额会带 0.00000001~0.00000099 的识别尾数、订单有效期 2 小时；
-- manual 模式是整数金额、有效期 24 小时。不想改就把这行注释掉。
-- --------------------------------------------
UPDATE `lp_recharge_channel` SET `confirm_mode` = 'auto' WHERE `type` = 'eth' AND `confirm_mode` = 'manual';

-- --------------------------------------------
-- 校验
-- --------------------------------------------
SELECT '--- lp_payment_callback_log 字段 ---' AS section;
SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT
  FROM `information_schema`.`COLUMNS`
 WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_payment_callback_log'
 ORDER BY `ORDINAL_POSITION`;

SELECT '--- 充值渠道（确认方式 / 地址 / API Key 是否已配）---' AS section;
SELECT id, name, type, confirm_mode, status, address,
       IF(api_key = '', '未配置', '已配置') AS api_key_state
  FROM `lp_recharge_channel` ORDER BY id;
