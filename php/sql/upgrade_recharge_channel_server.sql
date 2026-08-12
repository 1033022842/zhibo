-- ============================================
-- 充值渠道 + 充值审核 升级脚本（服务器版，数据库名自适应）
-- 在服务器执行: mysql -u zhibo -p'12345678' zhibo < 此文件
-- ============================================

-- 充值渠道配置表（不存在才建）
CREATE TABLE IF NOT EXISTS `lp_recharge_channel` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `name` varchar(64) NOT NULL COMMENT '渠道名称',
  `type` varchar(32) NOT NULL DEFAULT 'usdt_trc20' COMMENT '渠道类型',
  `qr_code_url` varchar(512) NOT NULL DEFAULT '' COMMENT '收款二维码图片URL',
  `address` varchar(255) NOT NULL DEFAULT '' COMMENT '收款地址/账号',
  `diamond_rate` decimal(18, 2) NOT NULL DEFAULT 100.00 COMMENT '汇率',
  `min_amount` decimal(18, 2) NOT NULL DEFAULT 10.00 COMMENT '最低充值金额',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '状态:0禁用 1启用',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_status_sort` (`status`, `sort`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '充值渠道配置';

-- 扩展 lp_recharge_order 字段
-- 用 DATABASE() 动态获取当前数据库名
DROP PROCEDURE IF EXISTS `upgrade_recharge_order`;
DELIMITER //
CREATE PROCEDURE `upgrade_recharge_order`()
BEGIN
  DECLARE db_name VARCHAR(100);
  SET db_name = DATABASE();

  IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = db_name AND TABLE_NAME = 'lp_recharge_order' AND COLUMN_NAME = 'channel_id') THEN
    ALTER TABLE `lp_recharge_order` ADD COLUMN `channel_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '充值渠道ID' AFTER `pay_channel`;
  END IF;
  IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = db_name AND TABLE_NAME = 'lp_recharge_order' AND COLUMN_NAME = 'proof_image') THEN
    ALTER TABLE `lp_recharge_order` ADD COLUMN `proof_image` varchar(512) NOT NULL DEFAULT '' COMMENT '支付凭证截图' AFTER `diamond_amount`;
  END IF;
  IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = db_name AND TABLE_NAME = 'lp_recharge_order' AND COLUMN_NAME = 'admin_remark') THEN
    ALTER TABLE `lp_recharge_order` ADD COLUMN `admin_remark` varchar(255) NOT NULL DEFAULT '' COMMENT '审核备注' AFTER `proof_image`;
  END IF;
  IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = db_name AND TABLE_NAME = 'lp_recharge_order' AND COLUMN_NAME = 'reviewed_at') THEN
    ALTER TABLE `lp_recharge_order` ADD COLUMN `reviewed_at` datetime DEFAULT NULL COMMENT '审核时间' AFTER `paid_at`;
  END IF;
END //
DELIMITER ;
CALL `upgrade_recharge_order`();
DROP PROCEDURE IF EXISTS `upgrade_recharge_order`;

-- 初始渠道数据（已存在则跳过）
INSERT IGNORE INTO `lp_recharge_channel` (`name`, `type`, `qr_code_url`, `address`, `diamond_rate`, `min_amount`, `sort`, `status`) VALUES
('USDT-TRC20', 'usdt_trc20', '', 'TXNfC3v8bqPzKnDPKxEzMxEMMzRzUvSRKx', 100.00, 10.00, 1, 1);
