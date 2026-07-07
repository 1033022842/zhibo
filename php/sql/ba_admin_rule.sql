/*
 Navicat Premium Data Transfer

 Source Server         : 本地3306
 Source Server Type    : MySQL
 Source Server Version : 80012 (8.0.12)
 Source Host           : localhost:3306
 Source Schema         : live_platform

 Target Server Type    : MySQL
 Target Server Version : 80012 (8.0.12)
 File Encoding         : 65001

 Date: 06/07/2026 22:37:47
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for ba_admin_rule
-- ----------------------------
DROP TABLE IF EXISTS `ba_admin_rule`;
CREATE TABLE `ba_admin_rule`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `pid` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '上级菜单',
  `type` enum('menu_dir','menu','button') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'menu' COMMENT '类型:menu_dir=菜单目录,menu=菜单项,button=页面按钮',
  `title` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '标题',
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '规则名称',
  `path` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '路由路径',
  `icon` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '图标',
  `menu_type` enum('tab','link','iframe') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '菜单类型:tab=选项卡,link=链接,iframe=Iframe',
  `url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Url',
  `component` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '组件路径',
  `keepalive` tinyint(4) UNSIGNED NOT NULL DEFAULT 0 COMMENT '缓存:0=关闭,1=开启',
  `extend` enum('none','add_rules_only','add_menu_only') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none' COMMENT '扩展属性:none=无,add_rules_only=只添加为路由,add_menu_only=只添加为菜单',
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '备注',
  `weigh` int(11) NOT NULL DEFAULT 0 COMMENT '权重',
  `status` tinyint(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态:0=禁用,1=启用',
  `update_time` bigint(16) UNSIGNED NULL DEFAULT NULL COMMENT '更新时间',
  `create_time` bigint(16) UNSIGNED NULL DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `pid`(`pid` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 129 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '菜单和权限规则表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of ba_admin_rule
-- ----------------------------
INSERT INTO `ba_admin_rule` VALUES (1, 0, 'menu', '控制台', 'dashboard', 'dashboard', 'fa fa-dashboard', 'tab', '', '/src/views/backend/dashboard.vue', 1, 'none', 'Remark lang', 999, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (2, 0, 'menu_dir', '权限管理', 'auth', 'auth', 'fa fa-group', NULL, '', '', 0, 'none', '', 100, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (3, 2, 'menu', '角色组管理', 'auth/group', 'auth/group', 'fa fa-group', 'tab', '', '/src/views/backend/auth/group/index.vue', 1, 'none', 'Remark lang', 99, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (4, 3, 'button', '查看', 'auth/group/index', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (5, 3, 'button', '添加', 'auth/group/add', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (6, 3, 'button', '编辑', 'auth/group/edit', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (7, 3, 'button', '删除', 'auth/group/del', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (8, 2, 'menu', '管理员管理', 'auth/admin', 'auth/admin', 'el-icon-UserFilled', 'tab', '', '/src/views/backend/auth/admin/index.vue', 1, 'none', '', 98, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (9, 8, 'button', '查看', 'auth/admin/index', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (10, 8, 'button', '添加', 'auth/admin/add', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (11, 8, 'button', '编辑', 'auth/admin/edit', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (12, 8, 'button', '删除', 'auth/admin/del', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (13, 2, 'menu', '菜单规则管理', 'auth/rule', 'auth/rule', 'el-icon-Grid', 'tab', '', '/src/views/backend/auth/rule/index.vue', 1, 'none', '', 97, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (14, 13, 'button', '查看', 'auth/rule/index', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (15, 13, 'button', '添加', 'auth/rule/add', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (16, 13, 'button', '编辑', 'auth/rule/edit', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (17, 13, 'button', '删除', 'auth/rule/del', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (18, 13, 'button', '快速排序', 'auth/rule/sortable', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (19, 2, 'menu', '管理员日志管理', 'auth/adminLog', 'auth/adminLog', 'el-icon-List', 'tab', '', '/src/views/backend/auth/adminLog/index.vue', 1, 'none', '', 96, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (20, 19, 'button', '查看', 'auth/adminLog/index', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (21, 0, 'menu_dir', '会员管理', 'user', 'user', 'fa fa-drivers-license', NULL, '', '', 0, 'none', '', 95, 0, 1779856431, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (22, 21, 'menu', '会员管理', 'user/user', 'user/user', 'fa fa-user', 'tab', '', '/src/views/backend/user/user/index.vue', 1, 'none', '', 94, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (23, 22, 'button', '查看', 'user/user/index', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (24, 22, 'button', '添加', 'user/user/add', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (25, 22, 'button', '编辑', 'user/user/edit', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (26, 22, 'button', '删除', 'user/user/del', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (27, 21, 'menu', '会员分组管理', 'user/group', 'user/group', 'fa fa-group', 'tab', '', '/src/views/backend/user/group/index.vue', 1, 'none', '', 93, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (28, 27, 'button', '查看', 'user/group/index', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (29, 27, 'button', '添加', 'user/group/add', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (30, 27, 'button', '编辑', 'user/group/edit', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (31, 27, 'button', '删除', 'user/group/del', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (32, 21, 'menu', '会员规则管理', 'user/rule', 'user/rule', 'fa fa-th-list', 'tab', '', '/src/views/backend/user/rule/index.vue', 1, 'none', '', 92, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (33, 32, 'button', '查看', 'user/rule/index', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (34, 32, 'button', '添加', 'user/rule/add', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (35, 32, 'button', '编辑', 'user/rule/edit', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (36, 32, 'button', '删除', 'user/rule/del', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (37, 32, 'button', '快速排序', 'user/rule/sortable', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (38, 21, 'menu', '会员余额管理', 'user/moneyLog', 'user/moneyLog', 'el-icon-Money', 'tab', '', '/src/views/backend/user/moneyLog/index.vue', 1, 'none', '', 91, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (39, 38, 'button', '查看', 'user/moneyLog/index', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (40, 38, 'button', '添加', 'user/moneyLog/add', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (41, 21, 'menu', '会员积分管理', 'user/scoreLog', 'user/scoreLog', 'el-icon-Discount', 'tab', '', '/src/views/backend/user/scoreLog/index.vue', 1, 'none', '', 90, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (42, 41, 'button', '查看', 'user/scoreLog/index', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (43, 41, 'button', '添加', 'user/scoreLog/add', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (44, 0, 'menu_dir', '常规管理', 'routine', 'routine', 'fa fa-cogs', NULL, '', '', 0, 'none', '', 89, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (45, 44, 'menu', '系统配置', 'routine/config', 'routine/config', 'el-icon-Tools', 'tab', '', '/src/views/backend/routine/config/index.vue', 1, 'none', '', 88, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (46, 45, 'button', '查看', 'routine/config/index', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (47, 45, 'button', '编辑', 'routine/config/edit', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (48, 44, 'menu', '附件管理', 'routine/attachment', 'routine/attachment', 'fa fa-folder', 'tab', '', '/src/views/backend/routine/attachment/index.vue', 1, 'none', 'Remark lang', 87, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (49, 48, 'button', '查看', 'routine/attachment/index', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (50, 48, 'button', '编辑', 'routine/attachment/edit', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (51, 48, 'button', '删除', 'routine/attachment/del', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (52, 44, 'menu', '个人资料', 'routine/adminInfo', 'routine/adminInfo', 'fa fa-user', 'tab', '', '/src/views/backend/routine/adminInfo.vue', 1, 'none', '', 86, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (53, 52, 'button', '查看', 'routine/adminInfo/index', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (54, 52, 'button', '编辑', 'routine/adminInfo/edit', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (55, 0, 'menu_dir', '数据安全管理', 'security', 'security', 'fa fa-shield', NULL, '', '', 0, 'none', '', 85, 0, 1779856429, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (56, 55, 'menu', '数据回收站', 'security/dataRecycleLog', 'security/dataRecycleLog', 'fa fa-database', 'tab', '', '/src/views/backend/security/dataRecycleLog/index.vue', 1, 'none', '', 84, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (57, 56, 'button', '查看', 'security/dataRecycleLog/index', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (58, 56, 'button', '删除', 'security/dataRecycleLog/del', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (59, 56, 'button', '还原', 'security/dataRecycleLog/restore', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (60, 56, 'button', '查看详情', 'security/dataRecycleLog/info', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (61, 55, 'menu', '敏感数据修改记录', 'security/sensitiveDataLog', 'security/sensitiveDataLog', 'fa fa-expeditedssl', 'tab', '', '/src/views/backend/security/sensitiveDataLog/index.vue', 1, 'none', '', 83, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (62, 61, 'button', '查看', 'security/sensitiveDataLog/index', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (63, 61, 'button', '删除', 'security/sensitiveDataLog/del', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (64, 61, 'button', '回滚', 'security/sensitiveDataLog/rollback', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (65, 61, 'button', '查看详情', 'security/sensitiveDataLog/info', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (66, 55, 'menu', '数据回收规则管理', 'security/dataRecycle', 'security/dataRecycle', 'fa fa-database', 'tab', '', '/src/views/backend/security/dataRecycle/index.vue', 1, 'none', 'Remark lang', 82, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (67, 66, 'button', '查看', 'security/dataRecycle/index', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (68, 66, 'button', '添加', 'security/dataRecycle/add', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (69, 66, 'button', '编辑', 'security/dataRecycle/edit', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (70, 66, 'button', '删除', 'security/dataRecycle/del', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (71, 55, 'menu', '敏感字段规则管理', 'security/sensitiveData', 'security/sensitiveData', 'fa fa-expeditedssl', 'tab', '', '/src/views/backend/security/sensitiveData/index.vue', 1, 'none', 'Remark lang', 81, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (72, 71, 'button', '查看', 'security/sensitiveData/index', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (73, 71, 'button', '添加', 'security/sensitiveData/add', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (74, 71, 'button', '编辑', 'security/sensitiveData/edit', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (75, 71, 'button', '删除', 'security/sensitiveData/del', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (76, 0, 'menu', 'BuildAdmin', 'buildadmin', 'buildadmin', 'local-logo', 'link', 'https://doc.buildadmin.com', '', 0, 'none', '', 0, 0, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (77, 45, 'button', '添加', 'routine/config/add', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (78, 0, 'menu', '模块市场', 'moduleStore/moduleStore', 'moduleStore', 'el-icon-GoodsFilled', 'tab', '', '/src/views/backend/module/index.vue', 1, 'none', '', 86, 0, 1779856430, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (79, 78, 'button', '查看', 'moduleStore/moduleStore/index', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (80, 78, 'button', '安装', 'moduleStore/moduleStore/install', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (81, 78, 'button', '调整状态', 'moduleStore/moduleStore/changeState', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (82, 78, 'button', '卸载', 'moduleStore/moduleStore/uninstall', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (83, 78, 'button', '更新', 'moduleStore/moduleStore/update', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (84, 0, 'menu', 'CRUD代码生成', 'crud/crud', 'crud/crud', 'fa fa-code', 'tab', '', '/src/views/backend/crud/index.vue', 1, 'none', '', 80, 0, 1779856428, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (85, 84, 'button', '查看', 'crud/crud/index', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (86, 84, 'button', '生成', 'crud/crud/generate', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (87, 84, 'button', '删除', 'crud/crud/delete', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (88, 45, 'button', '删除', 'routine/config/del', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_rule` VALUES (89, 1, 'button', '查看', 'dashboard/index', '', '', NULL, '', '', 0, 'none', '', 0, 1, 1778942777, 1778942777);
INSERT INTO `ba_admin_rule` VALUES (90, 0, 'menu_dir', '直播运营', 'live', 'live', 'fa fa-video-camera', 'tab', '', 'Layout', 1, 'none', '直播后台运营菜单', 120, 0, 1779248396, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (91, 122, 'menu', '人设管理', 'live/persona', 'live/persona', 'fa fa-user-circle', 'tab', '', '/src/views/backend/live/persona/index.vue', 1, 'none', '直播人设配置', 119, 1, 1779248396, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (92, 91, 'button', '查看', 'live/persona/index', '', '', 'tab', '', '', 0, 'none', '', 10, 1, 1779085601, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (93, 91, 'button', '新增', 'live/persona/add', '', '', 'tab', '', '', 0, 'none', '', 9, 1, 1779085601, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (94, 91, 'button', '编辑', 'live/persona/edit', '', '', 'tab', '', '', 0, 'none', '', 8, 1, 1779085601, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (95, 91, 'button', '删除', 'live/persona/del', '', '', 'tab', '', '', 0, 'none', '', 7, 1, 1779085601, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (96, 91, 'button', '选择', 'live/persona/select', '', '', 'tab', '', '', 0, 'none', '', 6, 1, 1779085601, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (97, 122, 'menu', '素材管理', 'live/mediaAsset', 'live/mediaAsset', 'fa fa-film', 'tab', '', '/src/views/backend/live/mediaAsset/index.vue', 1, 'none', '直播素材池管理', 118, 1, 1779248396, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (98, 97, 'button', '查看', 'live/mediaAsset/index', '', '', 'tab', '', '', 0, 'none', '', 10, 1, 1779085601, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (99, 97, 'button', '新增', 'live/mediaAsset/add', '', '', 'tab', '', '', 0, 'none', '', 9, 1, 1779085601, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (100, 97, 'button', '编辑', 'live/mediaAsset/edit', '', '', 'tab', '', '', 0, 'none', '', 8, 1, 1779085601, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (101, 97, 'button', '删除', 'live/mediaAsset/del', '', '', 'tab', '', '', 0, 'none', '', 7, 1, 1779085601, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (102, 97, 'button', '选择', 'live/mediaAsset/select', '', '', 'tab', '', '', 0, 'none', '', 6, 1, 1779085601, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (103, 122, 'menu', '房间管理', 'live/room', 'live/room', 'fa fa-television', 'tab', '', '/src/views/backend/live/room/index.vue', 1, 'none', '直播房间与播单绑定', 117, 1, 1779248396, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (104, 103, 'button', '查看', 'live/room/index', '', '', 'tab', '', '', 0, 'none', '', 10, 1, 1779085601, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (105, 103, 'button', '新增', 'live/room/add', '', '', 'tab', '', '', 0, 'none', '', 9, 1, 1779085601, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (106, 103, 'button', '编辑', 'live/room/edit', '', '', 'tab', '', '', 0, 'none', '', 8, 1, 1779085601, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (107, 103, 'button', '删除', 'live/room/del', '', '', 'tab', '', '', 0, 'none', '', 7, 1, 1779085601, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (108, 103, 'button', '选择', 'live/room/select', '', '', 'tab', '', '', 0, 'none', '', 6, 1, 1779085601, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (109, 122, 'menu', '礼物管理', 'live/gift', 'live/gift', 'fa fa-gift', 'tab', '', '/src/views/backend/live/gift/index.vue', 0, 'none', '直播礼物配置', 116, 1, 1779248396, 1779248396);
INSERT INTO `ba_admin_rule` VALUES (110, 109, 'button', '查看', 'live/gift/index', '', '', 'tab', '', '', 0, 'none', '', 10, 1, 1779248396, 1779248396);
INSERT INTO `ba_admin_rule` VALUES (111, 109, 'button', '新增', 'live/gift/add', '', '', 'tab', '', '', 0, 'none', '', 9, 1, 1779248396, 1779248396);
INSERT INTO `ba_admin_rule` VALUES (112, 109, 'button', '编辑', 'live/gift/edit', '', '', 'tab', '', '', 0, 'none', '', 8, 1, 1779248396, 1779248396);
INSERT INTO `ba_admin_rule` VALUES (113, 109, 'button', '删除', 'live/gift/del', '', '', 'tab', '', '', 0, 'none', '', 7, 1, 1779248396, 1779248396);
INSERT INTO `ba_admin_rule` VALUES (114, 109, 'button', '选择', 'live/gift/select', '', '', 'tab', '', '', 0, 'none', '', 6, 1, 1779248396, 1779248396);
INSERT INTO `ba_admin_rule` VALUES (115, 122, 'menu', '直播平台用户', 'user/liveUser', 'live/liveUser', 'fa fa-users', 'tab', '', '/src/views/backend/user/liveUser/index.vue', 0, 'none', '', 115, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (121, 44, 'menu', '短信服务配置', 'routine/smsConfig', 'routine/smsConfig', 'fa fa-message', 'tab', '', '/src/views/backend/routine/smsConfig/index.vue', 0, 'none', '', 4, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (122, 0, 'menu_dir', '直播运营', 'liveOps', '/liveOps', 'fa fa-youtube-play', 'tab', '', '', 0, 'none', '', 200, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (123, 122, 'menu', '收益明细', 'live/revenue', '/live/revenue', 'fa fa-list-alt', 'tab', '', '/src/views/backend/live/revenue/index.vue', 0, 'none', '', 1, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (124, 122, 'menu', '收入排行榜', 'live/leaderboard', '/live/leaderboard', 'fa fa-trophy', 'tab', '', '/src/views/backend/live/leaderboard/index.vue', 0, 'none', '', 2, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (125, 122, 'menu', '历史切片', 'live/replayClip', '/live/replayClip', 'fa fa-video-camera', 'tab', '', '/src/views/backend/live/replayClip/index.vue', 0, 'none', '', 3, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (126, 122, 'menu_dir', '定时维护', 'live/maintenance', '/live/maintenance', 'fa fa-clock-o', 'tab', '', '', 0, 'none', '', 4, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (127, 126, 'menu', '维护任务', 'live/maintenanceTask', '/live/maintenanceTask', 'fa fa-list', 'tab', '', '/src/views/backend/live/maintenanceTask/index.vue', 0, 'none', '', 1, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (128, 126, 'menu', 'TG通知配置', 'live/maintenanceConfig', '/live/maintenanceConfig', 'fa fa-telegram', 'tab', '', '/src/views/backend/live/maintenanceConfig/index.vue', 0, 'none', '', 2, 1, NULL, NULL);

SET FOREIGN_KEY_CHECKS = 1;
