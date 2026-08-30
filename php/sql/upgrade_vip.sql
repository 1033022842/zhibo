-- 会员订阅模块
-- 2026-08-23

CREATE TABLE IF NOT EXISTS lp_vip_plan (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(64) NOT NULL COMMENT '套餐名',
    months INT NOT NULL DEFAULT 1 COMMENT '月数',
    usd_price DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '参考美元价(展示)',
    diamond_price DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT '钻石支付价',
    daily_diamond DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT '会员每日领取钻石',
    sort INT NOT NULL DEFAULT 0,
    status TINYINT NOT NULL DEFAULT 1 COMMENT '0禁用 1启用',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='VIP套餐';

INSERT IGNORE INTO lp_vip_plan (id, name, months, usd_price, diamond_price, daily_diamond, sort, status) VALUES
(1, '月度会员', 1, 9.99, 1000, 20, 1, 1),
(2, '季度会员', 3, 24.99, 2500, 25, 2, 1),
(3, '年度会员', 12, 79.99, 8000, 30, 3, 1);

CREATE TABLE IF NOT EXISTS lp_vip_order (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_no VARCHAR(64) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    diamond_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    expire_from DATETIME NULL COMMENT '起算时间',
    expire_to DATETIME NULL COMMENT '到期时间',
    status TINYINT NOT NULL DEFAULT 1 COMMENT '1已生效 0失败',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_order_no (order_no),
    KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='VIP购买订单';

ALTER TABLE lp_user
    ADD COLUMN vip_expire_at DATETIME NULL DEFAULT NULL COMMENT '会员到期时间',
    ADD COLUMN vip_last_claim_date DATE NULL DEFAULT NULL COMMENT '每日领取日期';
