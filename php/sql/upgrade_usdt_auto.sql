-- USDT 自动确认充值升级
-- 2026-08-23

-- 1. 渠道增加确认模式与 TronGrid API Key
ALTER TABLE lp_recharge_channel
    ADD COLUMN confirm_mode VARCHAR(16) NOT NULL DEFAULT 'auto' COMMENT '确认模式 auto=链上自动 manual=人工审核' AFTER diamond_rate,
    ADD COLUMN api_key VARCHAR(128) NOT NULL DEFAULT '' COMMENT 'TronGrid API Key(可选)' AFTER confirm_mode;

-- 2. 支付回调日志表（schema 设计过但从未建/未用，正式启用）
CREATE TABLE IF NOT EXISTS lp_payment_callback_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_no VARCHAR(64) NOT NULL DEFAULT '' COMMENT '关联订单号(未匹配时为空)',
    gateway VARCHAR(32) NOT NULL DEFAULT 'usdt_trc20',
    payload_hash VARCHAR(64) NOT NULL DEFAULT '' COMMENT '交易ID/去重键',
    raw_payload TEXT NULL,
    verify_status TINYINT NOT NULL DEFAULT 0 COMMENT '0待人工 1已确认入账 2已忽略',
    amount DECIMAL(18,6) NOT NULL DEFAULT 0,
    from_address VARCHAR(64) NOT NULL DEFAULT '',
    to_address VARCHAR(64) NOT NULL DEFAULT '',
    block_timestamp BIGINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_payload_hash (gateway, payload_hash),
    KEY idx_order_no (order_no),
    KEY idx_verify_status (verify_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='支付链上回调日志';

-- 3. 旧待审核订单补过期时间（2小时前创建的视为已过期）
UPDATE lp_recharge_order
    SET expire_at = DATE_ADD(created_at, INTERVAL 2 HOUR)
    WHERE expire_at IS NULL OR expire_at = '1970-01-01 00:00:00';

-- 4. 平台配置表（P2 使用，先建）
CREATE TABLE IF NOT EXISTS lp_platform_config (
    `key` VARCHAR(64) NOT NULL,
    `value` VARCHAR(255) NOT NULL DEFAULT '',
    remark VARCHAR(255) NOT NULL DEFAULT '',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='平台资金配置';

INSERT IGNORE INTO lp_platform_config (`key`, `value`, remark) VALUES
('gift_commission_rate', '0.30', '礼物收入平台抽成比例'),
('crowdfunding_commission_rate', '0.05', '众筹达标结算平台抽成比例'),
('withdraw_fee_rate', '0.01', '提现手续费比例'),
('diamond_to_usdt_rate', '0.01', '钻石兑USDT比例(1钻=0.01USDT)'),
('min_withdraw_diamond', '1000', '最低提现钻石数');
