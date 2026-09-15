-- ============================================
-- AI 聊天记录：支持非平台角色（首页推荐 / 自建角色）按 role_key 归档
-- 在 live_platform 数据库执行；脚本可重复执行
-- ============================================

-- 1. 新增 role_key 列（content_id=0 时用它区分不同角色，如 home-4）
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lp_ai_chat_message' AND COLUMN_NAME = 'role_key');
SET @sql1 := IF(@has_col = 0,
  'ALTER TABLE `lp_ai_chat_message` ADD COLUMN `role_key` VARCHAR(64) NOT NULL DEFAULT '''' COMMENT ''非平台角色标识(home-4 等)，content_id=0 时使用'' AFTER `content_id`',
  'SELECT 1 AS skip_add_column');
PREPARE s1 FROM @sql1;
EXECUTE s1;
DEALLOCATE PREPARE s1;

-- 2. 索引：按角色 + 用户查询历史
SET @has_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lp_ai_chat_message' AND INDEX_NAME = 'idx_role_key_user');
SET @sql2 := IF(@has_idx = 0,
  'ALTER TABLE `lp_ai_chat_message` ADD INDEX `idx_role_key_user` (`role_key`, `user_id`, `id`)',
  'SELECT 1 AS skip_add_index');
PREPARE s2 FROM @sql2;
EXECUTE s2;
DEALLOCATE PREPARE s2;

-- 3. 校验
SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME = 'lp_ai_chat_message'
   AND COLUMN_NAME IN ('content_id', 'role_key');
