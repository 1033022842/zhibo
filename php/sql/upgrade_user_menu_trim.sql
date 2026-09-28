-- ============================================
-- 会员管理目录瘦身
-- 1) 只保留「直播平台用户」，并把标题改为「会员列表」
-- 2) 隐藏 BuildAdmin 自带的空壳会员模块（会员管理 / 分组 / 规则 / 余额 / 积分）
-- 说明：采用禁用（status=0）而非删除，随时可恢复。幂等，可重复执行。
-- ============================================

-- 1. 重命名「直播平台用户」-> 「会员列表」
UPDATE `ba_admin_rule`
SET `title` = '会员列表'
WHERE `type` = 'menu' AND `name` IN ('user/liveUser', 'live/liveUser');

-- 2. 隐藏框架自带的会员模块（数据表 ba_user / ba_user_*，本项目未使用）
UPDATE `ba_admin_rule`
SET `status` = 0
WHERE `type` = 'menu'
  AND `name` IN ('user/user', 'user/group', 'user/rule', 'user/moneyLog', 'user/scoreLog');

-- 3. 校验
SELECT `p`.`title` AS `parent`, `c`.`id`, `c`.`title`, `c`.`name`, `c`.`weigh`, `c`.`status`
FROM `ba_admin_rule` AS `c`
LEFT JOIN `ba_admin_rule` AS `p` ON `p`.`id` = `c`.`pid`
WHERE `p`.`name` = 'user'
ORDER BY `c`.`weigh` DESC;
