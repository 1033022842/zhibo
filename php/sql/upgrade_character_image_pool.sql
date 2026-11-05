-- ============================================
-- AI 工作室形象图预生成池（createCharacter 秒开优化）
-- 执行：source php/sql/upgrade_character_image_pool.sql   （库 zhibo / live_mysql）
--
-- 设计：
--   1) prompt 由 6 个词条维度 + 年龄段纯函数决定（CharacterImageService::buildPrompt），
--      fields_hash = md5(category|race|hairstyle|hair|eye|body|breast|age_bin)，天然缓存 key。
--   2) 每组合存 POOL_DEPTH=3 张（seq 1..3），用户建角色时原子领取 1 张（ready→served），
--      库存不足由 ai:pool:cron 按真实选择需求补池（priority=0 池任务永不抢占用户单）。
--   3) task_id 关联 lp_ai_task（task_type=pool_image）；completeTask 回写 image_url，
--      卡死回收（reapStuckCustomTasks）置 failed 后由 ai:pool:cron 删行释放 seq 重发。
-- ============================================

CREATE TABLE IF NOT EXISTS `lp_character_image_pool` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `category`    VARCHAR(16)  NOT NULL DEFAULT '' COMMENT 'realistic|anime',
  `fields_hash` CHAR(32)     NOT NULL DEFAULT '' COMMENT '词条组合 md5',
  `fields_json` TEXT         NULL COMMENT '出图用词条（age=分箱代表年龄）',
  `age_bin`     VARCHAR(8)   NOT NULL DEFAULT '' COMMENT '年龄段 18-19/20-24/25-29/30-39/40-55',
  `seq`         TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '同组合第几张(1..3)',
  `status`      VARCHAR(16)  NOT NULL DEFAULT 'pending' COMMENT 'pending|generating|ready|served|failed',
  `persona_id`  BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '恒为0（池行不占用户空间）',
  `task_id`     BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关联 lp_ai_task.id',
  `image_url`   VARCHAR(500) NOT NULL DEFAULT '' COMMENT '成品图',
  `served_at`   DATETIME DEFAULT NULL COMMENT '被用户领取时间',
  `created_at`  DATETIME DEFAULT NULL COMMENT '创建时间',
  `updated_at`  DATETIME DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_hash_seq` (`fields_hash`, `seq`),
  KEY `idx_hash_status` (`fields_hash`, `status`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI工作室形象图预生成池';
