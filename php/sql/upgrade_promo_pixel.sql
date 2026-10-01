-- 推广像素升级：单条 → 多条（无限添加）
-- 2026-09-30
--
-- 背景：原来 Meta / TikTok / Google 各只能配一个 ID（存在 lp_platform_config）。
-- 现在改为独立表 lp_promo_pixel，一条记录 = 一个像素，可无限添加，每条可单独设置：
--   platform  推广渠道（facebook / tiktok / google / snapchat / kwai / bing / pinterest / other）
--   pixel_id  像素 ID（GA4 填 G-xxx、Ads 填 AW-xxx、GTM 填 GTM-xxx）
--   events    该像素要接收的事件（register 完成注册 / recharge 购物·充值 / subscribe 订阅）
--   domains   推广域名：只在匹配的域名上生效（空 = 全部域名；支持 a.com,b.com 与 *.a.com）
--   code      像素代码：原样注入页面（用于 Snapchat / Kwai 等未内置平台或补充自定义脚本）
--   test_code 测试回调编码：Events Manager 里的测试代码，用于验证事件是否真的回传到广告后台
--
-- 旧的 promo_*_pixel_id 配置会被自动迁移成像素记录（仅首次执行、且表为空时）。

CREATE TABLE IF NOT EXISTS lp_promo_pixel (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    platform VARCHAR(16) NOT NULL DEFAULT 'facebook' COMMENT '推广渠道 facebook/tiktok/google/snapchat/kwai/bing/pinterest/other',
    name VARCHAR(50) NOT NULL DEFAULT '' COMMENT '备注名，如 "FB-马来1号"',
    pixel_id VARCHAR(100) NOT NULL DEFAULT '' COMMENT '像素ID',
    events VARCHAR(120) NOT NULL DEFAULT 'register,recharge,subscribe' COMMENT '接收的事件，逗号分隔',
    domains VARCHAR(500) NOT NULL DEFAULT '' COMMENT '推广域名，逗号/换行分隔，空=全部；支持 *.example.com',
    code MEDIUMTEXT NULL COMMENT '像素代码（原样注入页面）',
    test_code VARCHAR(100) NOT NULL DEFAULT '' COMMENT '测试回调编码（广告后台的测试代码）',
    sort INT NOT NULL DEFAULT 0 COMMENT '越小越靠前',
    status TINYINT NOT NULL DEFAULT 1 COMMENT '1启用 0停用',
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_status_sort (status, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='推广像素（支持多条）';

-- ---------- 旧配置迁移（只在表为空时执行一次）----------
SET @px_empty = (SELECT COUNT(*) FROM lp_promo_pixel);

-- 旧配置里开关是关的，迁移后也保持停用（status=0），避免把测试用的像素误启用
INSERT INTO lp_promo_pixel (platform, name, pixel_id, events, domains, code, test_code, sort, status, created_at, updated_at)
SELECT 'facebook', 'Meta Pixel（由旧配置迁移）', TRIM(p.`value`), 'register,recharge,subscribe', '', '', '', 10,
       IF(COALESCE(f.`value`, '0') = '1', 1, 0), NOW(), NOW()
FROM lp_platform_config p
LEFT JOIN lp_platform_config f ON f.`key` = 'promo_fb_enabled'
WHERE p.`key` = 'promo_fb_pixel_id' AND TRIM(p.`value`) <> '' AND @px_empty = 0;

INSERT INTO lp_promo_pixel (platform, name, pixel_id, events, domains, code, test_code, sort, status, created_at, updated_at)
SELECT 'tiktok', 'TikTok Pixel（由旧配置迁移）', TRIM(p.`value`), 'register,recharge,subscribe', '', '', '', 20,
       IF(COALESCE(f.`value`, '0') = '1', 1, 0), NOW(), NOW()
FROM lp_platform_config p
LEFT JOIN lp_platform_config f ON f.`key` = 'promo_tt_enabled'
WHERE p.`key` = 'promo_tt_pixel_id' AND TRIM(p.`value`) <> '' AND @px_empty = 0;

INSERT INTO lp_promo_pixel (platform, name, pixel_id, events, domains, code, test_code, sort, status, created_at, updated_at)
SELECT 'google', 'GA4（由旧配置迁移）', TRIM(p.`value`), 'register,recharge,subscribe', '', '', '', 30,
       IF(COALESCE(f.`value`, '0') = '1', 1, 0), NOW(), NOW()
FROM lp_platform_config p
LEFT JOIN lp_platform_config f ON f.`key` = 'promo_gg_enabled'
WHERE p.`key` = 'promo_ga4_id' AND TRIM(p.`value`) <> '' AND @px_empty = 0;

INSERT INTO lp_promo_pixel (platform, name, pixel_id, events, domains, code, test_code, sort, status, created_at, updated_at)
SELECT 'google', 'Google Ads（由旧配置迁移）', TRIM(p.`value`), 'register,recharge,subscribe', '', '', '', 40,
       IF(COALESCE(f.`value`, '0') = '1', 1, 0), NOW(), NOW()
FROM lp_platform_config p
LEFT JOIN lp_platform_config f ON f.`key` = 'promo_gg_enabled'
WHERE p.`key` = 'promo_ads_id' AND TRIM(p.`value`) <> '' AND @px_empty = 0;

INSERT INTO lp_promo_pixel (platform, name, pixel_id, events, domains, code, test_code, sort, status, created_at, updated_at)
SELECT 'google', 'GTM（由旧配置迁移）', TRIM(p.`value`), 'register,recharge,subscribe', '', '', '', 50,
       IF(COALESCE(f.`value`, '0') = '1', 1, 0), NOW(), NOW()
FROM lp_platform_config p
LEFT JOIN lp_platform_config f ON f.`key` = 'promo_gg_enabled'
WHERE p.`key` = 'promo_gtm_id' AND TRIM(p.`value`) <> '' AND @px_empty = 0;

-- 全局事件总开关（三个事件各自的总闸，关闭后所有像素都不上报该事件）
INSERT IGNORE INTO lp_platform_config (`key`, `value`, remark) VALUES
('promo_ev_register',  '1', '转化事件总开关：完成注册'),
('promo_ev_recharge',  '1', '转化事件总开关：购物/充值'),
('promo_ev_subscribe', '1', '转化事件总开关：订阅');

-- 前端域名列表（像素「推广域名」下拉的数据源；前台访问时自动收录，后台也可手动维护）
INSERT IGNORE INTO lp_platform_config (`key`, `value`, remark) VALUES
('promo_site_domains', '', '前端域名（自动收录 + 后台可维护）');
