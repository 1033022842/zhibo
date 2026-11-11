-- ============================================
-- 行为分析：埋点事件表 + 用户登录日志 + 后台一级菜单「行为分析」
-- 1) 新建 lp_track_event：行为埋点主表
--    页面浏览(pv) / 页面停留(leave 回写 duration 到对应 pv 行) / 功能事件(chat_send/register…)
-- 2) 新建 lp_user_login_log：终端用户登录日志（成功+失败），支撑「多次登录」分析
-- 3) 后台菜单：新增一级菜单「行为分析」（单页，内部 Tab），并挂各报表 action 的按钮规则
--    页面：/admin#/track/analysis（Vue 页，echarts + 表格）
--
-- 执行：source php/sql/upgrade_track_report.sql   （各站库分别执行，幂等可重复）
-- ============================================

CREATE TABLE IF NOT EXISTS `lp_track_event` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `vid`        VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '访客ID（浏览器维度，与 lp_promo_visit.vid 同源）',
  `sid`        VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '会话ID（标签页维度，与 lp_promo_visit.sid 同源）',
  `user_id`    INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户ID（登录后前端从 live_user_info 读取，0=未登录）',
  `domain`     VARCHAR(120) NOT NULL DEFAULT '' COMMENT '站点域名',
  `page`       VARCHAR(255) NOT NULL DEFAULT '' COMMENT '页面路径+查询串（pv/leave 用）',
  `title`      VARCHAR(128) NOT NULL DEFAULT '' COMMENT '页面标题',
  `event`      VARCHAR(64)  NOT NULL DEFAULT 'pageview' COMMENT 'pageview=页面浏览，其它=功能事件名（chat_send/register…）',
  `props`      VARCHAR(2000) NOT NULL DEFAULT '' COMMENT '事件附加参数 JSON（可空）',
  `referrer`   VARCHAR(255) NOT NULL DEFAULT '' COMMENT '来源页（站内上一页或外部 referer）',
  `duration`   INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '停留秒数（leave 事件回写到同会话同页面最近一条 pv 行）',
  `ip`         VARCHAR(45)  NOT NULL DEFAULT '' COMMENT '客户端IP',
  `ua`         VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'User-Agent',
  `created_at` DATETIME     NOT NULL COMMENT '发生时间',
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_sid_created` (`sid`, `created_at`),
  KEY `idx_vid` (`vid`),
  KEY `idx_user` (`user_id`),
  KEY `idx_event_created` (`event`, `created_at`),
  KEY `idx_domain_created` (`domain`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='行为埋点：页面浏览/停留/功能事件';

CREATE TABLE IF NOT EXISTS `lp_user_login_log` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户ID（0=账号不存在）',
  `account`     VARCHAR(128) NOT NULL DEFAULT '' COMMENT '尝试登录的账号',
  `success`     TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1=成功 0=失败',
  `is_register` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1=注册后自动登录',
  `fail_reason` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '失败原因',
  `vid`         VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '访客ID（前端登录请求带上的埋点 vid）',
  `sid`         VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '会话ID',
  `ip`          VARCHAR(45)  NOT NULL DEFAULT '' COMMENT '登录IP',
  `ua`          VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'User-Agent',
  `created_at`  DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_created` (`user_id`, `created_at`),
  KEY `idx_created` (`created_at`),
  KEY `idx_vid` (`vid`),
  KEY `idx_account` (`account`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='终端用户登录日志（行为分析用）';

-- --------------------------------------------
-- 后台一级菜单：「行为分析」（单页，内部 Tab 切换）
-- component 必须与前端 glob 路径 /src/views/backend/... 完全一致
-- --------------------------------------------
INSERT INTO `ba_admin_rule`
    (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
SELECT 0, 'menu', '行为分析', 'track/analysis', 'track/analysis', 'fa fa-line-chart', 'tab', '',
       '/src/views/backend/track/analysis/index.vue', 1, 'none',
       '埋点报表：页面退出/功能使用/登录分析/停留时长/行为明细', 998, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ba_admin_rule` WHERE `name` = 'track/analysis');

SET @track_pid = (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'track/analysis' ORDER BY `id` LIMIT 1);

-- 按钮规则（action 名与后端 track.Analysis 的方法一一对应，便于非超管分组授权）
INSERT INTO `ba_admin_rule`
    (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
SELECT @track_pid, 'button', '总览漏斗', 'track/analysis/overview', '', '', NULL, '', '', 0, 'none', '', 0, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ba_admin_rule` WHERE `name` = 'track/analysis/overview');

INSERT INTO `ba_admin_rule`
    (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
SELECT @track_pid, 'button', '页面分析', 'track/analysis/page', '', '', NULL, '', '', 0, 'none', '', 0, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ba_admin_rule` WHERE `name` = 'track/analysis/page');

INSERT INTO `ba_admin_rule`
    (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
SELECT @track_pid, 'button', '功能使用', 'track/analysis/feature', '', '', NULL, '', '', 0, 'none', '', 0, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ba_admin_rule` WHERE `name` = 'track/analysis/feature');

INSERT INTO `ba_admin_rule`
    (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
SELECT @track_pid, 'button', '登录分析', 'track/analysis/login', '', '', NULL, '', '', 0, 'none', '', 0, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ba_admin_rule` WHERE `name` = 'track/analysis/login');

INSERT INTO `ba_admin_rule`
    (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
SELECT @track_pid, 'button', '行为明细', 'track/analysis/detail', '', '', NULL, '', '', 0, 'none', '', 0, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ba_admin_rule` WHERE `name` = 'track/analysis/detail');

-- --------------------------------------------
-- 付费用户 / 用户流水（2026-11-11 新增）
-- 有真实充值后需要看「谁付了钱、钱花在哪」，挂在同一个「行为分析」菜单下
-- --------------------------------------------
INSERT INTO `ba_admin_rule`
    (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
SELECT @track_pid, 'button', '付费用户', 'track/analysis/paid', '', '', NULL, '', '', 0, 'none', '付费用户画像 + 消费去向', 0, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ba_admin_rule` WHERE `name` = 'track/analysis/paid');

INSERT INTO `ba_admin_rule`
    (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
SELECT @track_pid, 'button', '用户流水', 'track/analysis/wallet', '', '', NULL, '', '', 0, 'none', '单用户充值 + 每一笔消费明细', 0, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ba_admin_rule` WHERE `name` = 'track/analysis/wallet');

-- --------------------------------------------
-- 校验
-- --------------------------------------------
SHOW TABLES LIKE 'lp_track_event';
SHOW TABLES LIKE 'lp_user_login_log';
SELECT `id`, `pid`, `type`, `title`, `name`, `status` FROM `ba_admin_rule`
WHERE `name` LIKE 'track/analysis%' ORDER BY `id`;
