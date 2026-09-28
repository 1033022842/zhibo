-- ============================================
-- 后台菜单整体排序优化
-- 目标：把「会员管理」提到业务菜单最上面，并按使用频率重排顶级目录。
-- 说明：仅调整 weigh（越大越靠前），不改归属、不改路由。幂等，可重复执行。
-- ============================================

UPDATE `ba_admin_rule` SET `weigh` = 200 WHERE `type` = 'menu_dir' AND `name` = 'user';      -- 会员管理（会员列表）
UPDATE `ba_admin_rule` SET `weigh` = 190 WHERE `type` = 'menu_dir' AND `name` = 'live';      -- 直播运营
UPDATE `ba_admin_rule` SET `weigh` = 180 WHERE `type` = 'menu_dir' AND `name` = 'content';   -- 内容运营
UPDATE `ba_admin_rule` SET `weigh` = 170 WHERE `type` = 'menu_dir' AND `name` = 'finance';   -- 财务与审核
UPDATE `ba_admin_rule` SET `weigh` = 160 WHERE `type` = 'menu_dir' AND `name` = 'ai';        -- AI 管理
UPDATE `ba_admin_rule` SET `weigh` = 150 WHERE `type` = 'menu_dir' AND `name` = 'liveData';  -- 直播数据
UPDATE `ba_admin_rule` SET `weigh` = 100 WHERE `type` = 'menu_dir' AND `name` = 'auth';      -- 权限管理
UPDATE `ba_admin_rule` SET `weigh` = 89  WHERE `type` = 'menu_dir' AND `name` = 'routine';   -- 常规管理
UPDATE `ba_admin_rule` SET `weigh` = 85  WHERE `type` = 'menu_dir' AND `name` = 'security';  -- 数据安全管理

-- 控制台保持最高权重，始终在最顶部
UPDATE `ba_admin_rule` SET `weigh` = 999 WHERE `name` = 'dashboard';

-- 校验：打印顶级菜单顺序
SELECT `id`, `type`, `title`, `name`, `weigh`, `status`
FROM `ba_admin_rule`
WHERE `pid` = 0 AND `type` IN ('menu', 'menu_dir')
ORDER BY `weigh` DESC;
