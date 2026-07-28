-- 商家认证完善：新增真实姓名、身份证号、手机号、店铺名称、经营类目、营业执照、店铺简介
ALTER TABLE `lp_merchant_certification`
  ADD COLUMN `real_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '真实姓名' AFTER `email`,
  ADD COLUMN `id_card_no` VARCHAR(18) NOT NULL DEFAULT '' COMMENT '身份证号' AFTER `real_name`,
  ADD COLUMN `phone` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '手机号' AFTER `id_card_no`,
  ADD COLUMN `shop_name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '店铺名称' AFTER `phone`,
  ADD COLUMN `shop_type` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '经营类目' AFTER `shop_name`,
  ADD COLUMN `shop_description` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '店铺简介' AFTER `shop_type`,
  ADD COLUMN `business_license` VARCHAR(512) NOT NULL DEFAULT '' COMMENT '营业执照' AFTER `id_card_back`;
