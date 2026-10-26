-- ============================================
-- AI 女友端：站点首页可配（后台菜单）
-- 后台「内容运营 → 站点首页」，选一个 html 作为站点根路径 / 的落地页
-- 在 zhibo 数据库执行；脚本可重复执行
-- 业务代码：app/admin/controller/live/SiteHome.php、ai-girl-malaysia.com/index.php
-- ============================================

-- 后台菜单（挂在「内容运营」目录下，与首页轮播图同级）
SET @content_pid = IFNULL((SELECT `id` FROM `ba_admin_rule` WHERE `title` = '内容运营' AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1), 0);

INSERT INTO `ba_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
VALUES (@content_pid, 'menu', '站点首页', 'live/siteHome', 'live/siteHome', 'fa fa-home', 'iframe', '/admin/live.SiteHome/index', '', 0, 'none', '切换站点根路径 / 指向哪个页面', 8, 1)
ON DUPLICATE KEY UPDATE `pid`=VALUES(`pid`), `title`='站点首页', `menu_type`='iframe', `url`='/admin/live.SiteHome/index', `status`=1;

SELECT `id`, `pid`, `title`, `name`, `menu_type`, `url`, `status` FROM `ba_admin_rule` WHERE `name` = 'live/siteHome';
