-- ============================================================
-- MKPay（墨西哥 MXN）法币通道种子
-- 2026-10-20
--
-- 网关：https://pay.mkpay8888.com
-- 产品：商户后台已绑定产品，下单不传 product_code（网关自动路由）
-- 金额：MXN 分（factor=100），接口传整数分（20.00 MXN → 2000）
-- 本脚本幂等，可重复执行。
-- ============================================================

INSERT INTO `lp_fiat_channel`
    (`name`, `gateway`, `gateway_ref`, `currency`, `currency_symbol`, `usd_rate`, `auto_rate`,
     `decimals`, `min_amount`, `diamond_rate`, `pay_instructions`, `sort`, `status`)
SELECT
    'Mexico · Bank Transfer (MKPay)', 'mkpay_mx', '', 'MXN', 'Mex$', 18.5, 1,
    2, 20, 100, '', 30, 1
WHERE NOT EXISTS (
    SELECT 1 FROM `lp_fiat_channel` WHERE `gateway` = 'mkpay_mx'
);
