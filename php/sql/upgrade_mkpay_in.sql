-- ============================================================
-- MKPay（印度 INR）法币通道种子
-- 2026-10-20
--
-- 网关：https://pay.mkpay8888.com
-- 产品：商户后台已绑定产品，下单不传 product_code（网关自动路由）
-- 金额：INR 分（factor=100），接口传整数分（100.00 INR → 10000）
-- 汇率：初始 88，auto_rate=1（fiat:rate 每小时按中间价刷新，安全区间 50~130）
-- 本脚本幂等，可重复执行。
-- ============================================================

INSERT INTO `lp_fiat_channel`
    (`name`, `gateway`, `gateway_ref`, `currency`, `currency_symbol`, `usd_rate`, `auto_rate`,
     `decimals`, `min_amount`, `diamond_rate`, `pay_instructions`, `sort`, `status`)
SELECT
    'India · UPI (MKPay)', 'mkpay_in', '', 'INR', '₹', 88.0, 1,
    2, 100, 100, '', 40, 1
WHERE NOT EXISTS (
    SELECT 1 FROM `lp_fiat_channel` WHERE `gateway` = 'mkpay_in'
);
