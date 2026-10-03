-- 提现链上自动打款升级
-- 2026-09-30
--
-- 在 upgrade_withdraw.sql 基础上，为 lp_withdrawal 增加「打款」子状态：
--   pay_state 0待打款 1已广播待确认 2已确认到账 3打款失败 4打款处理中(并发占位)
-- status 语义保持不变：0待审核/处理中 1打款完成 2已拒绝(退代币)

ALTER TABLE lp_withdrawal
    ADD COLUMN pay_state TINYINT NOT NULL DEFAULT 0 COMMENT '打款状态 0待打款 1已广播 2已确认 3失败 4处理中' AFTER status,
    ADD COLUMN pay_error VARCHAR(255) NOT NULL DEFAULT '' COMMENT '打款失败原因' AFTER tx_hash,
    ADD COLUMN pay_attempts INT NOT NULL DEFAULT 0 COMMENT '已尝试打款次数' AFTER pay_error,
    ADD COLUMN broadcast_at DATETIME NULL COMMENT '链上广播时间' AFTER paid_at,
    ADD COLUMN confirmed_at DATETIME NULL COMMENT '链上确认时间' AFTER broadcast_at,
    ADD COLUMN raw_tx MEDIUMTEXT NULL COMMENT '已签名交易原文(重广播/审计用)' AFTER confirmed_at,
    ADD INDEX idx_pay (status, pay_state);

-- 平台配置表（upgrade_usdt_auto.sql 已建过；这里兜底，保证本文件可单独执行）
CREATE TABLE IF NOT EXISTS lp_platform_config (
    `key` VARCHAR(64) NOT NULL,
    `value` VARCHAR(255) NOT NULL DEFAULT '',
    remark VARCHAR(255) NOT NULL DEFAULT '',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='平台资金配置';

-- 打款参数（可在后台/DB 直接调整，无需改代码）
INSERT IGNORE INTO lp_platform_config (`key`, `value`, remark) VALUES
('payout_trc20_fee_limit', '30', '提现打款：TRC20 单笔 fee_limit（单位 TRX）'),
('payout_max_attempts', '3', '提现打款：单笔最多自动打款次数'),
('payout_erc20_rpc', 'https://ethereum-rpc.publicnode.com,https://1rpc.io/eth', '提现打款：ERC20 RPC 节点（逗号分隔可配多个降级）'),
('payout_bep20_rpc', 'https://bsc-dataseed.bnbchain.org,https://1rpc.io/bnb', '提现打款：BEP20 RPC 节点（逗号分隔可配多个降级）');
-- 注意：payout_mode 故意不预置，留空时回落 .env [PAYOUT] MODE；
--       运营在后台改过之后才会以库里的值为准。

-- 后台菜单：提现审核 + 提现打款配置
-- 挂在「财务与审核」目录下（与充值审核、充值渠道同级）——运营实际看的是这个目录，
-- 不要把提现相关菜单挂到「直播运营」下，那里翻不到。
SET @fin_pid = IFNULL((SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'finance'   AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1),
              IFNULL((SELECT `id` FROM `ba_admin_rule` WHERE `title` = '财务与审核' AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1),
              IFNULL((SELECT `id` FROM `ba_admin_rule` WHERE `title` = '直播运营'  AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1), 0)));

-- 「提现审核」菜单此前一直缺失（提现审核页只能靠手敲 URL 访问），这里补上
-- name 上没有唯一索引，所以用 INSERT ... WHERE NOT EXISTS 保证可重复执行不产生重复菜单
INSERT INTO `ba_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
SELECT @fin_pid, 'menu', '提现审核', 'live/withdrawal', 'live/withdrawal', 'fa fa-money', 'iframe', '/admin/live.Withdrawal/index', '', 0, 'none', '提现审核与链上打款', 96, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `ba_admin_rule` WHERE `name` = 'live/withdrawal');

INSERT INTO `ba_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
SELECT @fin_pid, 'menu', '提现打款配置', 'live/payoutSetting', 'live/payoutSetting', 'fa fa-credit-card', 'iframe', '/admin/live.PayoutSetting/index', '', 0, 'none', '提现打款与热钱包配置', 95, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `ba_admin_rule` WHERE `name` = 'live/payoutSetting');

-- 兜底修正：已被插到别的目录（如「直播运营」）下的提现菜单，统一挪回「财务与审核」
UPDATE `ba_admin_rule` SET `pid` = @fin_pid
WHERE `name` IN ('live/payoutSetting', 'live/withdrawal') AND `pid` <> @fin_pid;

-- 兜底修正：早期版本把排序号写成了 11，会沉到「财务与审核」菜单最底部
UPDATE `ba_admin_rule` SET `weigh` = 95
WHERE `name` = 'live/payoutSetting' AND `weigh` < 90;
