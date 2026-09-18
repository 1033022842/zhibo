-- ============================================
-- AI 女友端充值：star-pay 网关接入
-- 1) 新增充值套餐表 lp_recharge_package（后台可配，前端动态下发）
-- 2) 扩展 lp_recharge_order（套餐/网关单号/支付地址）
-- 3) 初始化 6 档套餐
-- 执行：mysql -u 用户 -p 库名 < upgrade_recharge_starpay.sql
-- ============================================

-- 1) 充值套餐表
CREATE TABLE IF NOT EXISTS `lp_recharge_package` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `tokens` int(11) NOT NULL DEFAULT 0 COMMENT '到账钻石/点数',
  `price` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '售价（USD）',
  `bonus_percent` int(11) NOT NULL DEFAULT 0 COMMENT '赠送比例（仅展示，如 10 表示 +10% bonus）',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '状态:0下架 1上架',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_status_sort` (`status`, `sort`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '充值套餐（AI女友端）' ROW_FORMAT = Dynamic;

-- 2) 扩展 lp_recharge_order
DROP PROCEDURE IF EXISTS `upgrade_recharge_order_starpay`;
DELIMITER //
CREATE PROCEDURE `upgrade_recharge_order_starpay`()
BEGIN
  DECLARE db_name VARCHAR(100);
  SET db_name = DATABASE();

  IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = db_name AND TABLE_NAME = 'lp_recharge_order' AND COLUMN_NAME = 'package_id') THEN
    ALTER TABLE `lp_recharge_order` ADD COLUMN `package_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '充值套餐ID' AFTER `channel_id`;
  END IF;
  IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = db_name AND TABLE_NAME = 'lp_recharge_order' AND COLUMN_NAME = 'system_ref') THEN
    ALTER TABLE `lp_recharge_order` ADD COLUMN `system_ref` varchar(64) NOT NULL DEFAULT '' COMMENT '网关平台订单号' AFTER `proof_image`;
  END IF;
  IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = db_name AND TABLE_NAME = 'lp_recharge_order' AND COLUMN_NAME = 'pay_url') THEN
    ALTER TABLE `lp_recharge_order` ADD COLUMN `pay_url` varchar(512) NOT NULL DEFAULT '' COMMENT '网关支付地址' AFTER `system_ref`;
  END IF;
END //
DELIMITER ;
CALL `upgrade_recharge_order_starpay`();
DROP PROCEDURE IF EXISTS `upgrade_recharge_order_starpay`;

-- 3) 初始化套餐（表已有数据时跳过，可重复执行）
INSERT INTO `lp_recharge_package` (`tokens`, `price`, `bonus_percent`, `sort`, `status`)
SELECT * FROM (
  SELECT 100 AS tokens, 9.99 AS price, 0 AS bonus_percent, 1 AS sort, 1 AS status UNION ALL
  SELECT 350, 34.99, 0, 2, 1 UNION ALL
  SELECT 550, 49.99, 10, 3, 1 UNION ALL
  SELECT 1150, 99.99, 15, 4, 1 UNION ALL
  SELECT 2400, 199.99, 20, 5, 1 UNION ALL
  SELECT 3750, 299.99, 25, 6, 1
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id` FROM `lp_recharge_package` LIMIT 1) AS existing);
