-- ============================================================
-- 会员列表：伪删除（软删除）+ 删除按钮权限节点
-- 2026-10-17
--
-- 1) lp_user 增加 is_deleted / deleted_at：后台删除只打标记，
--    数据保留在库里，需要恢复时执行：
--      UPDATE lp_user SET is_deleted = 0, deleted_at = NULL, status = 1 WHERE id = 用户ID;
--    同时 status 置 0，被删除的账号无法再登录。
--
-- 2) 前端删除按钮按权限节点显隐（baTable.auth('del')），
--    「会员列表」此前没有任何 button 子节点，所以按钮一直不显示。
--
-- 幂等：可重复执行。
-- ============================================================

-- ---- 1. lp_user 增加软删除字段 ----
DROP PROCEDURE IF EXISTS `_add_user_soft_delete_cols`;
DELIMITER $$
CREATE PROCEDURE `_add_user_soft_delete_cols`()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lp_user' AND COLUMN_NAME = 'is_deleted') THEN
        ALTER TABLE `lp_user`
            ADD COLUMN `is_deleted` TINYINT NOT NULL DEFAULT 0 COMMENT '后台伪删除标记:1已删除 0正常' AFTER `status`,
            ADD INDEX `idx_is_deleted` (`is_deleted`);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lp_user' AND COLUMN_NAME = 'deleted_at') THEN
        ALTER TABLE `lp_user`
            ADD COLUMN `deleted_at` DATETIME NULL COMMENT '后台删除时间' AFTER `is_deleted`;
    END IF;
END$$
DELIMITER ;
CALL `_add_user_soft_delete_cols`();
DROP PROCEDURE IF EXISTS `_add_user_soft_delete_cols`;

-- ---- 2. 会员列表的按钮权限节点（删除按钮显隐依赖它）----
SET @live_user_pid = (SELECT `id` FROM `ba_admin_rule`
                      WHERE `name` = 'user/liveUser' AND `type` = 'menu' ORDER BY `id` DESC LIMIT 1);

INSERT INTO `ba_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`,
                             `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
SELECT @live_user_pid, 'button', '查看', 'user/liveUser/index', '', '', NULL, '',
       '', 0, 'none', '', 1, 1
FROM DUAL
WHERE @live_user_pid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `ba_admin_rule` WHERE `name` = 'user/liveUser/index');

INSERT INTO `ba_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`,
                             `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
SELECT @live_user_pid, 'button', '删除', 'user/liveUser/del', '', '', NULL, '',
       '', 0, 'none', '', 2, 1
FROM DUAL
WHERE @live_user_pid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `ba_admin_rule` WHERE `name` = 'user/liveUser/del');

-- 超级管理员组（*）天然拥有全部节点；其他分组如果也授予了「会员列表」，
-- 需要把上面两个节点 id 追加进分组 rules 才可见删除按钮。
UPDATE `ba_admin_group`
SET `rules` = CONCAT(RTRIM(`rules`), ',',
                     (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'user/liveUser/index' LIMIT 1), ',',
                     (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'user/liveUser/del' LIMIT 1))
WHERE `id` <> 1
  AND FIND_IN_SET((SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'user/liveUser' AND `type` = 'menu' LIMIT 1), `rules`)
  AND NOT FIND_IN_SET((SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'user/liveUser/del' LIMIT 1), `rules`);
