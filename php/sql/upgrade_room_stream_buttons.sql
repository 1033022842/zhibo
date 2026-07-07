-- 房间推流控制按钮权限
-- 需要 room 菜单（name='live/room', type='menu'）已存在

SET @room_pid = (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'live/room' AND `type` = 'menu' LIMIT 1);
INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`) VALUES
(@room_pid, 'button', '开播', 'live/room/startStream', '', '', NULL, '', '', 0, 'none', '', 0, 1),
(@room_pid, 'button', '关播', 'live/room/stopStream', '', '', NULL, '', '', 0, 'none', '', 0, 1),
(@room_pid, 'button', '推流状态', 'live/room/streamStatus', '', '', NULL, '', '', 0, 'none', '', 0, 1);
