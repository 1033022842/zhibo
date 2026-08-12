-- ============================================
-- 4个新菜单：充值审核、充值渠道、众筹项目、商家认证
-- 在服务器数据库执行此文件即可
-- ============================================

-- 找"直播运营"菜单目录的ID，按标题查找（兼容 name 为 live 或 liveOps 的情况）
SET @live_pid = IFNULL((SELECT `id` FROM `ba_admin_rule` WHERE `title` = '直播运营' AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1), 0);

-- 1. 充值审核 (RechargeOrder)
INSERT INTO `ba_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
VALUES (@live_pid, 'menu', '充值审核', 'live/rechargeOrder', 'live/rechargeOrder', 'fa fa-money', 'iframe', '/admin/live.RechargeOrder/index', '', 0, 'none', '充值订单审核', 10, 1)
ON DUPLICATE KEY UPDATE `pid`=VALUES(`pid`), `title`='充值审核', `menu_type`='iframe', `url`='/admin/live.RechargeOrder/index', `status`=1;

-- 2. 充值渠道 (RechargeChannel)
INSERT INTO `ba_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
VALUES (@live_pid, 'menu', '充值渠道', 'live/rechargeChannel', 'live/rechargeChannel', 'fa fa-credit-card', 'iframe', '/admin/live.RechargeChannel/index', '', 0, 'none', '充值渠道配置', 9, 1)
ON DUPLICATE KEY UPDATE `pid`=VALUES(`pid`), `title`='充值渠道', `menu_type`='iframe', `url`='/admin/live.RechargeChannel/index', `status`=1;

-- 3. 众筹项目 (Crowdfunding)
INSERT INTO `ba_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
VALUES (@live_pid, 'menu', '众筹项目', 'live/crowdfunding', 'live/crowdfunding', 'fa fa-rocket', 'iframe', '/admin/live.Crowdfunding/index', '', 0, 'none', '众筹项目管理', 8, 1)
ON DUPLICATE KEY UPDATE `pid`=VALUES(`pid`), `title`='众筹项目', `menu_type`='iframe', `url`='/admin/live.Crowdfunding/index', `status`=1;

-- 4. 商家认证审核 (MerchantCertification)
INSERT INTO `ba_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
VALUES (@live_pid, 'menu', '商家认证审核', 'live/merchantCertification', 'live/merchantCertification', 'fa fa-certificate', 'iframe', '/admin/live.MerchantCertification/index', '', 0, 'none', '商家认证审核', 7, 1)
ON DUPLICATE KEY UPDATE `pid`=VALUES(`pid`), `title`='商家认证审核', `menu_type`='iframe', `url`='/admin/live.MerchantCertification/index', `status`=1;

-- 确认插入结果
SELECT `id`, `title`, `name`, `menu_type`, `url`, `status` FROM `ba_admin_rule` WHERE `name` IN ('live/rechargeOrder', 'live/rechargeChannel', 'live/crowdfunding', 'live/merchantCertification');
