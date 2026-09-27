-- ============================================
-- 订阅管理：记录「取消订阅」意图
--
-- 站点会员是一次性买断 N 个月（网关无 recurring），所以这里的「取消」只是记录用户意图：
--   到期后不再续订、并保留取消原因便于运营跟进。
-- 本脚本幂等，可重复执行
-- ============================================

SET @tbl = 'lp_user';

SET @has = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @tbl AND `COLUMN_NAME` = 'vip_auto_renew');
SET @ddl = IF(@has = 0,
  'ALTER TABLE `lp_user` ADD COLUMN `vip_auto_renew` TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''是否自动续订 1=续订 0=已取消续订''',
  'SELECT ''lp_user.vip_auto_renew 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

SET @has = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @tbl AND `COLUMN_NAME` = 'vip_cancel_reason');
SET @ddl = IF(@has = 0,
  'ALTER TABLE `lp_user` ADD COLUMN `vip_cancel_reason` VARCHAR(32) NOT NULL DEFAULT '''' COMMENT ''取消订阅原因''',
  'SELECT ''lp_user.vip_cancel_reason 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

SET @has = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'vip_cancel_description');
SET @ddl = IF(@has = 0,
  'ALTER TABLE `lp_user` ADD COLUMN `vip_cancel_description` VARCHAR(255) NOT NULL DEFAULT '''' COMMENT ''取消订阅补充说明''',
  'SELECT ''lp_user.vip_cancel_description 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

SET @has = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @tbl AND `COLUMN_NAME` = 'vip_cancel_at');
SET @ddl = IF(@has = 0,
  'ALTER TABLE `lp_user` ADD COLUMN `vip_cancel_at` DATETIME NULL DEFAULT NULL COMMENT ''取消订阅时间''',
  'SELECT ''lp_user.vip_cancel_at 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- --------------------------------------------
-- 校验
-- --------------------------------------------
SELECT '--- lp_user 订阅相关字段 ---' AS section;
SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT, COLUMN_COMMENT
  FROM `information_schema`.`COLUMNS`
 WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_user'
   AND `COLUMN_NAME` IN ('vip_expire_at', 'vip_last_claim_date', 'vip_auto_renew', 'vip_cancel_reason', 'vip_cancel_description', 'vip_cancel_at')
 ORDER BY `ORDINAL_POSITION`;
