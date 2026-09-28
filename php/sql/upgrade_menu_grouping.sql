-- ============================================
-- 后台菜单归属整理
-- 目的：把原先全部堆在「直播运营」目录下的业务菜单，按业务域拆到多个顶级目录，
--       并启用被禁用的「会员管理 / 数据安全管理」。
--
-- 拆分后顶级目录：
--   直播运营   (live)     -> 人设 / 素材 / 房间 / 礼物 / 定时维护
--   内容运营   (content)  -> 短剧、剧集、商品、动态、私密内容、换脸模板、首页配置
--   财务与审核 (finance)  -> 充值审核、充值渠道、众筹项目、商家认证审核
--   直播数据   (liveData) -> 收益明细、收入排行榜、历史切片
--   AI 管理    (ai)       -> AI内容、AI视频素材（保持独立顶级目录）
--   会员管理   (user)     -> 会员相关 + 直播平台用户（启用）
--
-- 说明：BuildAdmin 的菜单路由基于每条规则自身的 path 计算，改 pid 只影响菜单归属/展示，
--       不会改变已注册的路由地址，可安全执行。
-- 幂等：可重复执行。
-- ============================================

-- --------------------------------------------
-- 1. 新增顶级菜单目录：内容运营 / 财务与审核 / 直播数据
-- --------------------------------------------
INSERT INTO `ba_admin_rule`
    (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
SELECT 0, 'menu_dir', '内容运营', 'content', 'content', 'fa fa-th-large', NULL, '', '', 0, 'none', 'AI女友端内容运营', 118, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ba_admin_rule` WHERE `name` = 'content' AND `type` = 'menu_dir');

INSERT INTO `ba_admin_rule`
    (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
SELECT 0, 'menu_dir', '财务与审核', 'finance', 'finance', 'fa fa-money', NULL, '', '', 0, 'none', '充值/众筹/商家认证审核', 116, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ba_admin_rule` WHERE `name` = 'finance' AND `type` = 'menu_dir');

INSERT INTO `ba_admin_rule`
    (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
SELECT 0, 'menu_dir', '直播数据', 'liveData', 'liveData', 'fa fa-bar-chart', NULL, '', '', 0, 'none', '直播收益与切片数据', 60, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ba_admin_rule` WHERE `name` = 'liveData' AND `type` = 'menu_dir');

SET @content_pid   = (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'content'  AND `type` = 'menu_dir' ORDER BY `id` LIMIT 1);
SET @finance_pid   = (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'finance'  AND `type` = 'menu_dir' ORDER BY `id` LIMIT 1);
SET @live_data_pid = (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'liveData' AND `type` = 'menu_dir' ORDER BY `id` LIMIT 1);
SET @user_pid      = (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'user'     AND `type` = 'menu_dir' ORDER BY `id` LIMIT 1);
SET @live_pid      = (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'live'     AND `type` = 'menu_dir' ORDER BY `id` LIMIT 1);
SET @ai_pid        = (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'ai'       AND `type` = 'menu_dir' ORDER BY `id` LIMIT 1);

-- --------------------------------------------
-- 2. 「内容运营」：AI女友端内容与首页配置
-- --------------------------------------------
UPDATE `ba_admin_rule`
SET `pid` = @content_pid,
    `weigh` = CASE `name`
        WHEN 'live/shortItem'        THEN 100
        WHEN 'live/shortEpisode'     THEN 99
        WHEN 'live/shopItem'         THEN 98
        WHEN 'live/postItem'         THEN 97
        WHEN 'live/privateItem'      THEN 96
        WHEN 'live/faceSwapTemplate' THEN 95
        WHEN 'live/homeBanner'       THEN 92
        WHEN 'live/homeCharacter'    THEN 91
        ELSE `weigh`
    END
WHERE `type` = 'menu'
  AND `name` IN ('live/shortItem', 'live/shortEpisode', 'live/shopItem', 'live/postItem',
                 'live/privateItem', 'live/faceSwapTemplate',
                 'live/homeBanner', 'live/homeCharacter');

-- --------------------------------------------
-- 3. 「财务与审核」：充值 / 众筹 / 商家认证
-- --------------------------------------------
UPDATE `ba_admin_rule`
SET `pid` = @finance_pid,
    `weigh` = CASE `name`
        WHEN 'live/rechargeOrder'         THEN 100
        WHEN 'live/rechargeChannel'       THEN 99
        WHEN 'live/crowdfunding'          THEN 98
        WHEN 'live/merchantCertification' THEN 97
        ELSE `weigh`
    END
WHERE `type` = 'menu'
  AND `name` IN ('live/rechargeOrder', 'live/rechargeChannel', 'live/crowdfunding', 'live/merchantCertification');

-- --------------------------------------------
-- 4. 「直播数据」：收益 / 排行榜 / 历史切片
-- --------------------------------------------
UPDATE `ba_admin_rule`
SET `pid` = @live_data_pid,
    `weigh` = CASE `name`
        WHEN 'live/revenue'     THEN 100
        WHEN 'live/leaderboard' THEN 99
        WHEN 'live/replayClip'  THEN 98
        ELSE `weigh`
    END
WHERE `type` = 'menu' AND `name` IN ('live/revenue', 'live/leaderboard', 'live/replayClip');

-- --------------------------------------------
-- 5. 「直播运营」：只保留直播制作 + 定时维护，并规范排序
-- --------------------------------------------
UPDATE `ba_admin_rule`
SET `weigh` = CASE `name`
        WHEN 'live/persona'     THEN 100
        WHEN 'live/mediaAsset'  THEN 99
        WHEN 'live/room'        THEN 98
        WHEN 'live/gift'        THEN 97
        WHEN 'live/maintenance' THEN 90
        ELSE `weigh`
    END
WHERE `name` IN ('live/persona', 'live/mediaAsset', 'live/room', 'live/gift', 'live/maintenance');

-- 确保「直播运营」目录本身启用
UPDATE `ba_admin_rule` SET `status` = 1 WHERE `id` = @live_pid;

-- --------------------------------------------
-- 6. 「会员管理」：启用，并把「直播平台用户」归到这里
-- --------------------------------------------
UPDATE `ba_admin_rule`
SET `pid` = @user_pid, `weigh` = 89
WHERE `type` = 'menu' AND `name` IN ('user/liveUser', 'live/liveUser');

UPDATE `ba_admin_rule` SET `status` = 1 WHERE `id` = @user_pid;

-- --------------------------------------------
-- 7. 顶级目录排序：内容运营 / 财务与审核 / AI管理 / 直播数据
-- --------------------------------------------
UPDATE `ba_admin_rule` SET `weigh` = 120 WHERE `id` = @live_pid;
UPDATE `ba_admin_rule` SET `weigh` = 118 WHERE `id` = @content_pid;
UPDATE `ba_admin_rule` SET `weigh` = 116 WHERE `id` = @finance_pid;
UPDATE `ba_admin_rule` SET `weigh` = 114 WHERE `id` = @ai_pid;
UPDATE `ba_admin_rule` SET `weigh` = 60  WHERE `id` = @live_data_pid;

-- --------------------------------------------
-- 8. 「数据安全管理」：启用（模块市场 / CRUD代码生成 属开发工具，保持禁用）
-- --------------------------------------------
UPDATE `ba_admin_rule` SET `status` = 1 WHERE `type` = 'menu_dir' AND `name` = 'security';

-- --------------------------------------------
-- 9. 校验：打印整理后的菜单树
-- --------------------------------------------
SELECT `p`.`id` AS `pid`, `p`.`title` AS `parent`, `c`.`id` AS `cid`, `c`.`title`, `c`.`name`, `c`.`weigh`, `c`.`status`
FROM `ba_admin_rule` AS `c`
LEFT JOIN `ba_admin_rule` AS `p` ON `p`.`id` = `c`.`pid`
WHERE `c`.`type` IN ('menu_dir', 'menu') AND `c`.`pid` = 0
ORDER BY `c`.`weigh` DESC;

SELECT `p`.`title` AS `parent`, `c`.`title`, `c`.`name`, `c`.`weigh`, `c`.`status`
FROM `ba_admin_rule` AS `c`
LEFT JOIN `ba_admin_rule` AS `p` ON `p`.`id` = `c`.`pid`
WHERE `c`.`type` = 'menu'
  AND `p`.`name` IN ('live', 'content', 'finance', 'liveData', 'user', 'ai')
ORDER BY `p`.`weigh` DESC, `c`.`weigh` DESC;
