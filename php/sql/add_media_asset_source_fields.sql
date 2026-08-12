-- ============================================================================
-- 迁移：lp_media_asset 增加来源追踪字段
-- 目的：区分"后台手动上传"和"AI 电脑自动上报"的视频素材，并记录来源机器
-- 执行：在服务器 MySQL 上跑一次
--   mysql -u zhibo -p zhibo < php/sql/add_media_asset_source_fields.sql
-- ============================================================================

ALTER TABLE `lp_media_asset`
    ADD COLUMN `source`      VARCHAR(32)  NOT NULL DEFAULT 'admin'
        COMMENT '来源: admin=后台上传, machine=AI电脑上报' AFTER `status`,
    ADD COLUMN `machine_id`  VARCHAR(64)  NOT NULL DEFAULT ''
        COMMENT 'AI电脑标识（source=machine 时记录，如 room5-pc）' AFTER `source`,
    ADD COLUMN `remote_path` VARCHAR(255) NOT NULL DEFAULT ''
        COMMENT 'AI电脑本地原始路径（source=machine 时记录）' AFTER `machine_id`;

-- 索引：按机器筛选上报素材
ALTER TABLE `lp_media_asset`
    ADD INDEX `idx_machine` (`machine_id`, `source`);

-- 已有数据全部标记为后台上传
UPDATE `lp_media_asset` SET `source` = 'admin' WHERE `source` = '' OR `source` IS NULL;
