-- 营销推广（像素）+ 客服渠道配置
-- 2026-09-30
--
-- 1) lp_platform_config 增加：各像素开关与 ID、转化事件开关、客服弹窗全局设置
-- 2) 新表 lp_cs_channel：客服渠道（Telegram / WhatsApp / Line / Messenger / 邮件 / 外链 / 嵌入）
--    渠道点击计数也记在这张表（clicks），后台列表直接看哪个渠道被点得多
-- 3) 后台菜单：新建目录「营销推广」，下挂「推广与像素」「客服渠道」

-- ---------- 1. 配置项 ----------
CREATE TABLE IF NOT EXISTS lp_platform_config (
    `key` VARCHAR(64) NOT NULL,
    `value` VARCHAR(255) NOT NULL DEFAULT '',
    remark VARCHAR(255) NOT NULL DEFAULT '',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='平台资金与运营配置';

INSERT IGNORE INTO lp_platform_config (`key`, `value`, remark) VALUES
('promo_fb_enabled',      '0', '推广：Meta(Facebook) Pixel 开关'),
('promo_fb_pixel_id',     '',  '推广：Meta(Facebook) Pixel ID'),
('promo_tt_enabled',      '0', '推广：TikTok Pixel 开关'),
('promo_tt_pixel_id',     '',  '推广：TikTok Pixel ID'),
('promo_gg_enabled',      '0', '推广：Google（GA4 / Ads / GTM）开关'),
('promo_ga4_id',          '',  '推广：GA4 衡量 ID（G-XXXXXXX）'),
('promo_ads_id',          '',  '推广：Google Ads 转化 ID（AW-XXXXXXX）'),
('promo_gtm_id',          '',  '推广：GTM 容器 ID（GTM-XXXXXXX）'),
('promo_ev_register',     '1', '转化事件：注册成功上报'),
('promo_ev_recharge',     '1', '转化事件：充值/支付成功上报'),
('promo_ev_subscribe',    '1', '转化事件：订阅购买成功上报'),

('cs_enabled',            '1', '客服：总开关'),
('cs_float',              '1', '客服：右下角悬浮按钮开关'),
('cs_title',              'Contact us', '客服弹窗标题'),
('cs_subtitle',           'Pick a channel below — we usually reply within minutes.', '客服弹窗副标题'),
('cs_hint',               'Chat with us', '悬浮按钮气泡文案'),
('cs_logo',               '',  '客服图标（图片 URL，留空用内置图标）');

-- ---------- 2. 客服渠道表 ----------
CREATE TABLE IF NOT EXISTS lp_cs_channel (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type` VARCHAR(16) NOT NULL DEFAULT 'link' COMMENT 'telegram/whatsapp/line/messenger/email/link/embed',
    `name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '展示名，如 "Telegram 客服"',
    `url` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '链接（email 填邮箱，embed 填可嵌入地址）',
    icon VARCHAR(500) NOT NULL DEFAULT '' COMMENT '自定义图标 URL，留空用该类型内置图标',
    sort INT NOT NULL DEFAULT 0 COMMENT '越小越靠前',
    status TINYINT NOT NULL DEFAULT 1 COMMENT '1启用 0停用',
    clicks INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '前端点击数',
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_status_sort (status, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='前端客服渠道';

-- ---------- 3. 后台菜单 ----------
-- 新建目录「营销推广」（与「内容运营」「财务与审核」同级）
INSERT INTO `ba_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
SELECT 0, 'menu_dir', '营销推广', 'marketing', 'marketing', 'fa fa-bullhorn', NULL, '', '', 0, 'none', '像素与客服配置', 117, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `ba_admin_rule` WHERE `name` = 'marketing' AND `type` = 'menu_dir');

SET @mkt_pid = (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'marketing' AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1);

INSERT INTO `ba_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
SELECT @mkt_pid, 'menu', '推广与像素', 'live/promoSetting', 'live/promoSetting', 'fa fa-flag', 'iframe', '/admin/live.PromoSetting/index', '', 0, 'none', 'Facebook/TikTok/Google 像素与转化事件', 98, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `ba_admin_rule` WHERE `name` = 'live/promoSetting');

INSERT INTO `ba_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
SELECT @mkt_pid, 'menu', '客服渠道', 'live/csChannel', 'live/csChannel', 'fa fa-comments', 'iframe', '/admin/live.CsChannel/index', '', 0, 'none', 'Telegram/WhatsApp/Line 等客服渠道与点击统计', 97, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `ba_admin_rule` WHERE `name` = 'live/csChannel');

-- 兜底修正：菜单若被插到别处，挪回「营销推广」
UPDATE `ba_admin_rule` SET `pid` = @mkt_pid
WHERE `name` IN ('live/promoSetting', 'live/csChannel') AND `pid` <> @mkt_pid;
