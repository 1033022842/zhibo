-- ============================================
-- 流量来源：落地访问记录（点击 / 访客）
-- 1) 新建 lp_promo_visit：前台每次会话落地写入一行（同一会话去重）
--    记录 域名 / 平台 / Referer / 渠道 / 推广链接 / 像素 / 广告宏参数
-- 2) lp_user.reg_platform 兜底补齐（老库可能没有该列）
-- 3) 后台菜单「推广来源」改名为「流量来源」
-- 本脚本幂等，可重复执行
-- ============================================

CREATE TABLE IF NOT EXISTS `lp_promo_visit` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `vid`        VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '访客ID（浏览器维度，1年）',
  `sid`        VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '会话ID（标签页维度，用于去重）',
  `domain`     VARCHAR(120) NOT NULL DEFAULT '' COMMENT '落地域名',
  `platform`   VARCHAR(32)  NOT NULL DEFAULT '' COMMENT '归一化平台 x/tiktok/instagram/reddit/threads/youtube/direct/...',
  `referer`    VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'Referer 主机名（原始，用于排查）',
  `channel`    VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '?ch=',
  `link_id`    INT UNSIGNED NOT NULL DEFAULT 0  COMMENT '?l= 推广链接ID',
  `link_name`  VARCHAR(128) NOT NULL DEFAULT '' COMMENT '推广链接名称快照',
  `px`         VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '?px= 渠道/像素ID',
  `campaign`   VARCHAR(128) NOT NULL DEFAULT '' COMMENT '广告系列',
  `adset`      VARCHAR(128) NOT NULL DEFAULT '' COMMENT '广告组',
  `ad`         VARCHAR(128) NOT NULL DEFAULT '' COMMENT '广告',
  `landing`    VARCHAR(255) NOT NULL DEFAULT '' COMMENT '落地页路径',
  `ua`         VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'User-Agent',
  `ip`         VARCHAR(45)  NOT NULL DEFAULT '' COMMENT '访客IP',
  `country`    VARCHAR(8)   NOT NULL DEFAULT '' COMMENT '国家代码（CDN 头，可选）',
  `created_at` DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sid` (`sid`),
  KEY `idx_created` (`created_at`),
  KEY `idx_domain_created` (`domain`, `created_at`),
  KEY `idx_platform_created` (`platform`, `created_at`),
  KEY `idx_vid` (`vid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='流量来源：落地访问记录';

-- --------------------------------------------
-- lp_user.reg_platform（老库兜底）
-- --------------------------------------------
SET @has_col = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_user' AND `COLUMN_NAME` = 'reg_platform');
SET @ddl = IF(@has_col = 0,
  'ALTER TABLE `lp_user`
     ADD COLUMN `reg_platform` VARCHAR(32) NOT NULL DEFAULT '''' COMMENT ''注册来源平台'' AFTER `reg_channel`,
     ADD INDEX `idx_reg_platform` (`reg_platform`)',
  'SELECT ''lp_user.reg_platform 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- --------------------------------------------
-- 后台菜单：推广来源 → 流量来源
-- --------------------------------------------
UPDATE `ba_admin_rule`
SET `title` = '流量来源',
    `remark` = '按平台/域名/链接统计访问、注册与付费'
WHERE `name` = 'live/promoSource';

-- --------------------------------------------
-- 校验
-- --------------------------------------------
SHOW TABLES LIKE 'lp_promo_visit';
SELECT `COLUMN_NAME`, `COLUMN_TYPE`, `COLUMN_COMMENT` FROM `information_schema`.`COLUMNS`
WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_user'
  AND `COLUMN_NAME` IN ('reg_platform', 'reg_channel', 'reg_px', 'reg_link_name');
SELECT `id`, `pid`, `title`, `name`, `url`, `status` FROM `ba_admin_rule` WHERE `name` = 'live/promoSource';
