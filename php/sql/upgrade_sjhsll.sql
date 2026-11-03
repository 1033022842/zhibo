-- ============================================
-- 铭森支付（sjhsll）法币通道种子：微信支付 / 支付宝
--
-- 前置：
--   1) 服务器 .env 配好 [SJHSLL] 段（MEMBERID / KEY / NOTIFY_URL ...）
--   2) php/app/live/service/SjhsllPayService.php 已部署
--   3) php/config/sjhsll.php 已部署
--
-- 本脚本幂等，可重复执行；usd_rate 为占位值，上线前按实际汇率改。
-- 通道编码（gateway_ref）：1=微信支付，2=支付宝
-- ============================================

INSERT INTO `lp_fiat_channel`
(`name`, `gateway`, `gateway_ref`, `currency`, `currency_symbol`, `usd_rate`, `auto_rate`, `decimals`, `min_amount`, `diamond_rate`, `pay_instructions`, `sort`, `status`)
SELECT '微信支付 · CNY', 'sjhsll', '1', 'CNY', '¥', 7.100000, 0, 2, 0.01, 71, '', 10, 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `lp_fiat_channel` WHERE `gateway` = 'sjhsll' AND `gateway_ref` = '1'
);

INSERT INTO `lp_fiat_channel`
(`name`, `gateway`, `gateway_ref`, `currency`, `currency_symbol`, `usd_rate`, `auto_rate`, `decimals`, `min_amount`, `diamond_rate`, `pay_instructions`, `sort`, `status`)
SELECT '支付宝 · CNY', 'sjhsll', '2', 'CNY', '¥', 7.100000, 0, 2, 0.01, 71, '', 11, 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `lp_fiat_channel` WHERE `gateway` = 'sjhsll' AND `gateway_ref` = '2'
);
