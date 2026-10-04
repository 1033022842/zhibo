-- ============================================
-- 推广来源归因（按域名/渠道统计，不依赖任何像素）
-- 1) lp_user 增加 reg_domain / reg_channel：用户注册时的来源域名与渠道参数
-- 2) 后台菜单「推广来源」（挂在「营销推广」目录下）
-- 本脚本幂等，可重复执行
-- ============================================

-- --------------------------------------------
-- 1. lp_user.reg_domain + reg_channel
-- --------------------------------------------
SET @has_col = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_user' AND `COLUMN_NAME` = 'reg_domain');
SET @ddl = IF(@has_col = 0,
  'ALTER TABLE `lp_user`
     ADD COLUMN `reg_domain` VARCHAR(120) NOT NULL DEFAULT '''' COMMENT ''注册来源域名'' ,
     ADD COLUMN `reg_channel` VARCHAR(64) NOT NULL DEFAULT '''' COMMENT ''注册来源渠道参数(?ch=)'',
     ADD INDEX `idx_reg_domain` (`reg_domain`)',
  'SELECT ''lp_user.reg_domain 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- --------------------------------------------
-- 2. 后台菜单：推广来源
-- --------------------------------------------
SET @mkt_pid = IFNULL((SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'marketing' AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1), 0);

INSERT INTO `ba_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
SELECT @mkt_pid, 'menu', '推广来源', 'live/promoSource', 'live/promoSource', 'fa fa-share-alt', 'iframe', '/admin/live.PromoSource/index', '', 0, 'none', '按域名/渠道统计注册与充值来源', 97, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `ba_admin_rule` WHERE `name` = 'live/promoSource');

-- --------------------------------------------
-- 3. 校验
-- --------------------------------------------
SELECT `COLUMN_NAME`, `COLUMN_TYPE`, `COLUMN_COMMENT` FROM `information_schema`.`COLUMNS`
WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_user'
  AND `COLUMN_NAME` IN ('reg_domain', 'reg_channel');
SELECT `id`, `pid`, `title`, `name`, `url`, `status` FROM `ba_admin_rule` WHERE `name` = 'live/promoSource';
