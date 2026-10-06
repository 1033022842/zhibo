-- ============================================
-- 推广链接（可命名，按链接名称归因）
-- 1) 新建 lp_promo_link：每条链接 = 名称 + 域名 + 绑定像素 + 渠道
--    生成的链接形如 https://域名/?px=像素ID&name=名称&ch=渠道
--    客户注册时记录 reg_link_name，后台按名称区分来源
-- 2) 后台菜单「推广链接」（挂在「营销推广」目录下）
-- 本脚本幂等，可重复执行
-- ============================================

CREATE TABLE IF NOT EXISTS `lp_promo_link` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(128) NOT NULL DEFAULT '' COMMENT '链接名称（链接带 ?name=，注册时记录）',
  `domain`     VARCHAR(120) NOT NULL DEFAULT '' COMMENT '推广域名',
  `pixel_id`   INT UNSIGNED NOT NULL DEFAULT 0  COMMENT '绑定像素 lp_promo_pixel.id，0=不绑定',
  `channel`    VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '渠道参数 ?ch=',
  `remark`     VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注',
  `sort`       INT          NOT NULL DEFAULT 0,
  `status`     TINYINT      NOT NULL DEFAULT 1  COMMENT '1启用 0停用',
  `created_at` DATETIME     DEFAULT NULL,
  `updated_at` DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='推广链接（可命名，按名称归因）';

-- --------------------------------------------
-- 后台菜单：推广链接
-- --------------------------------------------
SET @mkt_pid = IFNULL((SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'marketing' AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1), 0);

INSERT INTO `ba_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
SELECT @mkt_pid, 'menu', '推广链接', 'live/promoLink', 'live/promoLink', 'fa fa-link', 'iframe', '/admin/live.PromoLink/index', '', 0, 'none', '创建并管理推广链接（按链接名称区分来源）', 96, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `ba_admin_rule` WHERE `name` = 'live/promoLink');

-- --------------------------------------------
-- 校验
-- --------------------------------------------
SHOW TABLES LIKE 'lp_promo_link';
SELECT `id`, `pid`, `title`, `name`, `url`, `status` FROM `ba_admin_rule` WHERE `name` = 'live/promoLink';
