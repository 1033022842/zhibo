-- 直播平台注册用户管理菜单
SET @user_pid = (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'user' AND `type` = 'menu_dir' LIMIT 1);

INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `weigh`, `status`) VALUES
(@user_pid, 'menu', 'user/liveUser', '直播平台用户', 'fa fa-users', '/admin/user/liveUser', 'user/liveUser/index', 0, 5, 1);
