-- ============================================
-- 会员分层（Premium / Premium Plus）与权益落地
-- 1) lp_vip_plan 扩展：层级 + 权益数值 + 展示文案
-- 2) 新建 lp_user_quota：免费用户每日额度计数（聊天等）
-- 3) 平台配置：免费用户每日聊天条数
-- 4) 存量 3 档归入 premium，新增 3 档 premium_plus
-- 执行：mysql -u 用户 -p 库名 < upgrade_vip_tier.sql
-- ============================================

-- 1) lp_vip_plan 扩展字段
DROP PROCEDURE IF EXISTS `upgrade_vip_plan_tier`;
DELIMITER //
CREATE PROCEDURE `upgrade_vip_plan_tier`()
BEGIN
  DECLARE db_name VARCHAR(100);
  SET db_name = DATABASE();

  IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = db_name AND TABLE_NAME = 'lp_vip_plan' AND COLUMN_NAME = 'level') THEN
    ALTER TABLE `lp_vip_plan` ADD COLUMN `level` varchar(16) NOT NULL DEFAULT 'premium' COMMENT '层级:premium/premium_plus' AFTER `months`;
  END IF;
  IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = db_name AND TABLE_NAME = 'lp_vip_plan' AND COLUMN_NAME = 'benefits') THEN
    ALTER TABLE `lp_vip_plan` ADD COLUMN `benefits` text NULL COMMENT '权益展示文案(JSON数组)' AFTER `daily_diamond`;
  END IF;
  IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = db_name AND TABLE_NAME = 'lp_vip_plan' AND COLUMN_NAME = 'chat_daily_limit') THEN
    ALTER TABLE `lp_vip_plan` ADD COLUMN `chat_daily_limit` int(11) NOT NULL DEFAULT 0 COMMENT '每日聊天条数,0=不限' AFTER `benefits`;
  END IF;
  IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = db_name AND TABLE_NAME = 'lp_vip_plan' AND COLUMN_NAME = 'image_daily_limit') THEN
    ALTER TABLE `lp_vip_plan` ADD COLUMN `image_daily_limit` int(11) NOT NULL DEFAULT 0 COMMENT '每日图片/视频生成次数,0=不可用' AFTER `chat_daily_limit`;
  END IF;
  IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = db_name AND TABLE_NAME = 'lp_vip_plan' AND COLUMN_NAME = 'discount_percent') THEN
    ALTER TABLE `lp_vip_plan` ADD COLUMN `discount_percent` int(11) NOT NULL DEFAULT 0 COMMENT '内容/商城折扣百分比,10=9折' AFTER `image_daily_limit`;
  END IF;
  IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = db_name AND TABLE_NAME = 'lp_vip_plan' AND COLUMN_NAME = 'custom_video') THEN
    ALTER TABLE `lp_vip_plan` ADD COLUMN `custom_video` tinyint(4) NOT NULL DEFAULT 1 COMMENT '是否可用定制视频/图片生成:0否 1是' AFTER `discount_percent`;
  END IF;
END //
DELIMITER ;
CALL `upgrade_vip_plan_tier`();
DROP PROCEDURE IF EXISTS `upgrade_vip_plan_tier`;

-- 2) 每日额度计数表
CREATE TABLE IF NOT EXISTS `lp_user_quota` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL COMMENT '用户ID',
  `quota_date` date NOT NULL COMMENT '统计日期',
  `quota_key` varchar(32) NOT NULL COMMENT '额度类型:chat/image',
  `used` int(11) NOT NULL DEFAULT 0 COMMENT '已使用次数',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_user_date_key` (`user_id`, `quota_date`, `quota_key`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '用户每日额度计数' ROW_FORMAT = Dynamic;

-- 3) 平台配置：免费用户每日聊天条数（0=不限）
INSERT IGNORE INTO `lp_platform_config` (`key`, `value`, `remark`)
VALUES ('free_chat_daily_limit', '20', '免费用户每日聊天条数,0=不限');

-- 3b) 平台配置：会员是否可看清未购买内容的封面（1=去模糊；默认 0，避免泄露付费内容）
INSERT IGNORE INTO `lp_platform_config` (`key`, `value`, `remark`)
VALUES ('vip_unblur_locked_preview', '0', '会员去除未购买内容封面模糊:1开 0关');

-- 4a) 存量 3 档归入 premium 并补齐权益数值
UPDATE `lp_vip_plan` SET
  `level`            = 'premium',
  `chat_daily_limit` = 0,
  `image_daily_limit`= 3,
  `discount_percent` = 10,
  `custom_video`     = 1,
  `benefits`         = '["Unlimited text messages","3 image / video generations every day","Daily diamonds to spend anywhere","10% off private content & shop","Remove image blur","Exclusive Premium badge"]'
WHERE `months` IN (1, 3, 12) AND (`level` = 'premium' OR `level` = '');

-- 4b) 新增 premium_plus 三档（按月数幂等）
INSERT INTO `lp_vip_plan` (`name`, `months`, `level`, `usd_price`, `diamond_price`, `daily_diamond`, `benefits`, `chat_daily_limit`, `image_daily_limit`, `discount_percent`, `custom_video`, `sort`, `status`)
SELECT * FROM (
  SELECT '月度会员 Plus' AS name, 1 AS months, 'premium_plus' AS level, 19.99 AS usd_price, 2000 AS diamond_price, 50 AS daily_diamond,
         '["Everything in Premium","10 image / video generations every day","50~80 diamonds every day","20% off private content & shop","Priority generation queue","Remove image blur","Exclusive Premium Plus badge"]' AS benefits,
         0 AS chat_daily_limit, 10 AS image_daily_limit, 20 AS discount_percent, 1 AS custom_video, 7 AS sort, 1 AS status UNION ALL
  SELECT '季度会员 Plus', 3, 'premium_plus', 49.99, 5000, 60,
         '["Everything in Premium","10 image / video generations every day","50~80 diamonds every day","20% off private content & shop","Priority generation queue","Remove image blur","Exclusive Premium Plus badge"]',
         0, 10, 20, 1, 8, 1 UNION ALL
  SELECT '年度会员 Plus', 12, 'premium_plus', 139.99, 16000, 80,
         '["Everything in Premium","10 image / video generations every day","50~80 diamonds every day","20% off private content & shop","Priority generation queue","Remove image blur","Exclusive Premium Plus badge"]',
         0, 10, 20, 1, 9, 1
) AS seed
WHERE NOT EXISTS (
  SELECT 1 FROM (SELECT `id` FROM `lp_vip_plan` WHERE `level` = 'premium_plus' LIMIT 1) AS existing
);
