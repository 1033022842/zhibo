-- ============================================
-- 创作提交支持「短剧多集」（对齐后台 lp_short_item / lp_short_episode 结构）
-- 1) lp_creator_submission 增加 episodes(剧集JSON) / free_episodes(前N集免费)
-- 本脚本幂等，可重复执行
-- ============================================

-- --------------------------------------------
-- 1. episodes：剧集 JSON
--    [{"episode_no":1,"title":"","poster":"","video_url":"","duration":"03:12","price":0}]
-- --------------------------------------------
SET @has_eps = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_creator_submission' AND `COLUMN_NAME` = 'episodes');
SET @ddl = IF(@has_eps = 0,
  'ALTER TABLE `lp_creator_submission` ADD COLUMN `episodes` TEXT NULL COMMENT ''短剧剧集JSON: [{episode_no,title,poster,video_url,duration,price}]'' AFTER `materials`',
  'SELECT ''lp_creator_submission.episodes 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- --------------------------------------------
-- 2. free_episodes：前 N 集免费
-- --------------------------------------------
SET @has_free = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_creator_submission' AND `COLUMN_NAME` = 'free_episodes');
SET @ddl = IF(@has_free = 0,
  'ALTER TABLE `lp_creator_submission` ADD COLUMN `free_episodes` INT NOT NULL DEFAULT 0 COMMENT ''短剧前N集免费'' AFTER `episodes`',
  'SELECT ''lp_creator_submission.free_episodes 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- --------------------------------------------
-- 3. 校验
-- --------------------------------------------
SELECT `COLUMN_NAME`, `COLUMN_TYPE`, `COLUMN_COMMENT` FROM `information_schema`.`COLUMNS`
WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_creator_submission'
  AND `COLUMN_NAME` IN ('episodes', 'free_episodes');
