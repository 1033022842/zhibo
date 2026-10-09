-- ============================================
-- 注册渠道名称快照
-- lp_user.reg_channel_name：注册时把「渠道配置名称」快照下来
-- 这样后续渠道配置被改名或删除，都不会再影响历史会员的归属展示
-- 本脚本幂等，可重复执行
-- ============================================

SET @has_col = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_user' AND `COLUMN_NAME` = 'reg_channel_name');
SET @ddl = IF(@has_col = 0,
  'ALTER TABLE `lp_user`
     ADD COLUMN `reg_channel_name` VARCHAR(128) NOT NULL DEFAULT '''' COMMENT ''注册渠道名称快照(?px= 解析出的渠道配置名)'' AFTER `reg_px`',
  'SELECT ''lp_user.reg_channel_name 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- 回填①：按 reg_px 匹配渠道配置主键
UPDATE `lp_user` u
JOIN `lp_promo_pixel` p ON p.`id` = u.`reg_px`
SET u.`reg_channel_name` = p.`name`
WHERE u.`reg_channel_name` = '' AND u.`reg_px` <> '' AND p.`name` <> '';

-- 回填②：按 reg_px 匹配像素ID（旧数据可能存的是像素ID字符串）
UPDATE `lp_user` u
JOIN `lp_promo_pixel` p ON p.`pixel_id` = u.`reg_px`
SET u.`reg_channel_name` = p.`name`
WHERE u.`reg_channel_name` = '' AND u.`reg_px` <> '' AND p.`pixel_id` <> '' AND p.`name` <> '';

-- 回填③：没有像素映射的，用推广链接名称兜底，再退到 ch 参数
UPDATE `lp_user`
SET `reg_channel_name` = CASE WHEN `reg_link_name` <> '' THEN `reg_link_name` ELSE `reg_channel` END
WHERE `reg_channel_name` = '';

-- 校验
SELECT COUNT(*) AS total, SUM(`reg_channel_name` = '') AS empty_name FROM `lp_user`;
