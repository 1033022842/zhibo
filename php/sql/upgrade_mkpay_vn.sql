-- ============================================================
-- MKPay（越南 VND）法币通道种子
-- 2026-10-19
--
-- 网关：https://mch.mkpay8888.com（越南 MKPay）
-- 产品：VN01 越南代收
-- 金额：VND 无小数，接口传整数 VND
-- 本脚本幂等，可重复执行。
-- ============================================================

INSERT INTO `lp_fiat_channel`
    (`name`, `gateway`, `gateway_ref`, `currency`, `currency_symbol`, `usd_rate`, `auto_rate`,
     `decimals`, `min_amount`, `diamond_rate`, `pay_instructions`, `sort`, `status`)
SELECT
    'Vietnam · Bank Transfer (MKPay)', 'mkpay_vn', 'VN01', 'VND', '₫', 26300, 1,
    0, 10000, 100, '', 20, 1
WHERE NOT EXISTS (
    SELECT 1 FROM `lp_fiat_channel` WHERE `gateway` = 'mkpay_vn'
);
