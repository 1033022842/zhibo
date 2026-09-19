-- ============================================
-- 会员订阅支持 star-pay 网关支付
-- 1) lp_recharge_order 扩展：biz_type（coin 充值 / vip 订阅）+ vip_plan_id
-- 2) 月付套餐 9.99 → 10.00（网关 USD 最小金额 10.00）
-- 执行：mysql -u 用户 -p 库名 < upgrade_vip_starpay.sql
-- ============================================

DROP PROCEDURE IF EXISTS `upgrade_recharge_order_vip`;
DELIMITER //
CREATE PROCEDURE `upgrade_recharge_order_vip`()
BEGIN
  DECLARE db_name VARCHAR(100);
  SET db_name = DATABASE();

  IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = db_name AND TABLE_NAME = 'lp_recharge_order' AND COLUMN_NAME = 'biz_type') THEN
    ALTER TABLE `lp_recharge_order` ADD COLUMN `biz_type` varchar(16) NOT NULL DEFAULT 'coin' COMMENT '业务类型:coin充值 vip订阅' AFTER `package_id`;
  END IF;
  IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = db_name AND TABLE_NAME = 'lp_recharge_order' AND COLUMN_NAME = 'vip_plan_id') THEN
    ALTER TABLE `lp_recharge_order` ADD COLUMN `vip_plan_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '会员套餐ID(biz_type=vip)' AFTER `biz_type`;
  END IF;
END //
DELIMITER ;
CALL `upgrade_recharge_order_vip`();
DROP PROCEDURE IF EXISTS `upgrade_recharge_order_vip`;

-- 月付套餐对齐网关最小金额（可重复执行）
UPDATE `lp_vip_plan` SET `usd_price` = 10.00 WHERE `months` = 1 AND `usd_price` < 10.00;
