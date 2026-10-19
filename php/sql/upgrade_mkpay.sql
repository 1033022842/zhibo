-- ============================================
-- MKPay（巴西）代收通道种子
--
-- 网关：https://br.mkpayeasy.com（appId 见 .env [MKPAY]）
-- 走 lp_fiat_channel 法币通道架构：后台配 1 USD 兑 BRL 汇率，
-- 用户下单时按汇率折算成 BRL 并锁定写进订单；MKPay 接口金额单位为「分」。
--
-- ⚠️ usd_rate=5.40 仅为占位值，上线前务必在后台改成实时汇率并确认最低金额。
-- 本脚本幂等，可重复执行。
-- ============================================

INSERT INTO `lp_fiat_channel`
    (`name`, `gateway`, `gateway_ref`, `currency`, `currency_symbol`, `usd_rate`,
     `decimals`, `min_amount`, `diamond_rate`, `pay_instructions`, `sort`, `status`)
SELECT
    '巴西 · Pix (MKPay)', 'mkpay', '', 'BRL', 'R$', 5.40,
    2, 5.00, 100, '', 30, 1
WHERE NOT EXISTS (
    SELECT 1 FROM `lp_fiat_channel` WHERE `gateway` = 'mkpay'
);
