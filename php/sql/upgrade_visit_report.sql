-- ============================================
-- 后台菜单：「营销推广 → 域名访问统计」（UV / PV / IP）
-- 执行：source php/sql/upgrade_visit_report.sql   （各站库分别执行，幂等）
--
-- 依赖：lp_promo_visit 表（php/sql/upgrade_promo_visit.sql；尚未执行的站先跑它）
-- 页面：/admin/live.VisitReport/index?_key=<ADMIN_FLOAT_KEY>（后台 iframe 内自动带 Cookie）
-- ============================================

SET @mkt_pid = IFNULL((SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'marketing' AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1), 0);

INSERT INTO `ba_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
SELECT @mkt_pid, 'menu', '域名访问统计', 'live/visitReport', 'live/visitReport', 'fa fa-bar-chart', 'iframe', '/admin/live.VisitReport/index', '', 0, 'none', '按域名统计 UV / PV / IP', 96, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `ba_admin_rule` WHERE `name` = 'live/visitReport');

SELECT `id`, `pid`, `title`, `name`, `url`, `status` FROM `ba_admin_rule` WHERE `name` = 'live/visitReport';
