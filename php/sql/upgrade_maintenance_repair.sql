-- =========================================
-- 修复所有菜单格式问题 + 合并到直播运营
-- =========================================

-- 1. 将原有"直播运营"(name=live)的子菜单统一挂到新 liveOps 下（如果还没挂的话）
--    先取新的 liveOps ID（或旧 live 的 ID 做备用）
SET @old_live_id = (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'live' AND `type` = 'menu_dir' AND `pid` = 0 LIMIT 1);
SET @new_live_id = (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'liveOps' AND `type` = 'menu_dir' LIMIT 1);

-- 如果新 liveOps 不存在，用旧的
SET @target_id = COALESCE(@new_live_id, @old_live_id);

-- 2. 将散落的 live/* 菜单统一归到 target 下
UPDATE IGNORE `ba_admin_rule` SET `pid` = @target_id
WHERE `name` IN ('live/room', 'live/persona', 'live/gift', 'live/mediaAsset', 'live/revenue', 'live/leaderboard', 'live/replayClip', 'live/maintenance', 'user/liveUser')
AND `pid` != @target_id;

-- 3. 删除旧的重复"直播运营"菜单目录 (name=live, status=0)
DELETE FROM `ba_admin_rule` WHERE `name` = 'live' AND `type` = 'menu_dir' AND `status` = 0;

-- 4. 将 liveOps 改名为 live（和其他菜单路径风格一致），保持名称为"直播运营"
UPDATE `ba_admin_rule` SET `name` = 'live', `path` = 'live', `menu_type` = NULL
WHERE `name` = 'liveOps' AND `type` = 'menu_dir';

-- 5. 修复 path 前导 / 问题（去掉开头的 /）
UPDATE `ba_admin_rule` SET `path` = SUBSTRING(`path`, 2)
WHERE `path` LIKE '/%' AND `type` IN ('menu', 'menu_dir');

-- 6. 修复 menu_type
--    menu_dir 应该是 NULL
UPDATE `ba_admin_rule` SET `menu_type` = NULL WHERE `type` = 'menu_dir' AND `menu_type` IS NOT NULL;
--    menu 应该是 'tab'
UPDATE `ba_admin_rule` SET `menu_type` = 'tab' WHERE `type` = 'menu' AND `menu_type` NOT IN ('tab', 'link', 'iframe');

-- 7. 修复 keepalive（应为 1）
UPDATE `ba_admin_rule` SET `keepalive` = 1 WHERE `type` = 'menu' AND `keepalive` = 0;

-- 8. 补上缺失的按钮权限记录
--    历史切片按钮
SET @replay_pid = (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'live/replayClip' AND `type` = 'menu' LIMIT 1);
INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `keepalive`, `weigh`, `status`) VALUES
(@replay_pid, 'button', 'live/replayClip/index', '查看', '', '', '', NULL, 0, 10, 1),
(@replay_pid, 'button', 'live/replayClip/add', '新增', '', '', '', NULL, 0, 9, 1),
(@replay_pid, 'button', 'live/replayClip/edit', '编辑', '', '', '', NULL, 0, 8, 1),
(@replay_pid, 'button', 'live/replayClip/del', '删除', '', '', '', NULL, 0, 7, 1);

--    维护任务按钮
SET @task_pid = (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'live/maintenanceTask' AND `type` = 'menu' LIMIT 1);
INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `keepalive`, `weigh`, `status`) VALUES
(@task_pid, 'button', 'live/maintenanceTask/index', '查看', '', '', '', NULL, 0, 10, 1),
(@task_pid, 'button', 'live/maintenanceTask/add', '新增', '', '', '', NULL, 0, 9, 1),
(@task_pid, 'button', 'live/maintenanceTask/edit', '编辑', '', '', '', NULL, 0, 8, 1),
(@task_pid, 'button', 'live/maintenanceTask/del', '删除', '', '', '', NULL, 0, 7, 1);
