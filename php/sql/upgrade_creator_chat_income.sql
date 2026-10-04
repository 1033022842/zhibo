-- ============================================
-- 聊天作品收益分账 + 专区隔离
-- 1) lp_ai_content 增加 creator_user_id：标识聊天内容的作者（创作者投稿），用于消费分账
-- 2) lp_creator_submission 增加 ai_content_id：聊天作品上架后的平台内容 id（幂等标记）
-- 本脚本幂等，可重复执行
-- ============================================

-- --------------------------------------------
-- 1. lp_ai_content.creator_user_id
-- --------------------------------------------
SET @has_col = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_ai_content' AND `COLUMN_NAME` = 'creator_user_id');
SET @ddl = IF(@has_col = 0,
  'ALTER TABLE `lp_ai_content` ADD COLUMN `creator_user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''作者用户ID(创作者投稿,0=平台)'' AFTER `personality`',
  'SELECT ''lp_ai_content.creator_user_id 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- --------------------------------------------
-- 2. lp_creator_submission.ai_content_id
-- --------------------------------------------
SET @has_col2 = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_creator_submission' AND `COLUMN_NAME` = 'ai_content_id');
SET @ddl2 = IF(@has_col2 = 0,
  'ALTER TABLE `lp_creator_submission` ADD COLUMN `ai_content_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''聊天作品上架后的lp_ai_content.id'' AFTER `short_id`',
  'SELECT ''lp_creator_submission.ai_content_id 已存在，跳过'' AS msg');
PREPARE _stmt2 FROM @ddl2;
EXECUTE _stmt2;
DEALLOCATE PREPARE _stmt2;

-- --------------------------------------------
-- 3. 校验
-- --------------------------------------------
SELECT `TABLE_NAME`, `COLUMN_NAME`, `COLUMN_TYPE`, `COLUMN_COMMENT` FROM `information_schema`.`COLUMNS`
WHERE `TABLE_SCHEMA` = DATABASE()
  AND ((`TABLE_NAME` = 'lp_ai_content' AND `COLUMN_NAME` = 'creator_user_id')
    OR (`TABLE_NAME` = 'lp_creator_submission' AND `COLUMN_NAME` = 'ai_content_id'));
