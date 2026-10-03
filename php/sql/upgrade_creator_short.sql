-- ============================================
-- 创作提交：短剧上架后回写关联的短剧 ID（幂等用）
-- 1) lp_creator_submission 增加 short_id（关联 lp_short_item.id）
-- 本脚本幂等，可重复执行
-- ============================================

SET @has_short = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_creator_submission' AND `COLUMN_NAME` = 'short_id');
SET @ddl = IF(@has_short = 0,
  'ALTER TABLE `lp_creator_submission` ADD COLUMN `short_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''短剧上架后关联 lp_short_item.id'' AFTER `home_character_id`',
  'SELECT ''lp_creator_submission.short_id 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

SELECT `COLUMN_NAME`, `COLUMN_TYPE`, `COLUMN_COMMENT` FROM `information_schema`.`COLUMNS`
WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_creator_submission'
  AND `COLUMN_NAME` IN ('persona_id', 'home_character_id', 'short_id');
