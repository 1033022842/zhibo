-- ============================================
-- 充值渠道「转账备注 / Memo / Tag」字段
--
-- 背景：XRP（Destination Tag）、TON（Comment）、部分交易所入金（Memo）
--   必须在转账时附带备注，否则币会进到收款地址但无法归属到具体用户，
--   属于「钱到了但认不出来」的丢币场景。
--   本字段为空表示该链不需要备注。本脚本幂等，可重复执行。
-- ============================================

SET @tbl = 'lp_recharge_channel';

SET @has = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @tbl AND `COLUMN_NAME` = 'memo');
SET @ddl = IF(@has = 0,
  'ALTER TABLE `lp_recharge_channel` ADD COLUMN `memo` VARCHAR(64) NOT NULL DEFAULT '''' COMMENT ''转账备注/Memo/Tag，XRP、TON 等必填，空表示不需要'' AFTER `address`',
  'SELECT ''lp_recharge_channel.memo 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- --------------------------------------------
-- 校验
-- --------------------------------------------
SELECT '--- lp_recharge_channel 渠道字段 ---' AS section;
SELECT `id`, `name`, `type`, `address`, `memo`, `confirm_mode`, `status`
  FROM `lp_recharge_channel`
 ORDER BY `sort`;
