-- ============================================
-- Sugus：MKPay 通道下线，切换为 Skytrade 固定商品链接收款
--
-- 执行范围：仅 sugus（上游生产）执行；其它站点不要执行，避免误关自己的 MKPay。
-- 说明：
--   1) 关闭所有 MKPay 法币通道（巴西 / 越南 / 墨西哥 / 印度），前台不再展示
--   2) 新增一条 Skytrade 通道：USD 计价、汇率 1（商品链接本身按 USD 标价）
--   3) 订单金额 → 商品链接的映射在 config/skytrade.php，不在数据库里
-- 本脚本幂等，可重复执行。
-- ============================================

-- 1) 下线 MKPay 全部通道
UPDATE `lp_fiat_channel`
SET `status` = 0
WHERE `gateway` IN ('mkpay', 'mkpay_vn', 'mkpay_mx', 'mkpay_in');

-- 2) 已有 Skytrade 通道时校正币种/汇率（名称、排序、说明保留后台配置）
UPDATE `lp_fiat_channel`
SET `currency` = 'USD',
    `currency_symbol` = '$',
    `usd_rate` = 1,
    `decimals` = 2
WHERE `gateway` = 'skytrade';

-- 3) 没有 Skytrade 通道时新增一条
INSERT INTO `lp_fiat_channel`
  (`name`, `gateway`, `gateway_ref`, `currency`, `currency_symbol`, `usd_rate`, `auto_rate`,
   `decimals`, `min_amount`, `diamond_rate`, `pay_instructions`, `sort`, `status`)
SELECT 'stripe', 'skytrade', '', 'USD', '$', 1.00000000, 0,
       2, 0, 100, 'You will be redirected to the payment page. Your order will be credited automatically after payment succeeds.', 1, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `lp_fiat_channel` WHERE `gateway` = 'skytrade');

-- 4) 校验
SELECT `id`, `name`, `gateway`, `currency`, `usd_rate`, `decimals`, `status`, `sort`
FROM `lp_fiat_channel`
ORDER BY `sort`, `id`;
