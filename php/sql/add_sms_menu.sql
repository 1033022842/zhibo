-- 短信服务配置菜单
-- routine.SmsConfig 控制器路由
INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `keepalive`, `weigh`, `status`) VALUES
(0, 'menu_dir', 'routine', '系统管理', 'fa fa-gears', 'routine', '', NULL, 0, 90, 1);

SET @routine_pid = (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'routine' LIMIT 1);

INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `keepalive`, `weigh`, `status`) VALUES
(@routine_pid, 'menu', 'routine/smsConfig', '短信服务配置', 'fa fa-message', 'routine/smsConfig', '/src/views/backend/routine/smsConfig/index.vue', 'tab', 1, 4, 1),
(@routine_pid, 'menu', 'routine/SmsConfig', '短信服务配置', 'fa fa-message', 'routine/smsConfig', '/src/views/backend/routine/smsConfig/index.vue', 'tab', 1, 0, 0);
