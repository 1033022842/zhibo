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
    '巴西 · Pix (MKPay)', 'mkpay', '', 'BRL', 'R$', 5.06,
    2, 5.00, 100, '', 30, 1
WHERE NOT EXISTS (
    SELECT 1 FROM `lp_fiat_channel` WHERE `gateway` = 'mkpay'
);

-- --------------------------------------------
-- 2) 汇率自动更新开关（fiat:rate 定时任务用，每小时拉中间价刷新 usd_rate）
--    MySQL 8 无 ADD COLUMN IF NOT EXISTS，用 information_schema 判断保证幂等
-- --------------------------------------------
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'lp_fiat_channel'
      AND COLUMN_NAME = 'auto_rate'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `lp_fiat_channel` ADD COLUMN `auto_rate` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''汇率自动更新:1=每小时按中间价刷新 0=手动'' AFTER `usd_rate`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- MKPay 巴西通道开启自动汇率（首次把占位值修正为当时中间价，后续由任务维护）
UPDATE `lp_fiat_channel` SET `auto_rate` = 1, `usd_rate` = 5.06 WHERE `gateway` = 'mkpay' AND `auto_rate` = 0;
