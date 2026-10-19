-- ============================================================
-- 登录后弹窗设置（创作指南 / 18+ 进场弹窗）
-- 2026-10-17
--
-- 后台「营销推广 → 弹窗设置」可控制：
--   开关 / 延迟秒数 / 弹出频率(每次登录/每会话/按间隔/每次打开/只一次) / 间隔小时
--
-- 前台读取接口：GET /api/live/popupConfig
-- 幂等：可重复执行。
-- ============================================================

-- ---------- 1. 默认配置 ----------
INSERT INTO `lp_platform_config` (`key`, `value`, `remark`, `updated_at`)
SELECT 'popup_guide_enabled', '1', '创作指南弹窗:1开 0关', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `lp_platform_config` WHERE `key` = 'popup_guide_enabled');

INSERT INTO `lp_platform_config` (`key`, `value`, `remark`, `updated_at`)
SELECT 'popup_guide_delay', '1', '创作指南弹窗延迟秒数(0-60)', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `lp_platform_config` WHERE `key` = 'popup_guide_delay');

INSERT INTO `lp_platform_config` (`key`, `value`, `remark`, `updated_at`)
SELECT 'popup_guide_freq', 'login', '创作指南弹窗频率:login/session/interval/always/once', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `lp_platform_config` WHERE `key` = 'popup_guide_freq');

INSERT INTO `lp_platform_config` (`key`, `value`, `remark`, `updated_at`)
SELECT 'popup_guide_interval', '24', '创作指南弹窗间隔小时(1-720)', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `lp_platform_config` WHERE `key` = 'popup_guide_interval');

INSERT INTO `lp_platform_config` (`key`, `value`, `remark`, `updated_at`)
SELECT 'popup_promo_enabled', '1', '18+进场弹窗:1开 0关', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `lp_platform_config` WHERE `key` = 'popup_promo_enabled');

INSERT INTO `lp_platform_config` (`key`, `value`, `remark`, `updated_at`)
SELECT 'popup_promo_delay', '1', '18+进场弹窗延迟秒数(0-60)', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `lp_platform_config` WHERE `key` = 'popup_promo_delay');

INSERT INTO `lp_platform_config` (`key`, `value`, `remark`, `updated_at`)
SELECT 'popup_promo_freq', 'session', '18+进场弹窗频率:login/session/interval/always/once', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `lp_platform_config` WHERE `key` = 'popup_promo_freq');

INSERT INTO `lp_platform_config` (`key`, `value`, `remark`, `updated_at`)
SELECT 'popup_promo_interval', '24', '18+进场弹窗间隔小时(1-720)', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `lp_platform_config` WHERE `key` = 'popup_promo_interval');

-- ---------- 2. 后台菜单（营销推广 → 弹窗设置） ----------
INSERT INTO `ba_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
SELECT t.pid, 'menu', '弹窗设置', 'live/popupSetting', 'live/popupSetting', 'fa fa-window-restore', 'iframe', '/admin/live.PopupSetting/index', '', 0, 'none', '登录后弹窗开关/延迟/弹出频率', 96, 1
FROM (SELECT `id` AS `pid` FROM `ba_admin_rule` WHERE `name` = 'marketing' AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1) t
WHERE NOT EXISTS (SELECT 1 FROM `ba_admin_rule` WHERE `name` = 'live/popupSetting');

-- 兜底：菜单若被插到别处，挪回「营销推广」
UPDATE `ba_admin_rule`
SET `pid` = (SELECT `id` FROM (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'marketing' AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1) m)
WHERE `name` = 'live/popupSetting' AND `type` = 'menu'
  AND `pid` <> (SELECT `id` FROM (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'marketing' AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1) m2);

-- ---------- 3. 非超管分组：把新菜单追加到已有「营销推广」权限的分组 ----------
UPDATE `ba_admin_group`
SET `rules` = CONCAT(RTRIM(`rules`), ',',
    (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'live/popupSetting' AND `type` = 'menu' LIMIT 1))
WHERE `rules` IS NOT NULL AND `rules` <> ''
  AND FIND_IN_SET((SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'marketing' AND `type` = 'menu_dir' LIMIT 1), `rules`)
  AND NOT FIND_IN_SET((SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'live/popupSetting' AND `type` = 'menu' LIMIT 1), `rules`);

-- ---------- 4. 核验 ----------
SELECT `id`, `pid`, `type`, `title`, `name`, `path`, `menu_type`, `url`, `status`
FROM `ba_admin_rule`
WHERE `name` IN ('marketing', 'live/popupSetting')
ORDER BY `id`;

SELECT `key`, `value`, `remark`
FROM `lp_platform_config`
WHERE `key` LIKE 'popup_%'
ORDER BY `key`;
