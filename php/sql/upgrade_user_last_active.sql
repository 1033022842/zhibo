-- ============================================
-- 会员在线/离线：lp_user_profile.last_active_at（接口心跳）
-- 1) lp_user_profile 增加 last_active_at：带 token 的接口请求会刷新该字段（同一用户每分钟最多一次）
-- 2) 后台会员列表新增「在线」标签列：last_active_at 在 5 分钟内 = 在线
--    （窗口常量：app\live\service\UserService::ONLINE_WINDOW）
-- 3) 登录成功时也会写入，保证刚登录的用户立刻显示在线
-- 本脚本幂等，可重复执行
-- ============================================

-- --------------------------------------------
-- 1. lp_user_profile.last_active_at
-- --------------------------------------------
SET @has_col = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_user_profile' AND `COLUMN_NAME` = 'last_active_at');
SET @ddl = IF(@has_col = 0,
  'ALTER TABLE `lp_user_profile`
     ADD COLUMN `last_active_at` DATETIME NULL DEFAULT NULL COMMENT ''最近活跃时间（接口心跳）'' AFTER `last_login_at`',
  'SELECT ''lp_user_profile.last_active_at 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- --------------------------------------------
-- 2. 校验
-- --------------------------------------------
SELECT `COLUMN_NAME`, `COLUMN_TYPE`, `COLUMN_COMMENT` FROM `information_schema`.`COLUMNS`
WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_user_profile'
  AND `COLUMN_NAME` IN ('last_login_at', 'last_active_at');
