-- ============================================
-- 会员列表：后台手动「添加会员」按钮权限
--
-- 1) 新增按钮权限节点 user/liveUser/add（控制器 user.LiveUser::add）
-- 2) 已拥有「会员列表」菜单的分组自动追加该节点
--
-- 超级管理员（分组 id=1，rules=*）无需执行本脚本也可见按钮；
-- 其他后台分组需要执行本脚本后才能看到「添加会员」。
-- 本脚本幂等，可重复执行。
-- 2026-11-07
-- ============================================

SET @live_user_pid = (SELECT `id` FROM `ba_admin_rule`
                      WHERE `name` = 'user/liveUser' AND `type` = 'menu' ORDER BY `id` DESC LIMIT 1);

INSERT INTO `ba_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`,
                             `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
SELECT @live_user_pid, 'button', '添加会员', 'user/liveUser/add', '', '', NULL, '',
       '', 0, 'none', '手动创建会员账号并生成专属邀请链接', 3, 1
FROM DUAL
WHERE @live_user_pid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `ba_admin_rule` WHERE `name` = 'user/liveUser/add');

-- 已授予「会员列表」菜单的分组追加「添加会员」节点
UPDATE `ba_admin_group`
SET `rules` = CONCAT(RTRIM(`rules`), ',',
                     (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'user/liveUser/add' LIMIT 1))
WHERE `id` <> 1
  AND FIND_IN_SET((SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'user/liveUser' AND `type` = 'menu' LIMIT 1), `rules`)
  AND NOT FIND_IN_SET((SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'user/liveUser/add' LIMIT 1), `rules`);

-- 校验
SELECT `id`, `pid`, `title`, `name`, `status` FROM `ba_admin_rule` WHERE `name` = 'user/liveUser/add';
