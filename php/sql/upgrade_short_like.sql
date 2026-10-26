-- 短剧点赞（Discover 页右侧点赞按钮，真实落库）
-- 幂等：重复执行不会报错
CREATE TABLE IF NOT EXISTS `lp_short_like` (
    `id`         bigint unsigned NOT NULL AUTO_INCREMENT,
    `short_id`   int unsigned NOT NULL DEFAULT 0 COMMENT '短剧 id（lp_short_item.id）',
    `user_id`    int unsigned NOT NULL DEFAULT 0 COMMENT '用户 id（lp_user.id）',
    `created_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_short_user` (`short_id`, `user_id`),
    KEY `idx_short` (`short_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COMMENT = '短剧点赞';
