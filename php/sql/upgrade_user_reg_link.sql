-- ============================================
-- 推广链接名称归因
-- lp_user.reg_link_name：注册时记录推广链接名称（后台生成推广链接时填的 ?name=）
-- 本脚本幂等，可重复执行
-- ============================================

SET @has_col = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_user' AND `COLUMN_NAME` = 'reg_link_name');
SET @ddl = IF(@has_col = 0,
  'ALTER TABLE `lp_user`
     ADD COLUMN `reg_link_name` VARCHAR(128) NOT NULL DEFAULT '''' COMMENT ''注册来源推广链接名称(?name=)'' AFTER `reg_ad`',
  'SELECT ''lp_user.reg_link_name 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- 校验
SELECT `COLUMN_NAME`, `COLUMN_TYPE`, `COLUMN_COMMENT` FROM `information_schema`.`COLUMNS`
WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_user' AND `COLUMN_NAME` = 'reg_link_name';
