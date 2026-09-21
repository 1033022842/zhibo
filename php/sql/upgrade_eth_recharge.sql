-- ETH（以太坊主链）充值渠道
-- 2026-09-21

-- 1. ETH 兑 USD 价格（链上应付 ETH = USD ÷ 该价格）
INSERT IGNORE INTO `lp_platform_config` (`key`, `value`, `remark`) VALUES
('eth_usd_rate', '3000', 'ETH 兑 USD 价格（ETH 充值换算用）');

-- 2. 预建 ETH 渠道（默认禁用：填好收款地址 + Etherscan API Key 后再启用）
--    类型 eth：自动确认走 recharge:scan-eth（Etherscan API v2，chainid=1）
INSERT INTO `lp_recharge_channel` (`name`, `type`, `qr_code_url`, `address`, `diamond_rate`, `min_amount`, `sort`, `status`)
SELECT 'ETH（以太坊主链）', 'eth', '', '', 100.00, 10.00, 20, 0
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id` FROM `lp_recharge_channel` WHERE `type` = 'eth') AS `x`);

-- 3. 渠道类型说明补充（原注释只有 usdt_trc20 / other）
ALTER TABLE `lp_recharge_channel`
    MODIFY COLUMN `type` varchar(32) NOT NULL DEFAULT 'usdt_trc20' COMMENT '渠道类型:usdt_trc20(TRON链自动扫) eth(以太坊主链自动扫) other(人工)';
