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

 Date: 05/07/2026 14:34:18
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for ba_admin
-- ----------------------------
DROP TABLE IF EXISTS `ba_admin`;
CREATE TABLE `ba_admin`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `username` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '用户名',
  `nickname` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '昵称',
  `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '头像',
  `email` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '邮箱',
  `mobile` varchar(11) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '手机',
  `login_failure` tinyint(4) UNSIGNED NOT NULL DEFAULT 0 COMMENT '登录失败次数',
  `last_login_time` bigint(16) UNSIGNED NULL DEFAULT NULL COMMENT '上次登录时间',
  `last_login_ip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '上次登录IP',
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '密码',
  `salt` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '密码盐（废弃待删）',
  `motto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '签名',
  `status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '状态:enable=启用,disable=禁用',
  `update_time` bigint(16) UNSIGNED NULL DEFAULT NULL COMMENT '更新时间',
  `create_time` bigint(16) UNSIGNED NULL DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `username`(`username` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '管理员表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of ba_admin
-- ----------------------------
INSERT INTO `ba_admin` VALUES (1, 'admin', 'Admin', '', 'admin@buildadmin.com', '18888888888', 0, 1783157976, '127.0.0.1', '$2y$10$b/w7wjNIymPfjY62LTInBuelicvFAjdMLXNUJhrd7ZvI/ckt3FQDm', '', '', 'enable', 1783157976, 1778942775);

-- ----------------------------
-- Table structure for ba_admin_group
-- ----------------------------
DROP TABLE IF EXISTS `ba_admin_group`;
CREATE TABLE `ba_admin_group`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `pid` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '上级分组',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '组名',
  `rules` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '权限规则ID',
  `status` tinyint(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态:0=禁用,1=启用',
  `update_time` bigint(16) UNSIGNED NULL DEFAULT NULL COMMENT '更新时间',
  `create_time` bigint(16) UNSIGNED NULL DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 5 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '管理分组表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of ba_admin_group
-- ----------------------------
INSERT INTO `ba_admin_group` VALUES (1, 0, '超级管理组', '*', 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_group` VALUES (2, 1, '一级管理员', '1,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,77,48,49,50,51,52,53,54,55,56,57,58,59,60,61,62,63,64,65,66,67,68,69,70,71,72,73,74,75,76,89', 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_group` VALUES (3, 2, '二级管理员', '21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43', 1, 1778942775, 1778942775);
INSERT INTO `ba_admin_group` VALUES (4, 3, '三级管理员', '55,56,57,58,59,60,61,62,63,64,65,66,67,68,69,70,71,72,73,74,75', 1, 1778942775, 1778942775);

-- ----------------------------
-- Table structure for ba_admin_group_access
-- ----------------------------
DROP TABLE IF EXISTS `ba_admin_group_access`;
CREATE TABLE `ba_admin_group_access`  (
  `uid` int(11) UNSIGNED NOT NULL COMMENT '管理员ID',
  `group_id` int(11) UNSIGNED NOT NULL COMMENT '分组ID',
  INDEX `uid`(`uid` ASC) USING BTREE,
  INDEX `group_id`(`group_id` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '管理分组映射表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of ba_admin_group_access
-- ----------------------------
INSERT INTO `ba_admin_group_access` VALUES (1, 1);

-- ----------------------------
-- Table structure for ba_admin_log
-- ----------------------------
DROP TABLE IF EXISTS `ba_admin_log`;
CREATE TABLE `ba_admin_log`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `admin_id` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '管理员ID',
  `username` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '管理员用户名',
  `url` varchar(1500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '操作Url',
  `title` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '日志标题',
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '请求数据',
  `ip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'IP',
  `useragent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'User-Agent',
  `create_time` bigint(16) UNSIGNED NULL DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 46 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '管理员日志表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of ba_admin_log
-- ----------------------------
INSERT INTO `ba_admin_log` VALUES (1, 1, 'admin', '/admin/Index/login', '登录', '{\"username\":\"admin\",\"password\":\"***\",\"keep\":\"\",\"captchaId\":\"ce96f19f-3309-4c7c-beed-3ad263f67b6d\",\"captchaInfo\":\"90,67-194,155;350;200\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1778942930);
INSERT INTO `ba_admin_log` VALUES (2, 1, 'admin', '/admin/Index/login', '登录', '{\"username\":\"admin\",\"password\":\"***\",\"keep\":\"\",\"captchaId\":\"34bc42f3-69c0-45ed-9a69-ddb506126e46\",\"captchaInfo\":\"35,95-329,120;350;200\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1779087067);
INSERT INTO `ba_admin_log` VALUES (3, 1, 'admin', '/admin/auth.Rule/edit', '菜单规则管理-编辑', '{\"id\":\"91\",\"keepalive\":\"1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1779087104);
INSERT INTO `ba_admin_log` VALUES (4, 1, 'admin', '/admin/auth.Rule/edit', '菜单规则管理-编辑', '{\"id\":\"97\",\"keepalive\":\"1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1779087105);
INSERT INTO `ba_admin_log` VALUES (5, 1, 'admin', '/admin/auth.Rule/edit', '菜单规则管理-编辑', '{\"id\":\"103\",\"keepalive\":\"1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1779087106);
INSERT INTO `ba_admin_log` VALUES (6, 1, 'admin', '/admin/auth.Rule/edit', '菜单规则管理-编辑', '{\"id\":\"90\",\"keepalive\":\"1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1779087112);
INSERT INTO `ba_admin_log` VALUES (7, 1, 'admin', '/admin/Index/login', '登录', '{\"username\":\"admin\",\"password\":\"***\",\"keep\":\"\",\"captchaId\":\"dd9fa17f-0a44-4b5f-b2fe-5c32cf935415\",\"captchaInfo\":\"253,109-35,67;350;200\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1779087173);
INSERT INTO `ba_admin_log` VALUES (8, 1, 'admin', '/admin/Index/login', '登录', '{\"username\":\"admin\",\"password\":\"***\",\"keep\":\"\",\"captchaId\":\"db0ae55d-773a-4d45-894f-c1e66f864cfc\",\"captchaInfo\":\"93,95-303,128;350;200\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1779087354);
INSERT INTO `ba_admin_log` VALUES (9, 1, 'admin', '/admin/Index/login', '登录', '{\"username\":\"admin\",\"password\":\"***\",\"keep\":\"\",\"captchaId\":\"056fcfbc-d0f8-4e69-902c-94c6f56538e4\",\"captchaInfo\":\"72,55-161,68;350;200\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1779087440);
INSERT INTO `ba_admin_log` VALUES (10, 1, 'admin', '/admin/Index/login', '登录', '{\"username\":\"admin\",\"password\":\"***\",\"keep\":\"\",\"captchaId\":\"f102e1fb-bbdd-4300-b6da-6a7fe12c3a9d\",\"captchaInfo\":\"153,121-240,155;350;200\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1779087754);
INSERT INTO `ba_admin_log` VALUES (11, 1, 'admin', '/admin/Index/login', '登录', '{\"username\":\"admin\",\"password\":\"***\",\"keep\":\"\",\"captchaId\":\"fa56fc79-3efa-4f04-9e16-2967fb6d2a45\",\"captchaInfo\":\"266,112-146,96;350;200\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1779087826);
INSERT INTO `ba_admin_log` VALUES (12, 1, 'admin', '/admin/Index/login', '登录', '{\"username\":\"admin\",\"password\":\"***\",\"keep\":\"\",\"captchaId\":\"49a25a5d-2a48-43b5-9b12-bfd0a631f19d\",\"captchaInfo\":\"190,54-262,122;350;200\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1779088250);
INSERT INTO `ba_admin_log` VALUES (13, 1, 'admin', '/admin/Index/login', '登录', '{\"username\":\"admin\",\"password\":\"***\",\"keep\":\"\",\"captchaId\":\"32345f71-123d-472d-8c70-bcc485ef9b82\",\"captchaInfo\":\"138,108-55,156;350;200\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1779088608);
INSERT INTO `ba_admin_log` VALUES (14, 1, 'admin', '/admin/ajax/upload?server=1', '上传文件', '{\"server\":\"1\",\"topic\":\"live\"}', '127.0.0.1', 'curl/8.13.0', 1779096312);
INSERT INTO `ba_admin_log` VALUES (15, 1, 'admin', '/admin/live.MediaAsset/add?server=1', '素材管理-新增', '{\"server\":\"1\",\"asset_code\":\"e2e_asset_20260518_1\",\"title\":\"E2E\\u8054\\u8c03\\u7d20\\u67501\",\"file_url\":\"\\/storage\\/live\\/20260518\\/demo_live_asset0e2b8da6ffb71124ee7e28e25094fd6fcfda45ec.mp4\",\"asset_type\":\"video\",\"scene_type\":\"public\",\"duration_ms\":\"10000\",\"status\":\"1\"}', '127.0.0.1', 'curl/8.13.0', 1779096437);
INSERT INTO `ba_admin_log` VALUES (16, 1, 'admin', '/admin/live.Room/add?server=1', '房间管理-新增', '{\"server\":\"1\",\"room_no\":\"E2E1001\",\"title\":\"E2E\\u8054\\u8c03\\u6d4b\\u8bd5\\u623f\\u95f4\",\"subtitle\":\"\\u540e\\u53f0\\u4e0a\\u4f20\\u7d20\\u6750\\u540e\\u7684\\u771f\\u5b9e\\u8054\\u8c03\\u623f\\u95f4\",\"persona_id\":\"1\",\"asset_ids\":[\"5\"],\"tag_names\":\"\\u8054\\u8c03,\\u6d4b\\u8bd5\",\"sort\":\"50\",\"status\":\"1\"}', '127.0.0.1', 'curl/8.13.0', 1779096594);
INSERT INTO `ba_admin_log` VALUES (17, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"\\u60c5\\u611f,\\u70ed\\u95e8\",\"asset_ids\":[\"2\",\"3\",\"4\"],\"playlist_name\":\"\\u6df1\\u591c\\u60c5\\u611f\\u7535\\u53f0\\u64ad\\u5355\",\"id\":\"1\",\"room_no\":\"R1001\",\"title\":\"\\u6df1\\u591c\\u60c5\\u611f\\u7535\\u53f0\",\"subtitle\":\"\\u966a\\u4f60\\u804a\\u5929\\u5230\\u5929\\u4eae\",\"persona_id\":\"2\",\"room_type\":\"live\",\"status\":\"1\",\"cover_url\":\"https:\\/\\/picsum.photos\\/seed\\/live-room-1\\/720\\/1280\",\"sort\":\"120\",\"created_at\":\"2026-05-17 09:41:46\",\"updated_at\":\"2026-05-18 09:49:16\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1779166778);
INSERT INTO `ba_admin_log` VALUES (18, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"\\u60c5\\u611f,\\u70ed\\u95e8\",\"asset_ids\":[\"2\"],\"playlist_name\":\"\\u6df1\\u591c\\u60c5\\u611f\\u7535\\u53f0\\u64ad\\u5355\",\"id\":\"1\",\"room_no\":\"R1001\",\"title\":\"\\u6df1\\u591c\\u60c5\\u611f\\u7535\\u53f0\",\"subtitle\":\"\\u966a\\u4f60\\u804a\\u5929\\u5230\\u5929\\u4eae\",\"persona_id\":\"2\",\"room_type\":\"live\",\"status\":\"1\",\"cover_url\":\"https:\\/\\/picsum.photos\\/seed\\/live-room-1\\/720\\/1280\",\"sort\":\"120\",\"created_at\":\"2026-05-17 09:41:46\",\"updated_at\":\"2026-05-18 09:49:16\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1779170093);
INSERT INTO `ba_admin_log` VALUES (19, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"\\u60c5\\u611f,\\u70ed\\u95e8\",\"asset_ids\":[\"2\"],\"playlist_name\":\"\\u6df1\\u591c\\u60c5\\u611f\\u7535\\u53f0\\u64ad\\u5355\",\"id\":\"1\",\"room_no\":\"R1001\",\"title\":\"\\u6df1\\u591c\\u60c5\\u611f\\u7535\\u53f02\",\"subtitle\":\"\\u966a\\u4f60\\u804a\\u5929\\u5230\\u5929\\u4eae\",\"persona_id\":\"2\",\"room_type\":\"live\",\"status\":\"1\",\"cover_url\":\"https:\\/\\/picsum.photos\\/seed\\/live-room-1\\/720\\/1280\",\"sort\":\"120\",\"created_at\":\"2026-05-17 09:41:46\",\"updated_at\":\"2026-05-18 09:49:16\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1779183004);
INSERT INTO `ba_admin_log` VALUES (20, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"\\u653e\\u677e,\\u8f7b\\u97f3\\u4e50\",\"asset_ids\":[\"3\"],\"playlist_name\":\"\\u5348\\u540e\\u8f7b\\u97f3\\u4e50\\u76f4\\u64ad\\u95f4\\u64ad\\u5355\",\"id\":\"2\",\"room_no\":\"R1002\",\"title\":\"\\u5348\\u540e\\u8f7b\\u97f3\\u4e50\\u76f4\\u64ad\\u95f43\",\"subtitle\":\"\\u5faa\\u73af\\u64ad\\u653e\\u8212\\u7f13\\u6b4c\\u5355\\u548c\\u804a\\u5929\\u4e92\\u52a8\",\"persona_id\":\"3\",\"room_type\":\"live\",\"status\":\"1\",\"cover_url\":\"https:\\/\\/picsum.photos\\/seed\\/live-room-2\\/720\\/1280\",\"sort\":\"110\",\"created_at\":\"2026-05-18 09:49:16\",\"updated_at\":\"2026-05-18 09:49:16\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1779183009);
INSERT INTO `ba_admin_log` VALUES (21, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"\\u4e13\\u6ce8,\\u5b66\\u4e60\",\"asset_ids\":[\"4\"],\"playlist_name\":\"\\u6e05\\u6668\\u81ea\\u4e60\\u76f4\\u64ad\\u95f4\\u64ad\\u5355\",\"id\":\"3\",\"room_no\":\"R1003\",\"title\":\"\\u6e05\\u6668\\u81ea\\u4e60\\u76f4\\u64ad\\u95f44\",\"subtitle\":\"\\u9002\\u5408\\u5207\\u540e\\u53f0\\u6302\\u673a\\u7684\\u4e13\\u6ce8\\u966a\\u4f34\\u6d41\",\"persona_id\":\"4\",\"room_type\":\"live\",\"status\":\"1\",\"cover_url\":\"https:\\/\\/picsum.photos\\/seed\\/live-room-3\\/720\\/1280\",\"sort\":\"100\",\"created_at\":\"2026-05-18 09:49:16\",\"updated_at\":\"2026-05-18 09:49:16\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1779183012);
INSERT INTO `ba_admin_log` VALUES (22, 0, 'admin', '/admin/Index/login', '登录', '{\"username\":\"admin\",\"password\":\"***\",\"keep\":\"\",\"captchaId\":\"700dc925-956d-4170-8d5e-c1846003d63e\",\"captchaInfo\":\"225,121-160,113;350;200\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1779249843);
INSERT INTO `ba_admin_log` VALUES (23, 1, 'admin', '/admin/Index/login', '登录', '{\"username\":\"admin\",\"password\":\"***\",\"keep\":\"\",\"captchaId\":\"700dc925-956d-4170-8d5e-c1846003d63e\",\"captchaInfo\":\"109,63-18,17;350;200\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1779249859);
INSERT INTO `ba_admin_log` VALUES (24, 1, 'admin', '/admin/Index/login', '登录', '{\"username\":\"admin\",\"password\":\"***\",\"keep\":\"\",\"captchaId\":\"f53db964-243f-459c-a72d-7f4817271704\",\"captchaInfo\":\"305,152-143,144;350;200\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1779270263);
INSERT INTO `ba_admin_log` VALUES (25, 1, 'admin', '/admin/Index/login', '登录', '{\"username\":\"admin\",\"password\":\"***\",\"keep\":\"\",\"captchaId\":\"ef401298-4e5e-474f-87cc-7c5243776209\",\"captchaInfo\":\"21,172-231,87;350;200\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1779270649);
INSERT INTO `ba_admin_log` VALUES (26, 1, 'admin', '/admin/Index/login', '登录', '{\"username\":\"admin\",\"password\":\"***\",\"keep\":\"\",\"captchaId\":\"9fbdf0a3-236c-42b3-94db-92ee515b1a65\",\"captchaInfo\":\"70,24-247,176;350;200\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1779691808);
INSERT INTO `ba_admin_log` VALUES (27, 1, 'admin', '/admin/Index/login', '登录', '{\"username\":\"admin\",\"password\":\"***\",\"keep\":\"\",\"captchaId\":\"ee82a112-d4a9-4df8-99be-57006d24f980\",\"captchaInfo\":\"92,128-107,9;350;200\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1779854560);
INSERT INTO `ba_admin_log` VALUES (28, 1, 'admin', '/admin/Index/login', '登录', '{\"username\":\"admin\",\"password\":\"***\",\"keep\":\"\",\"captchaId\":\"7f06254b-2e61-4d94-9c84-f426e0540d1b\",\"captchaInfo\":\"225,140-297,95;350;200\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1779854987);
INSERT INTO `ba_admin_log` VALUES (29, 1, 'admin', '/admin/Index/login', '登录', '{\"username\":\"admin\",\"password\":\"***\",\"keep\":\"\",\"captchaId\":\"e6be9aa2-e3c4-4c3d-a125-8ff8e4e0c233\",\"captchaInfo\":\"290,144-144,40;350;200\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1779855258);
INSERT INTO `ba_admin_log` VALUES (30, 1, 'admin', '/admin/routine.Config/edit', '系统配置-编辑', '{\"smtp_server\":\"smtp.qq.com\",\"smtp_port\":\"465\",\"smtp_user\":\"\",\"smtp_pass\":\"\",\"smtp_verification\":\"SSL\",\"smtp_sender_mail\":\"\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1779855279);
INSERT INTO `ba_admin_log` VALUES (31, 1, 'admin', '/admin/auth.Rule/edit', '菜单规则管理-编辑', '{\"id\":\"84\",\"status\":\"0\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1779856428);
INSERT INTO `ba_admin_log` VALUES (32, 1, 'admin', '/admin/auth.Rule/edit', '菜单规则管理-编辑', '{\"id\":\"55\",\"status\":\"0\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1779856429);
INSERT INTO `ba_admin_log` VALUES (33, 1, 'admin', '/admin/auth.Rule/edit', '菜单规则管理-编辑', '{\"id\":\"78\",\"status\":\"0\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1779856430);
INSERT INTO `ba_admin_log` VALUES (34, 1, 'admin', '/admin/auth.Rule/edit', '菜单规则管理-编辑', '{\"id\":\"21\",\"status\":\"0\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1779856431);
INSERT INTO `ba_admin_log` VALUES (35, 1, 'admin', '/admin/Index/login', '登录', '{\"username\":\"admin\",\"password\":\"***\",\"keep\":\"\",\"captchaId\":\"e691c0e2-8e8a-46b6-b9b9-a4d518e42ced\",\"captchaInfo\":\"232,123-201,54;350;200\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1780032146);
INSERT INTO `ba_admin_log` VALUES (36, 1, 'admin', '/admin/Index/login', '登录', '{\"username\":\"admin\",\"password\":\"***\",\"keep\":\"\",\"captchaId\":\"1920fd6f-fc45-45cc-a2e1-2e5d4cea2951\",\"captchaInfo\":\"321,56-144,141;350;200\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1782877234);
INSERT INTO `ba_admin_log` VALUES (37, 1, 'admin', '/admin/Index/login', '登录', '{\"username\":\"admin\",\"password\":\"***\",\"keep\":\"\",\"captchaId\":\"bf4bf79d-0c28-4b2e-b2be-25519668eea6\",\"captchaInfo\":\"312,126-38,169;350;200\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1782892520);
INSERT INTO `ba_admin_log` VALUES (38, 1, 'admin', '/admin/Index/login', '登录', '{\"username\":\"admin\",\"password\":\"***\",\"keep\":\"\",\"captchaId\":\"b06fb783-d9f5-4f90-938c-68db96c9472a\",\"captchaInfo\":\"145,136-311,125;350;200\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1783154705);
INSERT INTO `ba_admin_log` VALUES (39, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"\\u6d4b\\u8bd5,\\u8054\\u8c03\",\"asset_ids\":[\"5\"],\"playlist_name\":\"E2E\\u8054\\u8c03\\u6d4b\\u8bd5\\u623f\\u95f4\\u64ad\\u5355\",\"id\":\"4\",\"room_no\":\"E2E1001\",\"title\":\"E2E\\u8054\\u8c03\\u6d4b\\u8bd5\\u623f\\u95f4\",\"subtitle\":\"\\u540e\\u53f0\\u4e0a\\u4f20\\u7d20\\u6750\\u540e\\u7684\\u771f\\u5b9e\\u8054\\u8c03\\u623f\\u95f4\",\"persona_id\":\"1\",\"room_type\":\"live\",\"status\":\"2\",\"cover_url\":\"\",\"sort\":\"50\",\"created_at\":\"2026-05-18 17:29:54\",\"updated_at\":\"2026-05-18 17:29:54\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1783154903);
INSERT INTO `ba_admin_log` VALUES (40, 1, 'admin', '/admin/Index/login', '登录', '{\"username\":\"admin\",\"password\":\"***\",\"keep\":\"\",\"captchaId\":\"a5eb3629-1c8d-498a-88b8-027ddde904b8\",\"captchaInfo\":\"335,34-117,102;350;200\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1783155956);
INSERT INTO `ba_admin_log` VALUES (41, 1, 'admin', '/admin/live.Persona/edit', '人设管理-编辑', '{\"id\":\"3\",\"user_id\":\"0\",\"code\":\"persona_light_music\",\"name\":\"\\u8f7b\\u97f3\\u966a\\u4f34\",\"tags\":\"\\u8f7b\\u97f3\\u4e50,\\u653e\\u677e,\\u966a\\u4f34\",\"source_fields\":{\"age\":\"1\",\"eye\":\"0\",\"hair\":\"2\",\"type\":\"1\",\"hobby\":\"1,5\",\"profession\":\"1\",\"personality\":\"3\"},\"cover_url\":\"https:\\/\\/picsum.photos\\/seed\\/persona-music\\/320\\/320\",\"status\":\"2\",\"created_at\":\"2026-05-18 09:49:16\",\"updated_at\":\"2026-07-04 16:57:01\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1783157111);
INSERT INTO `ba_admin_log` VALUES (42, 1, 'admin', '/admin/live.Persona/edit', '人设管理-编辑', '{\"id\":\"2\",\"user_id\":\"0\",\"code\":\"persona_night_radio\",\"name\":\"\\u591c\\u804a\\u966a\\u4f34\",\"tags\":\"\\u6e29\\u67d4,\\u966a\\u4f34,\\u591c\\u95f4\",\"source_fields\":{\"age\":\"1\",\"eye\":\"0\",\"hair\":\"2\",\"type\":\"1\",\"hobby\":\"1,5\",\"profession\":\"1\",\"personality\":\"3\"},\"cover_url\":\"https:\\/\\/picsum.photos\\/seed\\/persona-night\\/320\\/320\",\"status\":\"2\",\"created_at\":\"2026-05-18 09:49:16\",\"updated_at\":\"2026-07-04 16:57:00\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1783157133);
INSERT INTO `ba_admin_log` VALUES (43, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"\\u4e13\\u6ce8,\\u5b66\\u4e60\",\"asset_ids\":[\"4\"],\"playlist_name\":\"\\u6e05\\u6668\\u81ea\\u4e60\\u76f4\\u64ad\\u95f44\\u64ad\\u5355\",\"id\":\"3\",\"room_no\":\"R1003\",\"title\":\"\\u6e05\\u6668\\u81ea\\u4e60\\u76f4\\u64ad\\u95f44\",\"subtitle\":\"\\u9002\\u5408\\u5207\\u540e\\u53f0\\u6302\\u673a\\u7684\\u4e13\\u6ce8\\u966a\\u4f34\\u6d41\",\"persona_id\":\"7\",\"room_type\":\"live\",\"status\":\"1\",\"cover_url\":\"https:\\/\\/picsum.photos\\/seed\\/live-room-3\\/720\\/1280\",\"sort\":\"100\",\"created_at\":\"2026-05-18 09:49:16\",\"updated_at\":\"2026-05-19 17:30:12\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1783157227);
INSERT INTO `ba_admin_log` VALUES (44, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"\\u4e13\\u6ce8,\\u5b66\\u4e60\",\"asset_ids\":[\"4\"],\"playlist_name\":\"\\u6e05\\u6668\\u81ea\\u4e60\\u76f4\\u64ad\\u95f44\\u64ad\\u5355\",\"id\":\"3\",\"room_no\":\"R1003\",\"title\":\"\\u6e05\\u6668\\u81ea\\u4e60\\u76f4\\u64ad\\u95f44\",\"subtitle\":\"\\u9002\\u5408\\u5207\\u540e\\u53f0\\u6302\\u673a\\u7684\\u4e13\\u6ce8\\u966a\\u4f34\\u6d41\",\"persona_id\":\"6\",\"room_type\":\"live\",\"status\":\"1\",\"cover_url\":\"https:\\/\\/picsum.photos\\/seed\\/live-room-3\\/720\\/1280\",\"sort\":\"100\",\"created_at\":\"2026-05-18 09:49:16\",\"updated_at\":\"2026-07-04 17:27:07\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1783157274);
INSERT INTO `ba_admin_log` VALUES (45, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"\\u4e13\\u6ce8,\\u5b66\\u4e60\",\"asset_ids\":[\"4\"],\"playlist_name\":\"\\u6e05\\u6668\\u81ea\\u4e60\\u76f4\\u64ad\\u95f44\\u64ad\\u5355\",\"id\":\"3\",\"room_no\":\"R1003\",\"title\":\"\\u6e05\\u6668\\u81ea\\u4e60\\u76f4\\u64ad\\u95f44\",\"subtitle\":\"\\u9002\\u5408\\u5207\\u540e\\u53f0\\u6302\\u673a\\u7684\\u4e13\\u6ce8\\u966a\\u4f34\\u6d41\",\"persona_id\":\"7\",\"room_type\":\"live\",\"status\":\"1\",\"cover_url\":\"https:\\/\\/picsum.photos\\/seed\\/live-room-3\\/720\\/1280\",\"sort\":\"100\",\"created_at\":\"2026-05-18 09:49:16\",\"updated_at\":\"2026-07-04 17:27:54\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1783157682);

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
) ENGINE = InnoDB AUTO_INCREMENT = 126 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '菜单和权限规则表' ROW_FORMAT = DYNAMIC;

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
INSERT INTO `ba_admin_rule` VALUES (90, 0, 'menu_dir', '直播运营', 'live', 'live', 'fa fa-video-camera', 'tab', '', 'Layout', 1, 'none', '直播后台运营菜单', 120, 1, 1779248396, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (91, 90, 'menu', '人设管理', 'live/persona', 'live/persona', 'fa fa-user-circle', 'tab', '', '/src/views/backend/live/persona/index.vue', 1, 'none', '直播人设配置', 119, 1, 1779248396, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (92, 91, 'button', '查看', 'live/persona/index', '', '', 'tab', '', '', 0, 'none', '', 10, 1, 1779085601, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (93, 91, 'button', '新增', 'live/persona/add', '', '', 'tab', '', '', 0, 'none', '', 9, 1, 1779085601, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (94, 91, 'button', '编辑', 'live/persona/edit', '', '', 'tab', '', '', 0, 'none', '', 8, 1, 1779085601, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (95, 91, 'button', '删除', 'live/persona/del', '', '', 'tab', '', '', 0, 'none', '', 7, 1, 1779085601, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (96, 91, 'button', '选择', 'live/persona/select', '', '', 'tab', '', '', 0, 'none', '', 6, 1, 1779085601, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (97, 90, 'menu', '素材管理', 'live/mediaAsset', 'live/mediaAsset', 'fa fa-film', 'tab', '', '/src/views/backend/live/mediaAsset/index.vue', 1, 'none', '直播素材池管理', 118, 1, 1779248396, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (98, 97, 'button', '查看', 'live/mediaAsset/index', '', '', 'tab', '', '', 0, 'none', '', 10, 1, 1779085601, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (99, 97, 'button', '新增', 'live/mediaAsset/add', '', '', 'tab', '', '', 0, 'none', '', 9, 1, 1779085601, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (100, 97, 'button', '编辑', 'live/mediaAsset/edit', '', '', 'tab', '', '', 0, 'none', '', 8, 1, 1779085601, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (101, 97, 'button', '删除', 'live/mediaAsset/del', '', '', 'tab', '', '', 0, 'none', '', 7, 1, 1779085601, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (102, 97, 'button', '选择', 'live/mediaAsset/select', '', '', 'tab', '', '', 0, 'none', '', 6, 1, 1779085601, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (103, 90, 'menu', '房间管理', 'live/room', 'live/room', 'fa fa-television', 'tab', '', '/src/views/backend/live/room/index.vue', 1, 'none', '直播房间与播单绑定', 117, 1, 1779248396, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (104, 103, 'button', '查看', 'live/room/index', '', '', 'tab', '', '', 0, 'none', '', 10, 1, 1779085601, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (105, 103, 'button', '新增', 'live/room/add', '', '', 'tab', '', '', 0, 'none', '', 9, 1, 1779085601, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (106, 103, 'button', '编辑', 'live/room/edit', '', '', 'tab', '', '', 0, 'none', '', 8, 1, 1779085601, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (107, 103, 'button', '删除', 'live/room/del', '', '', 'tab', '', '', 0, 'none', '', 7, 1, 1779085601, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (108, 103, 'button', '选择', 'live/room/select', '', '', 'tab', '', '', 0, 'none', '', 6, 1, 1779085601, 1779085601);
INSERT INTO `ba_admin_rule` VALUES (109, 90, 'menu', '礼物管理', 'live/gift', 'live/gift', 'fa fa-gift', 'tab', '', '/src/views/backend/live/gift/index.vue', 0, 'none', '直播礼物配置', 116, 1, 1779248396, 1779248396);
INSERT INTO `ba_admin_rule` VALUES (110, 109, 'button', '查看', 'live/gift/index', '', '', 'tab', '', '', 0, 'none', '', 10, 1, 1779248396, 1779248396);
INSERT INTO `ba_admin_rule` VALUES (111, 109, 'button', '新增', 'live/gift/add', '', '', 'tab', '', '', 0, 'none', '', 9, 1, 1779248396, 1779248396);
INSERT INTO `ba_admin_rule` VALUES (112, 109, 'button', '编辑', 'live/gift/edit', '', '', 'tab', '', '', 0, 'none', '', 8, 1, 1779248396, 1779248396);
INSERT INTO `ba_admin_rule` VALUES (113, 109, 'button', '删除', 'live/gift/del', '', '', 'tab', '', '', 0, 'none', '', 7, 1, 1779248396, 1779248396);
INSERT INTO `ba_admin_rule` VALUES (114, 109, 'button', '选择', 'live/gift/select', '', '', 'tab', '', '', 0, 'none', '', 6, 1, 1779248396, 1779248396);
INSERT INTO `ba_admin_rule` VALUES (115, 90, 'menu', '直播平台用户', 'user/liveUser', 'live/liveUser', 'fa fa-users', 'tab', '', '/src/views/backend/user/liveUser/index.vue', 0, 'none', '', 115, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (121, 44, 'menu', '短信服务配置', 'routine/smsConfig', 'routine/smsConfig', 'fa fa-message', 'tab', '', '/src/views/backend/routine/smsConfig/index.vue', 0, 'none', '', 4, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (122, 0, 'menu_dir', '直播数据', 'liveData', '/admin/liveData', 'fa fa-bar-chart', '', '', '', 0, 'none', '', 50, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (123, 122, 'menu', '收益明细', 'live/revenue', '/admin/live/revenue', 'fa fa-list-alt', '', '', 'live/revenue/index', 0, 'none', '', 1, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (124, 122, 'menu', '收入排行榜', 'live/leaderboard', '/admin/live/leaderboard', 'fa fa-trophy', '', '', 'live/leaderboard/index', 0, 'none', '', 2, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (125, 122, 'menu', '历史切片', 'live/replayClip', '/admin/live/replayClip', 'fa fa-video-camera', '', '', 'live/replayClip/index', 0, 'none', '', 3, 1, NULL, NULL);

-- ----------------------------
-- Table structure for ba_area
-- ----------------------------
DROP TABLE IF EXISTS `ba_area`;
CREATE TABLE `ba_area`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `pid` int(11) UNSIGNED NULL DEFAULT NULL COMMENT '父id',
  `shortname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '简称',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '名称',
  `mergename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '全称',
  `level` tinyint(4) UNSIGNED NULL DEFAULT NULL COMMENT '层级:1=省,2=市,3=区/县',
  `pinyin` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '拼音',
  `code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '长途区号',
  `zip` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '邮编',
  `first` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '首字母',
  `lng` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '经度',
  `lat` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '纬度',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `pid`(`pid` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '省份地区表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of ba_area
-- ----------------------------

-- ----------------------------
-- Table structure for ba_attachment
-- ----------------------------
DROP TABLE IF EXISTS `ba_attachment`;
CREATE TABLE `ba_attachment`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `topic` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '细目',
  `admin_id` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '上传管理员ID',
  `user_id` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '上传用户ID',
  `url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '物理路径',
  `width` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '宽度',
  `height` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '高度',
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '原始名称',
  `size` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '大小',
  `mimetype` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'mime类型',
  `quote` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '上传(引用)次数',
  `storage` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '存储方式',
  `sha1` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'sha1编码',
  `create_time` bigint(16) UNSIGNED NULL DEFAULT NULL COMMENT '创建时间',
  `last_upload_time` bigint(16) UNSIGNED NULL DEFAULT NULL COMMENT '最后上传时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 5 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '附件表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of ba_attachment
-- ----------------------------
INSERT INTO `ba_attachment` VALUES (1, 'live', 1, 0, '/storage/live/20260518/demo_live_asset0e2b8da6ffb71124ee7e28e25094fd6fcfda45ec.mp4', 0, 0, 'demo_live_asset_room_2.mp4', 5485935, 'video/mp4', 1, 'local', '0e2b8da6ffb71124ee7e28e25094fd6fcfda45ec', 1779096312, 1779096312);
INSERT INTO `ba_attachment` VALUES (2, 'ai', 0, 9, '/storage/ai/20260702/aiimageedit-1774431bc69c712377d1cb9d5b9d78f263e19afc039.png', 1302, 1208, 'aiimageedit-1778840506202.png', 2146360, 'image/png', 3, 'local', '4431bc69c712377d1cb9d5b9d78f263e19afc039', 1782928658, 1783154460);
INSERT INTO `ba_attachment` VALUES (3, 'ai', 0, 11, '/storage/ai/20260704/idlefish-msg-170fa9ab807607a1423118b4663652b447b4279af7.jpg', 316, 307, 'idlefish-msg-1781663477531.png.jpg', 14488, 'image/jpeg', 1, 'local', '0fa9ab807607a1423118b4663652b447b4279af7', 1783154491, 1783154491);
INSERT INTO `ba_attachment` VALUES (4, 'ai', 0, 11, '/storage/ai/20260704/idlefish-msg-177e42ed9da5222e1b3dabbdf033acdbc19495c749.jpg', 336, 300, 'idlefish-msg-1781663596861.png.jpg', 15418, 'image/jpeg', 1, 'local', '7e42ed9da5222e1b3dabbdf033acdbc19495c749', 1783154594, 1783154594);

-- ----------------------------
-- Table structure for ba_captcha
-- ----------------------------
DROP TABLE IF EXISTS `ba_captcha`;
CREATE TABLE `ba_captcha`  (
  `key` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '验证码Key',
  `code` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '验证码(加密后)',
  `captcha` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '验证码数据',
  `create_time` bigint(16) UNSIGNED NULL DEFAULT NULL COMMENT '创建时间',
  `expire_time` bigint(16) UNSIGNED NULL DEFAULT NULL COMMENT '过期时间',
  PRIMARY KEY (`key`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '验证码表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of ba_captcha
-- ----------------------------

-- ----------------------------
-- Table structure for ba_config
-- ----------------------------
DROP TABLE IF EXISTS `ba_config`;
CREATE TABLE `ba_config`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `name` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '变量名',
  `group` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '分组',
  `title` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '变量标题',
  `tip` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '变量描述',
  `type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '变量输入组件类型',
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '变量值',
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '字典数据',
  `rule` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '验证规则',
  `extend` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '扩展属性',
  `allow_del` tinyint(4) UNSIGNED NOT NULL DEFAULT 0 COMMENT '允许删除:0=否,1=是',
  `weigh` int(11) NOT NULL DEFAULT 0 COMMENT '权重',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `name`(`name` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 21 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '系统配置' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of ba_config
-- ----------------------------
INSERT INTO `ba_config` VALUES (1, 'config_group', 'basics', 'Config group', '', 'array', '[{\"key\":\"basics\",\"value\":\"Basics\"},{\"key\":\"mail\",\"value\":\"Mail\"},{\"key\":\"sms\",\"value\":\"SMS\"},{\"key\":\"config_quick_entrance\",\"value\":\"Config Quick entrance\"}]', NULL, 'required', '', 0, -1);
INSERT INTO `ba_config` VALUES (2, 'site_name', 'basics', 'Site Name', '', 'string', 'BuildAdmin', NULL, 'required', '', 0, 99);
INSERT INTO `ba_config` VALUES (3, 'record_number', 'basics', 'Record number', '域名备案号', 'string', '渝ICP备8888888号-1', NULL, '', '', 0, 0);
INSERT INTO `ba_config` VALUES (4, 'version', 'basics', 'Version number', '系统版本号', 'string', 'v1.0.0', NULL, 'required', '', 0, 0);
INSERT INTO `ba_config` VALUES (5, 'time_zone', 'basics', 'time zone', '', 'string', 'Asia/Shanghai', NULL, 'required', '', 0, 0);
INSERT INTO `ba_config` VALUES (6, 'no_access_ip', 'basics', 'No access ip', '禁止访问站点的ip列表,一行一个', 'textarea', NULL, NULL, '', '', 0, 0);
INSERT INTO `ba_config` VALUES (7, 'smtp_server', 'mail', 'smtp server', '', 'string', 'smtp.qq.com', NULL, '', '', 0, 9);
INSERT INTO `ba_config` VALUES (8, 'smtp_port', 'mail', 'smtp port', '', 'string', '465', NULL, '', '', 0, 8);
INSERT INTO `ba_config` VALUES (9, 'smtp_user', 'mail', 'smtp user', '', 'string', '', NULL, '', '', 0, 7);
INSERT INTO `ba_config` VALUES (10, 'smtp_pass', 'mail', 'smtp pass', '', 'string', '', NULL, '', '', 0, 6);
INSERT INTO `ba_config` VALUES (11, 'smtp_verification', 'mail', 'smtp verification', '', 'select', 'SSL', '{\"SSL\":\"SSL\",\"TLS\":\"TLS\"}', '', '', 0, 5);
INSERT INTO `ba_config` VALUES (12, 'smtp_sender_mail', 'mail', 'smtp sender mail', '', 'string', '', NULL, 'email', '', 0, 4);
INSERT INTO `ba_config` VALUES (13, 'config_quick_entrance', 'config_quick_entrance', 'Config Quick entrance', '', 'array', '[{\"key\":\"\\u6570\\u636e\\u56de\\u6536\\u89c4\\u5219\\u914d\\u7f6e\",\"value\":\"security\\/dataRecycle\"},{\"key\":\"\\u654f\\u611f\\u6570\\u636e\\u89c4\\u5219\\u914d\\u7f6e\",\"value\":\"security\\/sensitiveData\"}]', NULL, '', '', 0, 0);
INSERT INTO `ba_config` VALUES (14, 'backend_entrance', 'basics', 'Backend entrance', '', 'string', '/admin', NULL, 'required', '', 0, 1);
INSERT INTO `ba_config` VALUES (15, 'sms_api_url', 'sms', 'API接口地址', '短信服务商的API请求地址', 'string', '', '', '', '', 0, 10);
INSERT INTO `ba_config` VALUES (16, 'sms_api_key', 'sms', 'API密钥', '短信服务商提供的API Key（需加密存储）', 'string', '', '', '', '', 0, 9);
INSERT INTO `ba_config` VALUES (17, 'sms_sign_id', 'sms', '签名ID', '短信签名标识', 'string', '', '', '', '', 0, 8);
INSERT INTO `ba_config` VALUES (18, 'sms_template_id', 'sms', '模板ID', '短信模板编码', 'string', '', '', '', '', 0, 7);
INSERT INTO `ba_config` VALUES (19, 'sms_active_provider', 'sms', '激活渠道', '当前使用的短信渠道标识，留空使用默认', 'string', 'default', '', '', '', 0, 6);
INSERT INTO `ba_config` VALUES (20, 'sms_grayscale_providers', 'sms', '灰度切换配置', 'JSON格式，如{\"provider_a\":30,\"provider_b\":70}表示按百分比流量分配', 'textarea', '', '', '', '', 0, 5);

-- ----------------------------
-- Table structure for ba_crud_log
-- ----------------------------
DROP TABLE IF EXISTS `ba_crud_log`;
CREATE TABLE `ba_crud_log`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `table_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '数据表名',
  `comment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '注释',
  `table` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '数据表数据',
  `fields` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '字段数据',
  `sync` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '同步记录',
  `status` enum('delete','success','error','start') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'start' COMMENT '状态:delete=已删除,success=成功,error=失败,start=生成中',
  `connection` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '数据库连接配置标识',
  `create_time` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'CRUD记录表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of ba_crud_log
-- ----------------------------

-- ----------------------------
-- Table structure for ba_migrations
-- ----------------------------
DROP TABLE IF EXISTS `ba_migrations`;
CREATE TABLE `ba_migrations`  (
  `version` bigint(20) NOT NULL,
  `migration_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `breakpoint` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`version`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of ba_migrations
-- ----------------------------
INSERT INTO `ba_migrations` VALUES (20230620180908, 'Install', '2026-05-16 22:46:13', '2026-05-16 22:46:15', 0);
INSERT INTO `ba_migrations` VALUES (20230620180916, 'InstallData', '2026-05-16 22:46:15', '2026-05-16 22:46:15', 0);
INSERT INTO `ba_migrations` VALUES (20230622221507, 'Version200', '2026-05-16 22:46:15', '2026-05-16 22:46:17', 0);
INSERT INTO `ba_migrations` VALUES (20230719211338, 'Version201', '2026-05-16 22:46:17', '2026-05-16 22:46:17', 0);
INSERT INTO `ba_migrations` VALUES (20230905060702, 'Version202', '2026-05-16 22:46:17', '2026-05-16 22:46:17', 0);
INSERT INTO `ba_migrations` VALUES (20231112093414, 'Version205', '2026-05-16 22:46:17', '2026-05-16 22:46:17', 0);
INSERT INTO `ba_migrations` VALUES (20231229043002, 'Version206', '2026-05-16 22:46:17', '2026-05-16 22:46:18', 0);
INSERT INTO `ba_migrations` VALUES (20250412134127, 'Version222', '2026-05-16 22:46:18', '2026-05-16 22:46:21', 0);

-- ----------------------------
-- Table structure for ba_security_data_recycle
-- ----------------------------
DROP TABLE IF EXISTS `ba_security_data_recycle`;
CREATE TABLE `ba_security_data_recycle`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '规则名称',
  `controller` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '控制器',
  `controller_as` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '控制器别名',
  `data_table` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '对应数据表',
  `connection` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '数据库连接配置标识',
  `primary_key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '数据表主键',
  `status` tinyint(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态:0=禁用,1=启用',
  `update_time` bigint(16) UNSIGNED NULL DEFAULT NULL COMMENT '更新时间',
  `create_time` bigint(16) UNSIGNED NULL DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 7 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '回收规则表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of ba_security_data_recycle
-- ----------------------------
INSERT INTO `ba_security_data_recycle` VALUES (1, '管理员', 'auth/Admin.php', 'auth/admin', 'admin', '', 'id', 1, 1778942775, 1778942775);
INSERT INTO `ba_security_data_recycle` VALUES (2, '管理员日志', 'auth/AdminLog.php', 'auth/adminlog', 'admin_log', '', 'id', 1, 1778942775, 1778942775);
INSERT INTO `ba_security_data_recycle` VALUES (3, '菜单规则', 'auth/Menu.php', 'auth/menu', 'menu_rule', '', 'id', 1, 1778942775, 1778942775);
INSERT INTO `ba_security_data_recycle` VALUES (4, '系统配置项', 'routine/Config.php', 'routine/config', 'config', '', 'id', 1, 1778942775, 1778942775);
INSERT INTO `ba_security_data_recycle` VALUES (5, '会员', 'user/User.php', 'user/user', 'user', '', 'id', 1, 1778942775, 1778942775);
INSERT INTO `ba_security_data_recycle` VALUES (6, '数据回收规则', 'security/DataRecycle.php', 'security/datarecycle', 'security_data_recycle', '', 'id', 1, 1778942775, 1778942775);

-- ----------------------------
-- Table structure for ba_security_data_recycle_log
-- ----------------------------
DROP TABLE IF EXISTS `ba_security_data_recycle_log`;
CREATE TABLE `ba_security_data_recycle_log`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `admin_id` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作管理员',
  `recycle_id` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '回收规则ID',
  `data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '回收的数据',
  `data_table` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '数据表',
  `connection` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '数据库连接配置标识',
  `primary_key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '数据表主键',
  `is_restore` tinyint(4) UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否已还原:0=否,1=是',
  `ip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '操作者IP',
  `useragent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'User-Agent',
  `create_time` bigint(16) UNSIGNED NULL DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '数据回收记录表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of ba_security_data_recycle_log
-- ----------------------------

-- ----------------------------
-- Table structure for ba_security_sensitive_data
-- ----------------------------
DROP TABLE IF EXISTS `ba_security_sensitive_data`;
CREATE TABLE `ba_security_sensitive_data`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '规则名称',
  `controller` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '控制器',
  `controller_as` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '控制器别名',
  `data_table` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '对应数据表',
  `connection` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '数据库连接配置标识',
  `primary_key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '数据表主键',
  `data_fields` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '敏感数据字段',
  `status` tinyint(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态:0=禁用,1=启用',
  `update_time` bigint(16) UNSIGNED NULL DEFAULT NULL COMMENT '更新时间',
  `create_time` bigint(16) UNSIGNED NULL DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '敏感数据规则表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of ba_security_sensitive_data
-- ----------------------------
INSERT INTO `ba_security_sensitive_data` VALUES (1, '管理员数据', 'auth/Admin.php', 'auth/admin', 'admin', '', 'id', '{\"username\":\"用户名\",\"mobile\":\"手机\",\"password\":\"密码\",\"status\":\"状态\"}', 1, 1778942775, 1778942775);
INSERT INTO `ba_security_sensitive_data` VALUES (2, '会员数据', 'user/User.php', 'user/user', 'user', '', 'id', '{\"username\":\"用户名\",\"mobile\":\"手机号\",\"password\":\"密码\",\"status\":\"状态\",\"email\":\"邮箱地址\"}', 1, 1778942775, 1778942775);
INSERT INTO `ba_security_sensitive_data` VALUES (3, '管理员权限', 'auth/Group.php', 'auth/group', 'admin_group', '', 'id', '{\"rules\":\"权限规则ID\"}', 1, 1778942775, 1778942775);

-- ----------------------------
-- Table structure for ba_security_sensitive_data_log
-- ----------------------------
DROP TABLE IF EXISTS `ba_security_sensitive_data_log`;
CREATE TABLE `ba_security_sensitive_data_log`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `admin_id` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作管理员',
  `sensitive_id` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '敏感数据规则ID',
  `data_table` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '数据表',
  `connection` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '数据库连接配置标识',
  `primary_key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '数据表主键',
  `data_field` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '被修改字段',
  `data_comment` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '被修改项',
  `id_value` int(11) NOT NULL DEFAULT 0 COMMENT '被修改项主键值',
  `before` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '修改前',
  `after` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '修改后',
  `ip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '操作者IP',
  `useragent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'User-Agent',
  `is_rollback` tinyint(4) UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否已回滚:0=否,1=是',
  `create_time` bigint(16) UNSIGNED NULL DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '敏感数据修改记录' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of ba_security_sensitive_data_log
-- ----------------------------

-- ----------------------------
-- Table structure for ba_test_build
-- ----------------------------
DROP TABLE IF EXISTS `ba_test_build`;
CREATE TABLE `ba_test_build`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `title` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '标题',
  `keyword_rows` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '关键词',
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '内容',
  `views` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '浏览量',
  `likes` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '有帮助数',
  `dislikes` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '无帮助数',
  `note_textarea` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '备注',
  `status` tinyint(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态:0=禁用,1=启用',
  `weigh` int(11) NOT NULL DEFAULT 0 COMMENT '权重',
  `update_time` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '更新时间',
  `create_time` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '知识库表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of ba_test_build
-- ----------------------------

-- ----------------------------
-- Table structure for ba_token
-- ----------------------------
DROP TABLE IF EXISTS `ba_token`;
CREATE TABLE `ba_token`  (
  `token` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Token',
  `type` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '类型',
  `user_id` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户ID',
  `create_time` bigint(16) UNSIGNED NULL DEFAULT NULL COMMENT '创建时间',
  `expire_time` bigint(16) UNSIGNED NULL DEFAULT NULL COMMENT '过期时间',
  PRIMARY KEY (`token`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '用户Token表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of ba_token
-- ----------------------------
INSERT INTO `ba_token` VALUES ('2e674dd580b2cc7981f6e0b08ee9e25160024cc3', 'admin', 1, 1783155956, 1783415156);
INSERT INTO `ba_token` VALUES ('a8d81df5dc00df0a5a3bfa1358694b9c6f71c307', 'admin', 1, 1783154705, 1783413905);

-- ----------------------------
-- Table structure for ba_user
-- ----------------------------
DROP TABLE IF EXISTS `ba_user`;
CREATE TABLE `ba_user`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `group_id` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '分组ID',
  `username` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '用户名',
  `nickname` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '昵称',
  `email` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '邮箱',
  `mobile` varchar(11) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '手机',
  `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '头像',
  `gender` tinyint(4) UNSIGNED NOT NULL DEFAULT 0 COMMENT '性别:0=未知,1=男,2=女',
  `birthday` date NULL DEFAULT NULL COMMENT '生日',
  `money` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '余额',
  `score` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '积分',
  `last_login_time` bigint(16) UNSIGNED NULL DEFAULT NULL COMMENT '上次登录时间',
  `last_login_ip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '上次登录IP',
  `login_failure` tinyint(4) UNSIGNED NOT NULL DEFAULT 0 COMMENT '登录失败次数',
  `join_ip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '加入IP',
  `join_time` bigint(16) UNSIGNED NULL DEFAULT NULL COMMENT '加入时间',
  `motto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '签名',
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '密码',
  `salt` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '密码盐（废弃待删）',
  `status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '状态:enable=启用,disable=禁用',
  `update_time` bigint(16) UNSIGNED NULL DEFAULT NULL COMMENT '更新时间',
  `create_time` bigint(16) UNSIGNED NULL DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `username`(`username` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '会员表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of ba_user
-- ----------------------------
INSERT INTO `ba_user` VALUES (1, 1, 'user', 'User', '18888888888@qq.com', '18888888888', '', 2, '2026-05-16', 0, 0, NULL, '', 0, '', NULL, '', '$2y$10$.s832t0YRMMmx.82qTHOKevquvBe/4KpzFFop47A0y9Z3KAIhro1.', '', 'enable', 1778942775, 1778942775);

-- ----------------------------
-- Table structure for ba_user_group
-- ----------------------------
DROP TABLE IF EXISTS `ba_user_group`;
CREATE TABLE `ba_user_group`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '组名',
  `rules` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '权限节点',
  `status` tinyint(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态:0=禁用,1=启用',
  `update_time` bigint(16) UNSIGNED NULL DEFAULT NULL COMMENT '更新时间',
  `create_time` bigint(16) UNSIGNED NULL DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '会员组表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of ba_user_group
-- ----------------------------
INSERT INTO `ba_user_group` VALUES (1, '默认分组', '*', 1, 1778942775, 1778942775);

-- ----------------------------
-- Table structure for ba_user_money_log
-- ----------------------------
DROP TABLE IF EXISTS `ba_user_money_log`;
CREATE TABLE `ba_user_money_log`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '会员ID',
  `money` int(11) NOT NULL DEFAULT 0 COMMENT '变更余额',
  `before` int(11) NOT NULL DEFAULT 0 COMMENT '变更前余额',
  `after` int(11) NOT NULL DEFAULT 0 COMMENT '变更后余额',
  `memo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` bigint(16) UNSIGNED NULL DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '会员余额变动表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of ba_user_money_log
-- ----------------------------

-- ----------------------------
-- Table structure for ba_user_rule
-- ----------------------------
DROP TABLE IF EXISTS `ba_user_rule`;
CREATE TABLE `ba_user_rule`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `pid` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '上级菜单',
  `type` enum('route','menu_dir','menu','nav_user_menu','nav','button') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'menu' COMMENT '类型:route=路由,menu_dir=菜单目录,menu=菜单项,nav_user_menu=顶栏会员菜单下拉项,nav=顶栏菜单项,button=页面按钮',
  `title` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '标题',
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '规则名称',
  `path` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '路由路径',
  `icon` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '图标',
  `menu_type` enum('tab','link','iframe') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tab' COMMENT '菜单类型:tab=选项卡,link=链接,iframe=Iframe',
  `url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Url',
  `component` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '组件路径',
  `no_login_valid` tinyint(4) UNSIGNED NOT NULL DEFAULT 0 COMMENT '未登录有效:0=否,1=是',
  `extend` enum('none','add_rules_only','add_menu_only') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none' COMMENT '扩展属性:none=无,add_rules_only=只添加为路由,add_menu_only=只添加为菜单',
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '备注',
  `weigh` int(11) NOT NULL DEFAULT 0 COMMENT '权重',
  `status` tinyint(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态:0=禁用,1=启用',
  `update_time` bigint(16) UNSIGNED NULL DEFAULT NULL COMMENT '更新时间',
  `create_time` bigint(16) UNSIGNED NULL DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `pid`(`pid` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 7 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '会员菜单权限规则表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of ba_user_rule
-- ----------------------------
INSERT INTO `ba_user_rule` VALUES (1, 0, 'menu_dir', '我的账户', 'account', 'account', 'fa fa-user-circle', 'tab', '', '', 0, 'none', '', 98, 1, 1778942775, 1778942775);
INSERT INTO `ba_user_rule` VALUES (2, 1, 'menu', '账户概览', 'account/overview', 'account/overview', 'fa fa-home', 'tab', '', '/src/views/frontend/user/account/overview.vue', 0, 'none', '', 99, 1, 1778942775, 1778942775);
INSERT INTO `ba_user_rule` VALUES (3, 1, 'menu', '个人资料', 'account/profile', 'account/profile', 'fa fa-user-circle-o', 'tab', '', '/src/views/frontend/user/account/profile.vue', 0, 'none', '', 98, 1, 1778942775, 1778942775);
INSERT INTO `ba_user_rule` VALUES (4, 1, 'menu', '修改密码', 'account/changePassword', 'account/changePassword', 'fa fa-shield', 'tab', '', '/src/views/frontend/user/account/changePassword.vue', 0, 'none', '', 97, 1, 1778942775, 1778942775);
INSERT INTO `ba_user_rule` VALUES (5, 1, 'menu', '积分记录', 'account/integral', 'account/integral', 'fa fa-tag', 'tab', '', '/src/views/frontend/user/account/integral.vue', 0, 'none', '', 96, 1, 1778942775, 1778942775);
INSERT INTO `ba_user_rule` VALUES (6, 1, 'menu', '余额记录', 'account/balance', 'account/balance', 'fa fa-money', 'tab', '', '/src/views/frontend/user/account/balance.vue', 0, 'none', '', 95, 1, 1778942775, 1778942775);

-- ----------------------------
-- Table structure for ba_user_score_log
-- ----------------------------
DROP TABLE IF EXISTS `ba_user_score_log`;
CREATE TABLE `ba_user_score_log`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '会员ID',
  `score` int(11) NOT NULL DEFAULT 0 COMMENT '变更积分',
  `before` int(11) NOT NULL DEFAULT 0 COMMENT '变更前积分',
  `after` int(11) NOT NULL DEFAULT 0 COMMENT '变更后积分',
  `memo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` bigint(16) UNSIGNED NULL DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '会员积分变动表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of ba_user_score_log
-- ----------------------------

-- ----------------------------
-- Table structure for lp_ai_task
-- ----------------------------
DROP TABLE IF EXISTS `lp_ai_task`;
CREATE TABLE `lp_ai_task`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `task_no` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '任务号',
  `room_id` bigint(20) UNSIGNED NOT NULL COMMENT '房间ID',
  `task_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '任务类型:interaction_async interaction_realtime',
  `priority` int(11) NOT NULL DEFAULT 0 COMMENT '优先级',
  `source_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '来源类型:chat gift system',
  `source_ref_id` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '来源业务ID',
  `persona_id` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '人设ID',
  `content` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '任务文本',
  `callback_mode` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'file' COMMENT '回调模式:file stream',
  `status` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '任务状态',
  `worker_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '处理客户端ID',
  `result_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '结果类型:video_file live_stream',
  `video_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '视频URL',
  `cover_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '封面URL',
  `stream_alias` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '互动流别名',
  `duration_sec` int(11) NOT NULL DEFAULT 0 COMMENT '时长',
  `deadline_at` datetime NULL DEFAULT NULL COMMENT '截止时间',
  `accepted_at` datetime NULL DEFAULT NULL COMMENT '接单时间',
  `finished_at` datetime NULL DEFAULT NULL COMMENT '完成时间',
  `failed_at` datetime NULL DEFAULT NULL COMMENT '失败时间',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_task_no`(`task_no` ASC) USING BTREE,
  INDEX `idx_room_status_priority`(`room_id` ASC, `status` ASC, `priority` ASC) USING BTREE,
  INDEX `idx_worker_id`(`worker_id` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'AI任务' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_ai_task
-- ----------------------------

-- ----------------------------
-- Table structure for lp_ai_task_log
-- ----------------------------
DROP TABLE IF EXISTS `lp_ai_task_log`;
CREATE TABLE `lp_ai_task_log`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `task_id` bigint(20) UNSIGNED NOT NULL COMMENT '任务ID',
  `event_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '事件类型',
  `payload_json` json NULL COMMENT '事件数据',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_task_created`(`task_id` ASC, `created_at` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'AI任务日志' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_ai_task_log
-- ----------------------------

-- ----------------------------
-- Table structure for lp_asset_exchange_rate
-- ----------------------------
DROP TABLE IF EXISTS `lp_asset_exchange_rate`;
CREATE TABLE `lp_asset_exchange_rate`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `pay_channel` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '支付渠道',
  `chain_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '链类型',
  `pay_amount` decimal(18, 8) NOT NULL COMMENT '支付金额',
  `diamond_amount` decimal(18, 2) NOT NULL COMMENT '到账钻石',
  `rate_snapshot` decimal(18, 8) NOT NULL COMMENT '汇率快照',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_channel_chain_created`(`pay_channel` ASC, `chain_type` ASC, `created_at` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '充值汇率快照' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_asset_exchange_rate
-- ----------------------------

-- ----------------------------
-- Table structure for lp_chat_message
-- ----------------------------
DROP TABLE IF EXISTS `lp_chat_message`;
CREATE TABLE `lp_chat_message`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `room_id` bigint(20) UNSIGNED NOT NULL COMMENT '房间ID',
  `user_id` bigint(20) UNSIGNED NOT NULL COMMENT '用户ID',
  `message_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text' COMMENT '消息类型',
  `content` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '消息内容',
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '状态:0屏蔽 1正常',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_room_created`(`room_id` ASC, `created_at` ASC) USING BTREE,
  INDEX `idx_user_created`(`user_id` ASC, `created_at` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 29 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '聊天消息' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_chat_message
-- ----------------------------
INSERT INTO `lp_chat_message` VALUES (1, 1, 3, 'text', 'hello_ws_1778984184', 1, '2026-05-17 10:16:24');
INSERT INTO `lp_chat_message` VALUES (2, 1, 3, 'text', '这是敏感词测试', 0, '2026-05-17 10:25:41');
INSERT INTO `lp_chat_message` VALUES (3, 1, 3, 'text', '这是敏感词测试', 0, '2026-05-17 10:26:58');
INSERT INTO `lp_chat_message` VALUES (4, 1, 0, 'text', 'sdd', 1, '2026-05-20 11:28:32');
INSERT INTO `lp_chat_message` VALUES (5, 1, 0, 'text', '2', 1, '2026-05-20 11:28:47');
INSERT INTO `lp_chat_message` VALUES (6, 1, 0, 'text', 'sda', 1, '2026-05-20 11:28:53');
INSERT INTO `lp_chat_message` VALUES (7, 1, 0, 'text', 'ddd', 1, '2026-05-20 11:29:02');
INSERT INTO `lp_chat_message` VALUES (8, 2, 0, 'text', '123', 1, '2026-05-20 11:41:51');
INSERT INTO `lp_chat_message` VALUES (9, 1, 0, 'text', '4', 1, '2026-05-20 11:47:12');
INSERT INTO `lp_chat_message` VALUES (10, 1, 0, 'text', '5', 1, '2026-05-20 11:47:36');
INSERT INTO `lp_chat_message` VALUES (11, 2, 0, 'text', '4', 1, '2026-05-20 11:47:46');
INSERT INTO `lp_chat_message` VALUES (12, 2, 0, 'text', '23', 1, '2026-05-20 11:54:28');
INSERT INTO `lp_chat_message` VALUES (13, 2, 0, 'text', '4445', 1, '2026-05-20 11:54:30');
INSERT INTO `lp_chat_message` VALUES (14, 2, 0, 'text', '全微分', 1, '2026-05-20 11:54:33');
INSERT INTO `lp_chat_message` VALUES (15, 2, 0, 'text', '没了', 1, '2026-05-20 11:54:38');
INSERT INTO `lp_chat_message` VALUES (16, 3, 0, 'text', '213', 1, '2026-05-21 09:31:34');
INSERT INTO `lp_chat_message` VALUES (17, 3, 0, 'text', '51', 1, '2026-05-21 09:31:37');
INSERT INTO `lp_chat_message` VALUES (18, 2, 0, 'text', '31', 1, '2026-05-21 09:32:06');
INSERT INTO `lp_chat_message` VALUES (19, 3, 0, 'text', '31', 1, '2026-05-21 09:32:28');
INSERT INTO `lp_chat_message` VALUES (20, 1, 0, 'text', '24', 1, '2026-05-28 11:14:26');
INSERT INTO `lp_chat_message` VALUES (21, 1, 0, 'text', '36363', 1, '2026-05-28 11:14:28');
INSERT INTO `lp_chat_message` VALUES (22, 2, 0, 'text', '43242', 1, '2026-05-28 11:14:34');
INSERT INTO `lp_chat_message` VALUES (23, 3, 0, 'text', '3213', 1, '2026-05-28 11:16:00');
INSERT INTO `lp_chat_message` VALUES (24, 2, 0, 'text', '213', 1, '2026-05-28 18:24:28');
INSERT INTO `lp_chat_message` VALUES (25, 2, 0, 'text', '123', 1, '2026-05-28 18:24:40');
INSERT INTO `lp_chat_message` VALUES (26, 1, 0, 'text', '321', 1, '2026-05-28 18:40:57');
INSERT INTO `lp_chat_message` VALUES (27, 2, 0, 'text', 'we', 1, '2026-07-01 11:10:29');
INSERT INTO `lp_chat_message` VALUES (28, 2, 0, 'text', '123', 1, '2026-07-02 01:25:40');

-- ----------------------------
-- Table structure for lp_control_command_log
-- ----------------------------
DROP TABLE IF EXISTS `lp_control_command_log`;
CREATE TABLE `lp_control_command_log`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `room_id` bigint(20) UNSIGNED NOT NULL COMMENT '房间ID',
  `task_id` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '关联任务ID',
  `command_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '命令类型',
  `target_worker` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '目标执行端',
  `request_payload` json NULL COMMENT '请求数据',
  `ack_payload` json NULL COMMENT '响应数据',
  `status` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '状态',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_room_created`(`room_id` ASC, `created_at` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '控制命令日志' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_control_command_log
-- ----------------------------

-- ----------------------------
-- Table structure for lp_gift
-- ----------------------------
DROP TABLE IF EXISTS `lp_gift`;
CREATE TABLE `lp_gift`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `gift_code` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '礼物编码',
  `name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '礼物名称',
  `price_diamond` decimal(18, 2) NOT NULL COMMENT '钻石价格',
  `trigger_mode` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none' COMMENT '触发模式:none privilege interaction',
  `trigger_duration_sec` int(11) NOT NULL DEFAULT 0 COMMENT '触发时长秒',
  `effect_code` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '特效编码',
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '状态',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_gift_code`(`gift_code` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '礼物配置' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_gift
-- ----------------------------
INSERT INTO `lp_gift` VALUES (1, 'rose', '玫瑰', 10.00, 'none', 0, 'rose_effect', 1, '2026-05-17 00:01:21');
INSERT INTO `lp_gift` VALUES (2, 'vip_30s', '专属礼物', 199.00, 'privilege', 30, 'vip_effect', 1, '2026-05-17 00:01:21');

-- ----------------------------
-- Table structure for lp_gift_order
-- ----------------------------
DROP TABLE IF EXISTS `lp_gift_order`;
CREATE TABLE `lp_gift_order`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `order_no` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '订单号',
  `user_id` bigint(20) UNSIGNED NOT NULL COMMENT '用户ID',
  `room_id` bigint(20) UNSIGNED NOT NULL COMMENT '房间ID',
  `gift_id` bigint(20) UNSIGNED NOT NULL COMMENT '礼物ID',
  `quantity` int(11) NOT NULL DEFAULT 1 COMMENT '数量',
  `total_price` decimal(18, 2) NOT NULL COMMENT '总价',
  `trigger_task_id` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '触发的切流任务ID',
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '状态',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_gift_order_no`(`order_no` ASC) USING BTREE,
  INDEX `idx_room_created`(`room_id` ASC, `created_at` ASC) USING BTREE,
  INDEX `idx_user_created`(`user_id` ASC, `created_at` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 21 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '礼物订单' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_gift_order
-- ----------------------------
INSERT INTO `lp_gift_order` VALUES (1, 'G2026052109313845D05EF6', 0, 3, 1, 1, 10.00, NULL, 1, '2026-05-21 09:31:38');
INSERT INTO `lp_gift_order` VALUES (2, 'G20260521093139088C16AB', 0, 3, 2, 1, 199.00, NULL, 1, '2026-05-21 09:31:39');
INSERT INTO `lp_gift_order` VALUES (3, 'G20260521093140A2DCEA8C', 0, 3, 2, 1, 199.00, NULL, 1, '2026-05-21 09:31:40');
INSERT INTO `lp_gift_order` VALUES (4, 'G20260521093142E664B882', 0, 3, 1, 1, 10.00, NULL, 1, '2026-05-21 09:31:42');
INSERT INTO `lp_gift_order` VALUES (5, 'G202605210931439F532AB9', 0, 3, 2, 1, 199.00, NULL, 1, '2026-05-21 09:31:43');
INSERT INTO `lp_gift_order` VALUES (6, 'G20260521093247F2F0A615', 0, 3, 1, 1, 10.00, NULL, 1, '2026-05-21 09:32:47');
INSERT INTO `lp_gift_order` VALUES (7, 'G2026052109324708F54A35', 0, 3, 2, 1, 199.00, NULL, 1, '2026-05-21 09:32:47');
INSERT INTO `lp_gift_order` VALUES (8, 'G20260521125131D28C35D7', 0, 3, 2, 1, 199.00, NULL, 1, '2026-05-21 12:51:31');
INSERT INTO `lp_gift_order` VALUES (9, 'G20260521125136DC4477E3', 0, 3, 1, 1, 10.00, NULL, 1, '2026-05-21 12:51:36');
INSERT INTO `lp_gift_order` VALUES (10, 'G20260521125137E4208AB9', 0, 3, 1, 1, 10.00, NULL, 1, '2026-05-21 12:51:37');
INSERT INTO `lp_gift_order` VALUES (11, 'G20260521125138C8DA1943', 0, 3, 1, 1, 10.00, NULL, 1, '2026-05-21 12:51:38');
INSERT INTO `lp_gift_order` VALUES (12, 'G20260521125138C90E6881', 0, 3, 1, 1, 10.00, NULL, 1, '2026-05-21 12:51:38');
INSERT INTO `lp_gift_order` VALUES (13, 'G202605211251396E925D9A', 0, 3, 1, 1, 10.00, NULL, 1, '2026-05-21 12:51:39');
INSERT INTO `lp_gift_order` VALUES (14, 'G202605211251393EB3A4ED', 0, 3, 1, 1, 10.00, NULL, 1, '2026-05-21 12:51:39');
INSERT INTO `lp_gift_order` VALUES (15, 'G20260521125142E8E364E2', 0, 3, 2, 1, 199.00, NULL, 1, '2026-05-21 12:51:42');
INSERT INTO `lp_gift_order` VALUES (16, 'G202605211251443039A359', 0, 3, 2, 1, 199.00, NULL, 1, '2026-05-21 12:51:44');
INSERT INTO `lp_gift_order` VALUES (17, 'G20260521125150A04488E4', 0, 3, 2, 1, 199.00, NULL, 1, '2026-05-21 12:51:50');
INSERT INTO `lp_gift_order` VALUES (18, 'G20260521125200ED133E3A', 0, 1, 2, 1, 199.00, NULL, 1, '2026-05-21 12:52:00');
INSERT INTO `lp_gift_order` VALUES (19, 'G20260701110936B628C60B', 0, 2, 2, 1, 199.00, NULL, 1, '2026-07-01 11:09:36');
INSERT INTO `lp_gift_order` VALUES (20, 'G20260702012541B5CA929B', 0, 2, 1, 1, 10.00, NULL, 1, '2026-07-02 01:25:41');

-- ----------------------------
-- Table structure for lp_like_action_log
-- ----------------------------
DROP TABLE IF EXISTS `lp_like_action_log`;
CREATE TABLE `lp_like_action_log`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `room_id` bigint(20) UNSIGNED NOT NULL COMMENT '房间ID',
  `user_id` bigint(20) UNSIGNED NOT NULL COMMENT '用户ID',
  `action_count` int(11) NOT NULL DEFAULT 1 COMMENT '点赞次数',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_room_created`(`room_id` ASC, `created_at` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '点赞日志' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_like_action_log
-- ----------------------------

-- ----------------------------
-- Table structure for lp_media_asset
-- ----------------------------
DROP TABLE IF EXISTS `lp_media_asset`;
CREATE TABLE `lp_media_asset`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `asset_code` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '素材编码',
  `asset_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '素材类型:video image audio subtitle',
  `scene_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '场景类型:public privilege interaction cover',
  `title` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '标题',
  `file_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件地址',
  `duration_ms` int(11) NOT NULL DEFAULT 0 COMMENT '时长ms',
  `checksum` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '校验值',
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '状态',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_asset_code`(`asset_code` ASC) USING BTREE,
  INDEX `idx_scene_type_status`(`scene_type` ASC, `status` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 6 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '素材池' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_media_asset
-- ----------------------------
INSERT INTO `lp_media_asset` VALUES (1, 'demo_live_asset', 'video', 'public', '默认直播演示素材', 'D:\\ever\\douyin\\douyin\\services\\channel-worker/runtime/assets/demo_live_asset.mp4', 5000, '', 1, '2026-05-17 23:11:55');
INSERT INTO `lp_media_asset` VALUES (2, 'demo_live_asset_room_1', 'video', 'public', '深夜情感电台演示素材', 'D:\\ever\\douyin\\douyin\\services\\channel-worker/runtime/assets/demo_live_asset_room_1.mp4', 5000, '', 1, '2026-05-18 09:56:39');
INSERT INTO `lp_media_asset` VALUES (3, 'demo_live_asset_room_2', 'video', 'public', '午后轻音乐直播间演示素材', 'D:\\ever\\douyin\\douyin\\services\\channel-worker/runtime/assets/demo_live_asset_room_2.mp4', 10000, '', 1, '2026-05-18 09:56:39');
INSERT INTO `lp_media_asset` VALUES (4, 'demo_live_asset_room_3', 'video', 'public', '清晨自习直播间演示素材', 'D:\\ever\\douyin\\douyin\\services\\channel-worker/runtime/assets/demo_live_asset_room_3.mp4', 15000, '', 1, '2026-05-18 09:56:39');
INSERT INTO `lp_media_asset` VALUES (5, 'e2e_asset_20260518_1', 'video', 'public', 'E2E联调素材1', '/storage/live/20260518/demo_live_asset0e2b8da6ffb71124ee7e28e25094fd6fcfda45ec.mp4', 10000, '0e2b8da6ffb71124ee7e28e25094fd6fcfda45ec', 1, '2026-05-18 17:27:17');

-- ----------------------------
-- Table structure for lp_payment_callback_log
-- ----------------------------
DROP TABLE IF EXISTS `lp_payment_callback_log`;
CREATE TABLE `lp_payment_callback_log`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `order_no` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '订单号',
  `gateway` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '网关',
  `payload_hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '回调指纹',
  `raw_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '回调原文',
  `verify_status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '验签状态:0待处理 1成功 2失败',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_gateway_payload_hash`(`gateway` ASC, `payload_hash` ASC) USING BTREE,
  INDEX `idx_order_no`(`order_no` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '支付回调日志' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_payment_callback_log
-- ----------------------------

-- ----------------------------
-- Table structure for lp_persona
-- ----------------------------
DROP TABLE IF EXISTS `lp_persona`;
CREATE TABLE `lp_persona`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '所属用户ID(关联lp_user.id)',
  `code` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '人设编码',
  `name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '人设名称',
  `tags` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '标签,逗号分隔',
  `source_fields` json NULL COMMENT 'AI角色原始属性(type/age/eye/hair/body/breast/hip/personality/profession/hobby/relation/clothing)',
  `cover_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '封面',
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '状态',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_persona_code`(`code` ASC) USING BTREE,
  INDEX `idx_user_id`(`user_id` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 8 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '数字人人设' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_persona
-- ----------------------------
INSERT INTO `lp_persona` VALUES (1, 11, 'persona_demo', '夜聊陪伴', '温柔,陪伴,夜间', '{\"age\": \"1\", \"eye\": \"0\", \"hair\": \"2\", \"type\": \"1\", \"hobby\": \"1,5\", \"profession\": \"1\", \"personality\": \"3\"}', 'https://cdn.example.com/persona/night.jpg', 2, '2026-05-17 09:41:46', '2026-07-04 17:39:09');
INSERT INTO `lp_persona` VALUES (2, 11, 'persona_night_radio', '夜聊陪伴', '温柔,陪伴,夜间', '{\"age\": \"1\", \"eye\": \"0\", \"hair\": \"2\", \"type\": \"1\", \"hobby\": \"1,5\", \"profession\": \"1\", \"personality\": \"3\"}', 'https://picsum.photos/seed/persona-night/320/320', 2, '2026-05-18 09:49:16', '2026-07-04 17:39:07');
INSERT INTO `lp_persona` VALUES (3, 11, 'persona_light_music', '轻音陪伴', '轻音乐,放松,陪伴', '{\"age\": \"1\", \"eye\": \"0\", \"hair\": \"2\", \"type\": \"1\", \"hobby\": \"1,5\", \"profession\": \"1\", \"personality\": \"3\"}', 'https://picsum.photos/seed/persona-music/320/320', 2, '2026-05-18 09:49:16', '2026-07-04 17:39:06');
INSERT INTO `lp_persona` VALUES (4, 11, 'persona_focus_study', '专注搭子', '学习,专注,清晨', '{\"age\": \"1\", \"eye\": \"0\", \"hair\": \"2\", \"type\": \"1\", \"hobby\": \"1,5\", \"profession\": \"1\", \"personality\": \"3\"}', 'https://picsum.photos/seed/persona-study/320/320', 1, '2026-05-18 09:49:16', '2026-07-04 17:39:05');
INSERT INTO `lp_persona` VALUES (5, 8, 'P202607020129318550', 'Test AI Girl', '', '{\"age\": \"1\", \"eye\": \"0\", \"hair\": \"2\", \"type\": \"1\", \"hobby\": \"1,5\", \"profession\": \"1\", \"personality\": \"3\"}', 'https://example.com/avatar.jpg', 1, '2026-07-02 01:29:31', '2026-07-02 01:29:31');
INSERT INTO `lp_persona` VALUES (6, 9, 'P202607020158464287', 'eva', '', '{\"age\": \"1\", \"eye\": \"3\", \"hip\": \"3\", \"body\": \"1\", \"hair\": \"1\", \"race\": \"2\", \"type\": \"1\", \"hobby\": \"6,7\", \"breast\": \"5\", \"clothing\": \"5\", \"relation\": \"6\", \"hairstyle\": \"7\", \"profession\": \"26\", \"personality\": \"1\"}', '/storage/ai/20260702/aiimageedit-1774431bc69c712377d1cb9d5b9d78f263e19afc039.png', 1, '2026-07-02 01:58:46', '2026-07-04 17:34:42');
INSERT INTO `lp_persona` VALUES (7, 11, 'P202607041643171411', 'eeemmmm', '', '{\"age\": \"1\", \"eye\": \"2\", \"hip\": \"3\", \"body\": \"2\", \"hair\": \"4\", \"race\": \"1\", \"type\": \"1\", \"hobby\": \"10\", \"breast\": \"2\", \"clothing\": \"19\", \"relation\": \"2\", \"hairstyle\": \"3\", \"profession\": \"1\", \"personality\": \"1\"}', 'http://127.0.0.1:8000/storage/ai/20260704/idlefish-msg-177e42ed9da5222e1b3dabbdf033acdbc19495c749.jpg', 2, '2026-07-04 16:43:17', '2026-07-04 17:34:42');

-- ----------------------------
-- Table structure for lp_playlist_template
-- ----------------------------
DROP TABLE IF EXISTS `lp_playlist_template`;
CREATE TABLE `lp_playlist_template`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `template_code` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '模板编码',
  `name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '模板名称',
  `mode` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '模板模式:public privilege backup',
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '状态',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_playlist_template_code`(`template_code` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 6 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '播单模板' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_playlist_template
-- ----------------------------
INSERT INTO `lp_playlist_template` VALUES (1, 'playlist_demo', '默认播单', 'public', 1, '2026-05-17 09:41:46');
INSERT INTO `lp_playlist_template` VALUES (2, 'playlist_public_demo_room_1', '深夜情感电台2播单', 'public', 1, '2026-05-18 09:56:39');
INSERT INTO `lp_playlist_template` VALUES (3, 'playlist_public_demo_room_2', '午后轻音乐直播间3播单', 'public', 1, '2026-05-18 09:56:39');
INSERT INTO `lp_playlist_template` VALUES (4, 'playlist_public_demo_room_3', '清晨自习直播间4播单', 'public', 1, '2026-05-18 09:56:39');
INSERT INTO `lp_playlist_template` VALUES (5, 'room_playlist_4', 'E2E联调测试房间播单', 'public', 1, '2026-05-18 17:29:54');

-- ----------------------------
-- Table structure for lp_playlist_template_item
-- ----------------------------
DROP TABLE IF EXISTS `lp_playlist_template_item`;
CREATE TABLE `lp_playlist_template_item`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `template_id` bigint(20) UNSIGNED NOT NULL COMMENT '播单模板ID',
  `asset_id` bigint(20) UNSIGNED NOT NULL COMMENT '素材ID',
  `seq` int(11) NOT NULL DEFAULT 0 COMMENT '顺序',
  `loop_count` int(11) NOT NULL DEFAULT 1 COMMENT '循环次数',
  `weight` int(11) NOT NULL DEFAULT 1 COMMENT '权重',
  `start_offset_ms` int(11) NOT NULL DEFAULT 0 COMMENT '起始偏移ms',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_template_seq`(`template_id` ASC, `seq` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 17 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '播单模板项' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_playlist_template_item
-- ----------------------------
INSERT INTO `lp_playlist_template_item` VALUES (1, 1, 1, 1, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (10, 2, 2, 1, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (11, 3, 3, 1, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (13, 5, 5, 1, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (16, 4, 4, 1, 1, 1, 0);

-- ----------------------------
-- Table structure for lp_recharge_order
-- ----------------------------
DROP TABLE IF EXISTS `lp_recharge_order`;
CREATE TABLE `lp_recharge_order`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `order_no` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '充值订单号',
  `user_id` bigint(20) UNSIGNED NOT NULL COMMENT '用户ID',
  `pay_channel` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '支付渠道:usdt_trc20',
  `chain_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TRC20' COMMENT '链类型',
  `pay_amount` decimal(18, 8) NOT NULL COMMENT '支付金额',
  `diamond_amount` decimal(18, 2) NOT NULL COMMENT '到账钻石数',
  `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '状态:0待支付 1已支付 2已过期 3关闭',
  `expire_at` datetime NULL DEFAULT NULL COMMENT '过期时间',
  `paid_at` datetime NULL DEFAULT NULL COMMENT '支付时间',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_recharge_order_no`(`order_no` ASC) USING BTREE,
  INDEX `idx_user_status`(`user_id` ASC, `status` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '充值订单' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_recharge_order
-- ----------------------------

-- ----------------------------
-- Table structure for lp_replay_clip
-- ----------------------------
DROP TABLE IF EXISTS `lp_replay_clip`;
CREATE TABLE `lp_replay_clip`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `persona_id` bigint(20) UNSIGNED NOT NULL COMMENT '关联角色ID',
  `room_id` bigint(20) UNSIGNED NOT NULL COMMENT '关联房间ID',
  `title` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '标题',
  `video_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '视频路径',
  `cover_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '封面图',
  `duration` int(11) NOT NULL DEFAULT 0 COMMENT '时长(秒)',
  `live_date` date NOT NULL COMMENT '直播日期',
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '状态:0下架 1上架',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_persona_id`(`persona_id` ASC) USING BTREE,
  INDEX `idx_room_id`(`room_id` ASC) USING BTREE,
  INDEX `idx_live_date`(`live_date` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '历史切片' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_replay_clip
-- ----------------------------

-- ----------------------------
-- Table structure for lp_room
-- ----------------------------
DROP TABLE IF EXISTS `lp_room`;
CREATE TABLE `lp_room`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `room_no` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '房间编号',
  `title` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '标题',
  `subtitle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '副标题',
  `persona_id` bigint(20) UNSIGNED NOT NULL COMMENT '人设ID',
  `room_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'live' COMMENT '房间类型',
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '状态:0关闭 1启用 2维护',
  `cover_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '封面',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序值',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_room_no`(`room_no` ASC) USING BTREE,
  INDEX `idx_persona_id`(`persona_id` ASC) USING BTREE,
  INDEX `idx_status_sort`(`status` ASC, `sort` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 5 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '直播房间' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_room
-- ----------------------------
INSERT INTO `lp_room` VALUES (1, 'R1001', '深夜情感电台2', '陪你聊天到天亮', 2, 'live', 1, 'https://picsum.photos/seed/live-room-1/720/1280', 120, '2026-05-17 09:41:46', '2026-05-19 17:30:04');
INSERT INTO `lp_room` VALUES (2, 'R1002', '午后轻音乐直播间3', '循环播放舒缓歌单和聊天互动', 3, 'live', 1, 'https://picsum.photos/seed/live-room-2/720/1280', 110, '2026-05-18 09:49:16', '2026-05-19 17:30:09');
INSERT INTO `lp_room` VALUES (3, 'R1003', '清晨自习直播间4', '适合切后台挂机的专注陪伴流', 7, 'live', 1, 'https://picsum.photos/seed/live-room-3/720/1280', 100, '2026-05-18 09:49:16', '2026-07-04 17:34:42');
INSERT INTO `lp_room` VALUES (4, 'E2E1001', 'E2E联调测试房间', '后台上传素材后的真实联调房间', 1, 'live', 2, '', 50, '2026-05-18 17:29:54', '2026-07-04 16:48:23');

-- ----------------------------
-- Table structure for lp_room_binding
-- ----------------------------
DROP TABLE IF EXISTS `lp_room_binding`;
CREATE TABLE `lp_room_binding`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `room_id` bigint(20) UNSIGNED NOT NULL COMMENT '房间ID',
  `room_group_id` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '房间分组ID',
  `stream_template_id` bigint(20) UNSIGNED NOT NULL COMMENT '流模板ID',
  `playlist_template_id` bigint(20) UNSIGNED NOT NULL COMMENT '公共播单模板ID',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_room_id`(`room_id` ASC) USING BTREE,
  INDEX `idx_room_group_id`(`room_group_id` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 5 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '房间绑定配置' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_room_binding
-- ----------------------------
INSERT INTO `lp_room_binding` VALUES (1, 1, 1, 1, 2);
INSERT INTO `lp_room_binding` VALUES (2, 2, 1, 1, 3);
INSERT INTO `lp_room_binding` VALUES (3, 3, 1, 1, 4);
INSERT INTO `lp_room_binding` VALUES (4, 4, NULL, 1, 5);

-- ----------------------------
-- Table structure for lp_room_event_log
-- ----------------------------
DROP TABLE IF EXISTS `lp_room_event_log`;
CREATE TABLE `lp_room_event_log`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `room_id` bigint(20) UNSIGNED NOT NULL COMMENT '房间ID',
  `event_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '事件类型',
  `payload_json` json NULL COMMENT '事件数据',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_room_created`(`room_id` ASC, `created_at` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '房间事件日志' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_room_event_log
-- ----------------------------

-- ----------------------------
-- Table structure for lp_room_group
-- ----------------------------
DROP TABLE IF EXISTS `lp_room_group`;
CREATE TABLE `lp_room_group`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '分组名称',
  `source_group_code` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '共享源组编码',
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '状态',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_source_group_code`(`source_group_code` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '共享源房间分组' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_room_group
-- ----------------------------
INSERT INTO `lp_room_group` VALUES (1, '默认分组', 'group_demo', 1);

-- ----------------------------
-- Table structure for lp_room_online_minute
-- ----------------------------
DROP TABLE IF EXISTS `lp_room_online_minute`;
CREATE TABLE `lp_room_online_minute`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `room_id` bigint(20) UNSIGNED NOT NULL COMMENT '房间ID',
  `minute_at` datetime NOT NULL COMMENT '分钟时间',
  `online_count` int(11) NOT NULL DEFAULT 0 COMMENT '在线人数',
  `like_count` int(11) NOT NULL DEFAULT 0 COMMENT '点赞数',
  `gift_amount` decimal(18, 2) NOT NULL DEFAULT 0.00 COMMENT '礼物金额',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_room_minute`(`room_id` ASC, `minute_at` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1238 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '房间分钟聚合数据' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_room_online_minute
-- ----------------------------
INSERT INTO `lp_room_online_minute` VALUES (1, 1, '2026-05-17 10:35:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2, 1, '2026-05-20 11:28:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3, 2, '2026-05-20 11:28:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4, 1, '2026-05-20 11:29:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5, 1, '2026-05-20 11:30:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (6, 1, '2026-05-20 11:31:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (7, 1, '2026-05-20 11:32:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (8, 1, '2026-05-20 11:33:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (9, 1, '2026-05-20 11:34:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (10, 2, '2026-05-20 11:34:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (11, 1, '2026-05-20 11:35:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (12, 2, '2026-05-20 11:35:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (13, 3, '2026-05-20 11:35:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (14, 1, '2026-05-20 11:36:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (15, 2, '2026-05-20 11:36:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (16, 1, '2026-05-20 11:37:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (17, 2, '2026-05-20 11:37:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (18, 3, '2026-05-20 11:37:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (19, 1, '2026-05-20 11:38:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (20, 2, '2026-05-20 11:38:00', 5, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (21, 1, '2026-05-20 11:39:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (22, 2, '2026-05-20 11:39:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (23, 1, '2026-05-20 11:40:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (24, 2, '2026-05-20 11:40:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (25, 2, '2026-05-20 11:42:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (26, 2, '2026-05-20 11:43:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (27, 1, '2026-05-20 11:44:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (28, 2, '2026-05-20 11:44:00', 12, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (29, 1, '2026-05-20 11:46:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (30, 2, '2026-05-20 11:46:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (31, 1, '2026-05-20 11:48:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (32, 2, '2026-05-20 11:48:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (33, 1, '2026-05-20 11:49:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (34, 2, '2026-05-20 11:49:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (35, 1, '2026-05-20 11:50:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (36, 2, '2026-05-20 11:50:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (37, 1, '2026-05-20 11:51:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (38, 2, '2026-05-20 11:51:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (39, 1, '2026-05-20 11:52:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (40, 2, '2026-05-20 11:52:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (41, 1, '2026-05-20 11:53:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (42, 2, '2026-05-20 11:53:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (43, 1, '2026-05-20 11:54:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (44, 2, '2026-05-20 11:54:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (45, 1, '2026-05-20 11:55:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (46, 2, '2026-05-20 11:55:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (47, 1, '2026-05-20 11:56:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (48, 2, '2026-05-20 11:56:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (49, 1, '2026-05-20 11:57:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (50, 2, '2026-05-20 11:57:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (51, 1, '2026-05-20 11:58:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (52, 2, '2026-05-20 11:58:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (53, 1, '2026-05-20 11:59:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (54, 2, '2026-05-20 11:59:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (55, 1, '2026-05-20 12:00:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (56, 2, '2026-05-20 12:00:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (57, 1, '2026-05-20 12:01:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (58, 2, '2026-05-20 12:01:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (59, 1, '2026-05-20 12:02:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (60, 2, '2026-05-20 12:02:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (61, 1, '2026-05-20 12:03:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (62, 2, '2026-05-20 12:03:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (63, 1, '2026-05-20 12:04:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (64, 2, '2026-05-20 12:04:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (65, 1, '2026-05-20 12:05:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (66, 2, '2026-05-20 12:05:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (67, 1, '2026-05-20 12:06:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (68, 2, '2026-05-20 12:06:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (69, 1, '2026-05-20 12:07:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (70, 2, '2026-05-20 12:07:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (71, 1, '2026-05-20 12:08:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (72, 2, '2026-05-20 12:08:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (73, 1, '2026-05-20 12:09:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (74, 2, '2026-05-20 12:09:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (75, 1, '2026-05-20 12:10:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (76, 2, '2026-05-20 12:10:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (77, 1, '2026-05-20 12:11:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (78, 2, '2026-05-20 12:11:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (79, 1, '2026-05-20 12:12:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (80, 2, '2026-05-20 12:12:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (81, 1, '2026-05-20 12:13:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (82, 2, '2026-05-20 12:13:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (83, 1, '2026-05-20 12:14:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (84, 2, '2026-05-20 12:14:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (85, 1, '2026-05-20 12:15:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (86, 2, '2026-05-20 12:15:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (87, 1, '2026-05-20 12:16:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (88, 2, '2026-05-20 12:16:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (89, 1, '2026-05-20 12:17:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (90, 2, '2026-05-20 12:17:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (91, 1, '2026-05-20 12:18:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (92, 2, '2026-05-20 12:18:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (93, 1, '2026-05-20 12:19:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (94, 2, '2026-05-20 12:19:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (95, 1, '2026-05-20 12:20:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (96, 2, '2026-05-20 12:20:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (97, 1, '2026-05-20 12:21:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (98, 2, '2026-05-20 12:21:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (99, 1, '2026-05-20 12:22:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (100, 2, '2026-05-20 12:22:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (101, 1, '2026-05-20 12:23:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (102, 2, '2026-05-20 12:23:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (103, 1, '2026-05-20 12:24:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (104, 2, '2026-05-20 12:24:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (105, 1, '2026-05-20 12:25:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (106, 2, '2026-05-20 12:25:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (107, 1, '2026-05-20 12:26:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (108, 2, '2026-05-20 12:26:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (109, 1, '2026-05-20 12:27:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (110, 2, '2026-05-20 12:27:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (111, 1, '2026-05-20 12:28:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (112, 2, '2026-05-20 12:28:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (113, 1, '2026-05-20 12:29:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (114, 2, '2026-05-20 12:29:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (115, 1, '2026-05-20 12:30:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (116, 2, '2026-05-20 12:30:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (117, 1, '2026-05-20 12:31:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (118, 2, '2026-05-20 12:31:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (119, 1, '2026-05-20 12:32:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (120, 2, '2026-05-20 12:32:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (121, 1, '2026-05-20 12:33:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (122, 2, '2026-05-20 12:33:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (123, 1, '2026-05-20 12:34:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (124, 2, '2026-05-20 12:34:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (125, 1, '2026-05-20 12:35:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (126, 2, '2026-05-20 12:35:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (127, 1, '2026-05-20 12:36:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (128, 2, '2026-05-20 12:36:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (129, 1, '2026-05-20 12:37:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (130, 2, '2026-05-20 12:37:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (131, 1, '2026-05-20 12:38:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (132, 2, '2026-05-20 12:38:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (133, 1, '2026-05-20 12:39:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (134, 2, '2026-05-20 12:39:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (135, 1, '2026-05-20 12:40:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (136, 2, '2026-05-20 12:40:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (137, 1, '2026-05-20 12:41:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (138, 2, '2026-05-20 12:41:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (139, 1, '2026-05-20 12:42:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (140, 2, '2026-05-20 12:42:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (141, 1, '2026-05-20 12:43:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (142, 2, '2026-05-20 12:43:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (143, 1, '2026-05-20 12:44:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (144, 2, '2026-05-20 12:44:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (145, 1, '2026-05-20 12:45:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (146, 2, '2026-05-20 12:45:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (147, 1, '2026-05-20 12:46:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (148, 2, '2026-05-20 12:46:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (149, 1, '2026-05-20 12:47:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (150, 2, '2026-05-20 12:47:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (151, 1, '2026-05-20 12:48:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (152, 2, '2026-05-20 12:48:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (153, 1, '2026-05-20 12:49:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (154, 2, '2026-05-20 12:49:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (155, 1, '2026-05-20 12:50:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (156, 2, '2026-05-20 12:50:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (157, 1, '2026-05-20 12:51:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (158, 2, '2026-05-20 12:51:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (159, 1, '2026-05-20 12:52:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (160, 2, '2026-05-20 12:52:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (161, 1, '2026-05-20 12:53:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (162, 2, '2026-05-20 12:53:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (163, 1, '2026-05-20 12:56:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (164, 2, '2026-05-20 12:56:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (165, 1, '2026-05-20 12:57:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (166, 2, '2026-05-20 12:57:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (167, 1, '2026-05-20 12:58:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (168, 2, '2026-05-20 12:58:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (169, 1, '2026-05-20 12:59:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (170, 2, '2026-05-20 12:59:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (171, 1, '2026-05-20 13:00:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (172, 2, '2026-05-20 13:00:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (173, 1, '2026-05-20 13:01:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (174, 2, '2026-05-20 13:01:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (175, 1, '2026-05-20 13:02:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (176, 2, '2026-05-20 13:02:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (177, 1, '2026-05-20 13:03:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (178, 2, '2026-05-20 13:03:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (179, 1, '2026-05-20 13:04:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (180, 2, '2026-05-20 13:04:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (181, 1, '2026-05-20 13:05:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (182, 2, '2026-05-20 13:05:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (183, 1, '2026-05-20 13:06:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (184, 2, '2026-05-20 13:06:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (185, 1, '2026-05-20 13:07:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (186, 2, '2026-05-20 13:07:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (187, 1, '2026-05-20 13:08:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (188, 2, '2026-05-20 13:08:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (189, 1, '2026-05-20 13:09:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (190, 2, '2026-05-20 13:09:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (191, 1, '2026-05-20 13:10:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (192, 2, '2026-05-20 13:10:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (193, 1, '2026-05-20 13:11:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (194, 2, '2026-05-20 13:11:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (195, 1, '2026-05-20 13:12:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (196, 2, '2026-05-20 13:12:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (197, 1, '2026-05-20 13:13:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (198, 2, '2026-05-20 13:13:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (199, 1, '2026-05-20 13:14:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (200, 2, '2026-05-20 13:14:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (201, 1, '2026-05-20 13:15:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (202, 2, '2026-05-20 13:15:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (203, 1, '2026-05-20 13:16:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (204, 2, '2026-05-20 13:16:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (205, 1, '2026-05-20 13:17:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (206, 2, '2026-05-20 13:17:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (207, 1, '2026-05-20 13:18:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (208, 2, '2026-05-20 13:18:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (209, 1, '2026-05-20 13:19:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (210, 2, '2026-05-20 13:19:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (211, 1, '2026-05-20 13:20:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (212, 2, '2026-05-20 13:20:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (213, 1, '2026-05-20 13:21:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (214, 2, '2026-05-20 13:21:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (215, 1, '2026-05-20 13:22:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (216, 2, '2026-05-20 13:22:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (217, 1, '2026-05-20 13:23:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (218, 2, '2026-05-20 13:23:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (219, 1, '2026-05-20 13:24:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (220, 2, '2026-05-20 13:24:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (221, 1, '2026-05-20 13:25:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (222, 2, '2026-05-20 13:25:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (223, 1, '2026-05-20 13:26:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (224, 2, '2026-05-20 13:26:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (225, 1, '2026-05-20 13:27:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (226, 2, '2026-05-20 13:27:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (227, 1, '2026-05-20 13:28:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (228, 2, '2026-05-20 13:28:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (229, 1, '2026-05-20 13:29:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (230, 2, '2026-05-20 13:29:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (231, 1, '2026-05-20 13:30:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (232, 2, '2026-05-20 13:30:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (233, 1, '2026-05-20 13:31:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (234, 2, '2026-05-20 13:31:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (235, 1, '2026-05-20 13:32:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (236, 2, '2026-05-20 13:32:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (237, 1, '2026-05-20 13:33:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (238, 2, '2026-05-20 13:33:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (239, 1, '2026-05-20 13:34:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (240, 2, '2026-05-20 13:34:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (241, 1, '2026-05-20 13:35:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (242, 2, '2026-05-20 13:35:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (243, 1, '2026-05-20 13:36:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (244, 2, '2026-05-20 13:36:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (245, 1, '2026-05-20 13:37:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (246, 2, '2026-05-20 13:37:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (247, 1, '2026-05-20 13:38:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (248, 2, '2026-05-20 13:38:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (249, 1, '2026-05-20 13:39:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (250, 2, '2026-05-20 13:39:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (251, 1, '2026-05-20 13:40:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (252, 2, '2026-05-20 13:40:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (253, 1, '2026-05-20 13:41:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (254, 2, '2026-05-20 13:41:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (255, 1, '2026-05-20 13:42:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (256, 2, '2026-05-20 13:42:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (257, 1, '2026-05-20 13:43:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (258, 2, '2026-05-20 13:43:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (259, 1, '2026-05-20 13:44:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (260, 2, '2026-05-20 13:44:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (261, 1, '2026-05-20 13:45:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (262, 2, '2026-05-20 13:45:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (263, 1, '2026-05-20 13:46:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (264, 2, '2026-05-20 13:46:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (265, 1, '2026-05-20 13:47:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (266, 2, '2026-05-20 13:47:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (267, 1, '2026-05-20 13:48:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (268, 2, '2026-05-20 13:48:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (269, 1, '2026-05-20 13:49:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (270, 2, '2026-05-20 13:49:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (271, 1, '2026-05-20 13:50:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (272, 2, '2026-05-20 13:50:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (273, 1, '2026-05-20 13:51:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (274, 2, '2026-05-20 13:51:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (275, 1, '2026-05-20 13:52:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (276, 2, '2026-05-20 13:52:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (277, 1, '2026-05-20 13:53:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (278, 2, '2026-05-20 13:53:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (279, 1, '2026-05-20 13:54:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (280, 2, '2026-05-20 13:54:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (281, 1, '2026-05-20 13:55:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (282, 2, '2026-05-20 13:55:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (283, 1, '2026-05-20 13:56:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (284, 2, '2026-05-20 13:56:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (285, 1, '2026-05-20 13:57:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (286, 2, '2026-05-20 13:57:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (287, 1, '2026-05-20 13:58:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (288, 2, '2026-05-20 13:58:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (289, 1, '2026-05-20 13:59:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (290, 2, '2026-05-20 13:59:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (291, 1, '2026-05-20 14:00:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (292, 2, '2026-05-20 14:00:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (293, 1, '2026-05-20 14:01:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (294, 2, '2026-05-20 14:01:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (295, 1, '2026-05-20 14:02:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (296, 2, '2026-05-20 14:02:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (297, 1, '2026-05-20 14:03:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (298, 2, '2026-05-20 14:03:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (299, 1, '2026-05-20 14:04:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (300, 2, '2026-05-20 14:04:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (301, 1, '2026-05-20 14:05:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (302, 2, '2026-05-20 14:05:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (303, 1, '2026-05-20 14:06:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (304, 2, '2026-05-20 14:06:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (305, 1, '2026-05-20 14:07:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (306, 2, '2026-05-20 14:07:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (307, 1, '2026-05-20 14:08:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (308, 2, '2026-05-20 14:08:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (309, 1, '2026-05-20 14:09:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (310, 2, '2026-05-20 14:09:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (311, 1, '2026-05-20 14:10:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (312, 2, '2026-05-20 14:10:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (313, 1, '2026-05-20 14:11:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (314, 2, '2026-05-20 14:11:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (315, 1, '2026-05-20 14:12:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (316, 2, '2026-05-20 14:12:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (317, 1, '2026-05-20 14:13:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (318, 2, '2026-05-20 14:13:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (319, 1, '2026-05-20 14:14:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (320, 2, '2026-05-20 14:14:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (321, 1, '2026-05-20 14:15:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (322, 2, '2026-05-20 14:15:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (323, 1, '2026-05-20 14:16:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (324, 2, '2026-05-20 14:16:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (325, 1, '2026-05-20 14:17:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (326, 2, '2026-05-20 14:17:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (327, 1, '2026-05-20 14:18:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (328, 2, '2026-05-20 14:18:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (329, 1, '2026-05-20 14:19:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (330, 2, '2026-05-20 14:19:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (331, 1, '2026-05-20 14:20:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (332, 2, '2026-05-20 14:20:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (333, 1, '2026-05-20 14:21:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (334, 2, '2026-05-20 14:21:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (335, 1, '2026-05-20 14:22:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (336, 2, '2026-05-20 14:22:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (337, 1, '2026-05-20 14:23:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (338, 2, '2026-05-20 14:23:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (339, 1, '2026-05-20 14:24:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (340, 2, '2026-05-20 14:24:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (341, 1, '2026-05-20 14:25:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (342, 2, '2026-05-20 14:25:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (343, 1, '2026-05-20 14:26:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (344, 2, '2026-05-20 14:26:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (345, 1, '2026-05-20 14:27:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (346, 2, '2026-05-20 14:27:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (347, 1, '2026-05-20 14:28:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (348, 2, '2026-05-20 14:28:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (349, 1, '2026-05-20 14:29:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (350, 2, '2026-05-20 14:29:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (351, 1, '2026-05-20 14:30:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (352, 2, '2026-05-20 14:30:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (353, 1, '2026-05-20 14:31:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (354, 2, '2026-05-20 14:31:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (355, 1, '2026-05-20 14:32:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (356, 2, '2026-05-20 14:32:00', 16, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (357, 1, '2026-05-20 14:33:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (358, 2, '2026-05-20 14:33:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (359, 1, '2026-05-20 14:34:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (360, 2, '2026-05-20 14:34:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (361, 1, '2026-05-20 14:35:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (362, 2, '2026-05-20 14:35:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (363, 1, '2026-05-20 14:36:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (364, 2, '2026-05-20 14:36:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (365, 1, '2026-05-20 14:37:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (366, 2, '2026-05-20 14:37:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (367, 1, '2026-05-20 14:38:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (368, 2, '2026-05-20 14:38:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (369, 1, '2026-05-20 14:39:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (370, 2, '2026-05-20 14:39:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (371, 1, '2026-05-20 14:40:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (372, 2, '2026-05-20 14:40:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (373, 1, '2026-05-20 14:41:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (374, 2, '2026-05-20 14:41:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (375, 1, '2026-05-20 14:42:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (376, 2, '2026-05-20 14:42:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (377, 1, '2026-05-20 14:43:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (378, 2, '2026-05-20 14:43:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (379, 1, '2026-05-20 14:44:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (380, 2, '2026-05-20 14:44:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (381, 1, '2026-05-20 14:45:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (382, 2, '2026-05-20 14:45:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (383, 1, '2026-05-20 14:46:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (384, 2, '2026-05-20 14:46:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (385, 1, '2026-05-20 14:47:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (386, 2, '2026-05-20 14:47:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (387, 1, '2026-05-20 14:48:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (388, 2, '2026-05-20 14:48:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (389, 1, '2026-05-20 14:49:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (390, 2, '2026-05-20 14:49:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (391, 1, '2026-05-20 14:50:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (392, 2, '2026-05-20 14:50:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (393, 1, '2026-05-20 14:51:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (394, 2, '2026-05-20 14:51:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (395, 1, '2026-05-20 14:52:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (396, 2, '2026-05-20 14:52:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (397, 1, '2026-05-20 14:53:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (398, 2, '2026-05-20 14:53:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (399, 1, '2026-05-20 14:54:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (400, 2, '2026-05-20 14:54:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (401, 1, '2026-05-20 14:55:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (402, 2, '2026-05-20 14:55:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (403, 1, '2026-05-20 14:56:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (404, 2, '2026-05-20 14:56:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (405, 1, '2026-05-20 14:57:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (406, 2, '2026-05-20 14:57:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (407, 1, '2026-05-20 14:58:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (408, 2, '2026-05-20 14:58:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (409, 1, '2026-05-20 14:59:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (410, 2, '2026-05-20 14:59:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (411, 1, '2026-05-20 15:00:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (412, 2, '2026-05-20 15:00:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (413, 1, '2026-05-20 15:01:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (414, 2, '2026-05-20 15:01:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (415, 1, '2026-05-20 15:02:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (416, 2, '2026-05-20 15:02:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (417, 1, '2026-05-20 15:03:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (418, 2, '2026-05-20 15:03:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (419, 1, '2026-05-20 15:04:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (420, 2, '2026-05-20 15:04:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (421, 1, '2026-05-20 15:05:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (422, 2, '2026-05-20 15:05:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (423, 1, '2026-05-20 15:06:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (424, 2, '2026-05-20 15:06:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (425, 1, '2026-05-20 15:07:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (426, 2, '2026-05-20 15:07:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (427, 1, '2026-05-20 15:08:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (428, 2, '2026-05-20 15:08:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (429, 1, '2026-05-20 15:09:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (430, 2, '2026-05-20 15:09:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (431, 1, '2026-05-20 15:10:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (432, 2, '2026-05-20 15:10:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (433, 1, '2026-05-20 15:11:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (434, 2, '2026-05-20 15:11:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (435, 1, '2026-05-20 15:12:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (436, 2, '2026-05-20 15:12:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (437, 1, '2026-05-20 15:13:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (438, 2, '2026-05-20 15:13:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (439, 1, '2026-05-20 15:14:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (440, 2, '2026-05-20 15:14:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (441, 1, '2026-05-20 15:15:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (442, 2, '2026-05-20 15:15:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (443, 1, '2026-05-20 15:16:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (444, 2, '2026-05-20 15:16:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (445, 1, '2026-05-20 15:17:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (446, 2, '2026-05-20 15:17:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (447, 1, '2026-05-20 15:18:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (448, 2, '2026-05-20 15:18:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (449, 1, '2026-05-20 15:19:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (450, 2, '2026-05-20 15:19:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (451, 1, '2026-05-20 15:20:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (452, 2, '2026-05-20 15:20:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (453, 1, '2026-05-20 15:21:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (454, 2, '2026-05-20 15:21:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (455, 1, '2026-05-20 15:22:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (456, 2, '2026-05-20 15:22:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (457, 1, '2026-05-20 15:23:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (458, 2, '2026-05-20 15:23:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (459, 1, '2026-05-20 15:24:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (460, 2, '2026-05-20 15:24:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (461, 1, '2026-05-20 15:25:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (462, 2, '2026-05-20 15:25:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (463, 1, '2026-05-20 15:26:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (464, 2, '2026-05-20 15:26:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (465, 1, '2026-05-20 15:27:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (466, 2, '2026-05-20 15:27:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (467, 1, '2026-05-20 15:28:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (468, 2, '2026-05-20 15:28:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (469, 1, '2026-05-20 15:29:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (470, 2, '2026-05-20 15:29:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (471, 1, '2026-05-20 15:30:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (472, 2, '2026-05-20 15:30:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (473, 1, '2026-05-20 15:31:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (474, 2, '2026-05-20 15:31:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (475, 1, '2026-05-20 15:32:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (476, 2, '2026-05-20 15:32:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (477, 1, '2026-05-20 15:33:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (478, 2, '2026-05-20 15:33:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (479, 1, '2026-05-20 15:34:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (480, 2, '2026-05-20 15:34:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (481, 1, '2026-05-20 15:35:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (482, 2, '2026-05-20 15:35:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (483, 1, '2026-05-20 15:36:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (484, 2, '2026-05-20 15:36:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (485, 1, '2026-05-20 15:37:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (486, 2, '2026-05-20 15:37:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (487, 1, '2026-05-20 15:38:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (488, 2, '2026-05-20 15:38:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (489, 1, '2026-05-20 15:39:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (490, 2, '2026-05-20 15:39:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (491, 1, '2026-05-20 15:40:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (492, 2, '2026-05-20 15:40:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (493, 1, '2026-05-20 15:41:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (494, 2, '2026-05-20 15:41:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (495, 1, '2026-05-20 15:42:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (496, 2, '2026-05-20 15:42:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (497, 1, '2026-05-20 15:43:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (498, 2, '2026-05-20 15:43:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (499, 1, '2026-05-20 15:44:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (500, 2, '2026-05-20 15:44:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (501, 1, '2026-05-20 15:45:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (502, 2, '2026-05-20 15:45:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (503, 1, '2026-05-20 15:46:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (504, 2, '2026-05-20 15:46:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (505, 1, '2026-05-20 15:47:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (506, 2, '2026-05-20 15:47:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (507, 1, '2026-05-20 15:48:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (508, 2, '2026-05-20 15:48:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (509, 1, '2026-05-20 15:49:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (510, 2, '2026-05-20 15:49:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (511, 1, '2026-05-20 15:50:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (512, 2, '2026-05-20 15:50:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (513, 1, '2026-05-20 15:51:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (514, 2, '2026-05-20 15:51:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (515, 1, '2026-05-20 15:52:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (516, 2, '2026-05-20 15:52:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (517, 1, '2026-05-20 15:53:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (518, 2, '2026-05-20 15:53:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (519, 1, '2026-05-20 15:54:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (520, 2, '2026-05-20 15:54:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (521, 1, '2026-05-20 15:55:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (522, 2, '2026-05-20 15:55:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (523, 1, '2026-05-20 15:56:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (524, 2, '2026-05-20 15:56:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (525, 1, '2026-05-20 15:57:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (526, 2, '2026-05-20 15:57:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (527, 1, '2026-05-20 15:58:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (528, 2, '2026-05-20 15:58:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (529, 1, '2026-05-20 15:59:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (530, 2, '2026-05-20 15:59:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (531, 1, '2026-05-20 16:00:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (532, 2, '2026-05-20 16:00:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (533, 1, '2026-05-20 16:01:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (534, 2, '2026-05-20 16:01:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (535, 1, '2026-05-20 16:02:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (536, 2, '2026-05-20 16:02:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (537, 1, '2026-05-20 16:03:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (538, 2, '2026-05-20 16:03:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (539, 1, '2026-05-20 16:04:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (540, 2, '2026-05-20 16:04:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (541, 3, '2026-05-21 09:31:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (542, 2, '2026-05-21 09:32:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (543, 3, '2026-05-21 09:32:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (544, 3, '2026-05-21 09:33:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (545, 3, '2026-05-21 09:34:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (546, 3, '2026-05-21 09:35:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (547, 3, '2026-05-21 09:36:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (548, 3, '2026-05-21 09:37:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (549, 3, '2026-05-21 09:38:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (550, 3, '2026-05-21 09:39:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (551, 3, '2026-05-21 09:40:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (552, 3, '2026-05-21 09:41:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (553, 3, '2026-05-21 09:42:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (554, 3, '2026-05-21 09:43:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (555, 3, '2026-05-21 09:44:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (556, 3, '2026-05-21 09:45:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (557, 3, '2026-05-21 09:46:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (558, 3, '2026-05-21 09:47:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (559, 3, '2026-05-21 09:48:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (560, 3, '2026-05-21 09:49:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (561, 3, '2026-05-21 09:50:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (562, 3, '2026-05-21 09:51:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (563, 3, '2026-05-21 09:52:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (564, 3, '2026-05-21 09:53:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (565, 3, '2026-05-21 09:54:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (566, 3, '2026-05-21 09:55:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (567, 3, '2026-05-21 09:56:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (568, 3, '2026-05-21 09:57:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (569, 3, '2026-05-21 09:58:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (570, 3, '2026-05-21 09:59:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (571, 3, '2026-05-21 10:00:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (572, 3, '2026-05-21 10:01:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (573, 3, '2026-05-21 10:02:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (574, 3, '2026-05-21 10:03:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (575, 3, '2026-05-21 10:04:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (576, 3, '2026-05-21 10:05:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (577, 3, '2026-05-21 10:06:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (578, 3, '2026-05-21 10:07:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (579, 3, '2026-05-21 10:08:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (580, 3, '2026-05-21 10:09:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (581, 3, '2026-05-21 10:10:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (582, 3, '2026-05-21 10:11:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (583, 3, '2026-05-21 10:12:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (584, 3, '2026-05-21 10:13:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (585, 3, '2026-05-21 10:14:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (586, 3, '2026-05-21 10:15:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (587, 3, '2026-05-21 10:16:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (588, 3, '2026-05-21 10:17:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (589, 3, '2026-05-21 10:18:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (590, 3, '2026-05-21 10:19:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (591, 3, '2026-05-21 10:20:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (592, 3, '2026-05-21 10:21:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (593, 3, '2026-05-21 10:22:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (594, 3, '2026-05-21 10:23:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (595, 3, '2026-05-21 10:24:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (596, 3, '2026-05-21 10:25:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (597, 3, '2026-05-21 10:26:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (598, 3, '2026-05-21 10:27:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (599, 3, '2026-05-21 10:28:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (600, 3, '2026-05-21 10:29:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (601, 3, '2026-05-21 10:30:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (602, 3, '2026-05-21 10:31:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (603, 3, '2026-05-21 10:32:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (604, 3, '2026-05-21 10:33:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (605, 3, '2026-05-21 12:34:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (606, 2, '2026-05-21 12:35:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (607, 3, '2026-05-21 12:35:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (608, 2, '2026-05-21 12:36:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (609, 3, '2026-05-21 12:36:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (610, 2, '2026-05-21 12:37:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (611, 3, '2026-05-21 12:37:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (612, 2, '2026-05-21 12:40:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (613, 3, '2026-05-21 12:40:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (614, 2, '2026-05-21 12:41:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (615, 3, '2026-05-21 12:41:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (616, 2, '2026-05-21 12:42:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (617, 3, '2026-05-21 12:42:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (618, 2, '2026-05-21 12:43:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (619, 3, '2026-05-21 12:43:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (620, 2, '2026-05-21 12:44:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (621, 3, '2026-05-21 12:44:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (622, 2, '2026-05-21 12:45:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (623, 3, '2026-05-21 12:45:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (624, 2, '2026-05-21 12:46:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (625, 3, '2026-05-21 12:46:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (626, 2, '2026-05-21 12:47:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (627, 3, '2026-05-21 12:47:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (628, 2, '2026-05-21 12:48:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (629, 3, '2026-05-21 12:48:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (630, 2, '2026-05-21 12:49:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (631, 3, '2026-05-21 12:49:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (632, 2, '2026-05-21 12:50:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (633, 3, '2026-05-21 12:50:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (634, 2, '2026-05-21 12:51:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (635, 3, '2026-05-21 12:51:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (636, 1, '2026-05-21 12:52:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (637, 2, '2026-05-21 12:52:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (638, 3, '2026-05-21 12:52:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (639, 1, '2026-05-21 12:53:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (640, 2, '2026-05-21 12:53:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (641, 3, '2026-05-21 12:53:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (642, 1, '2026-05-21 12:54:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (643, 2, '2026-05-21 12:54:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (644, 3, '2026-05-21 12:54:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (645, 1, '2026-05-21 12:55:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (646, 2, '2026-05-21 12:55:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (647, 3, '2026-05-21 12:55:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (648, 1, '2026-05-21 12:56:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (649, 2, '2026-05-21 12:56:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (650, 3, '2026-05-21 12:56:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (651, 1, '2026-05-21 12:57:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (652, 2, '2026-05-21 12:57:00', 28, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (653, 3, '2026-05-21 12:57:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (654, 1, '2026-05-21 12:58:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (655, 2, '2026-05-21 12:58:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (656, 3, '2026-05-21 12:58:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (657, 1, '2026-05-21 12:59:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (658, 2, '2026-05-21 12:59:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (659, 3, '2026-05-21 12:59:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (660, 1, '2026-05-21 13:00:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (661, 2, '2026-05-21 13:00:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (662, 3, '2026-05-21 13:00:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (663, 1, '2026-05-21 13:01:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (664, 2, '2026-05-21 13:01:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (665, 3, '2026-05-21 13:01:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (666, 1, '2026-05-21 13:02:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (667, 2, '2026-05-21 13:02:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (668, 3, '2026-05-21 13:02:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (669, 1, '2026-05-21 13:03:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (670, 2, '2026-05-21 13:03:00', 22, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (671, 3, '2026-05-21 13:03:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (672, 1, '2026-05-21 13:04:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (673, 2, '2026-05-21 13:04:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (674, 3, '2026-05-21 13:04:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (675, 1, '2026-05-21 13:05:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (676, 2, '2026-05-21 13:05:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (677, 3, '2026-05-21 13:05:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (678, 1, '2026-05-21 13:06:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (679, 2, '2026-05-21 13:06:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (680, 3, '2026-05-21 13:06:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (681, 1, '2026-05-21 13:07:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (682, 2, '2026-05-21 13:07:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (683, 3, '2026-05-21 13:07:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (684, 1, '2026-05-21 13:08:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (685, 2, '2026-05-21 13:08:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (686, 3, '2026-05-21 13:08:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (687, 1, '2026-05-21 13:09:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (688, 2, '2026-05-21 13:09:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (689, 3, '2026-05-21 13:09:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (690, 1, '2026-05-21 13:10:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (691, 2, '2026-05-21 13:10:00', 28, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (692, 3, '2026-05-21 13:10:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (693, 1, '2026-05-21 13:11:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (694, 2, '2026-05-21 13:11:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (695, 3, '2026-05-21 13:11:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (696, 1, '2026-05-21 13:12:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (697, 2, '2026-05-21 13:12:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (698, 3, '2026-05-21 13:12:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (699, 1, '2026-05-21 13:13:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (700, 2, '2026-05-21 13:13:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (701, 3, '2026-05-21 13:13:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (702, 1, '2026-05-21 13:14:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (703, 2, '2026-05-21 13:14:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (704, 3, '2026-05-21 13:14:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (705, 1, '2026-05-21 13:15:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (706, 2, '2026-05-21 13:15:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (707, 3, '2026-05-21 13:15:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (708, 1, '2026-05-21 13:16:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (709, 2, '2026-05-21 13:16:00', 22, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (710, 3, '2026-05-21 13:16:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (711, 1, '2026-05-21 13:17:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (712, 2, '2026-05-21 13:17:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (713, 3, '2026-05-21 13:17:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (714, 1, '2026-05-21 13:18:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (715, 2, '2026-05-21 13:18:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (716, 3, '2026-05-21 13:18:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (717, 1, '2026-05-21 13:19:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (718, 2, '2026-05-21 13:19:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (719, 3, '2026-05-21 13:19:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (720, 1, '2026-05-21 13:20:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (721, 2, '2026-05-21 13:20:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (722, 3, '2026-05-21 13:20:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (723, 1, '2026-05-21 13:21:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (724, 2, '2026-05-21 13:21:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (725, 3, '2026-05-21 13:21:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (726, 1, '2026-05-21 13:22:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (727, 2, '2026-05-21 13:22:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (728, 3, '2026-05-21 13:22:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (729, 1, '2026-05-21 13:23:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (730, 2, '2026-05-21 13:23:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (731, 3, '2026-05-21 13:23:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (732, 1, '2026-05-21 13:24:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (733, 2, '2026-05-21 13:24:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (734, 3, '2026-05-21 13:24:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (735, 1, '2026-05-21 13:25:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (736, 2, '2026-05-21 13:25:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (737, 3, '2026-05-21 13:25:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (738, 1, '2026-05-21 13:26:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (739, 2, '2026-05-21 13:26:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (740, 3, '2026-05-21 13:26:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (741, 1, '2026-05-21 13:27:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (742, 2, '2026-05-21 13:27:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (743, 3, '2026-05-21 13:27:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (744, 1, '2026-05-21 13:28:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (745, 2, '2026-05-21 13:28:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (746, 3, '2026-05-21 13:28:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (747, 1, '2026-05-21 13:29:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (748, 2, '2026-05-21 13:29:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (749, 3, '2026-05-21 13:29:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (750, 1, '2026-05-21 13:30:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (751, 2, '2026-05-21 13:30:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (752, 3, '2026-05-21 13:30:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (753, 1, '2026-05-21 13:31:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (754, 2, '2026-05-21 13:31:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (755, 3, '2026-05-21 13:31:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (756, 1, '2026-05-21 13:32:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (757, 2, '2026-05-21 13:32:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (758, 3, '2026-05-21 13:32:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (759, 1, '2026-05-21 13:33:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (760, 2, '2026-05-21 13:33:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (761, 3, '2026-05-21 13:33:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (762, 1, '2026-05-21 13:34:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (763, 2, '2026-05-21 13:34:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (764, 3, '2026-05-21 13:34:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (765, 1, '2026-05-21 13:35:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (766, 2, '2026-05-21 13:35:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (767, 3, '2026-05-21 13:35:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (768, 1, '2026-05-21 13:36:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (769, 2, '2026-05-21 13:36:00', 25, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (770, 3, '2026-05-21 13:36:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (771, 1, '2026-05-21 13:37:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (772, 2, '2026-05-21 13:37:00', 25, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (773, 3, '2026-05-21 13:37:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (774, 1, '2026-05-21 13:38:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (775, 2, '2026-05-21 13:38:00', 22, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (776, 3, '2026-05-21 13:38:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (777, 1, '2026-05-21 13:39:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (778, 2, '2026-05-21 13:39:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (779, 3, '2026-05-21 13:39:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (780, 1, '2026-05-21 13:40:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (781, 2, '2026-05-21 13:40:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (782, 3, '2026-05-21 13:40:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (783, 1, '2026-05-21 13:41:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (784, 2, '2026-05-21 13:41:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (785, 3, '2026-05-21 13:41:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (786, 1, '2026-05-21 13:42:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (787, 2, '2026-05-21 13:42:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (788, 3, '2026-05-21 13:42:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (789, 1, '2026-05-21 13:43:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (790, 2, '2026-05-21 13:43:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (791, 3, '2026-05-21 13:43:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (792, 1, '2026-05-21 13:44:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (793, 2, '2026-05-21 13:44:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (794, 3, '2026-05-21 13:44:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (795, 1, '2026-05-21 13:45:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (796, 2, '2026-05-21 13:45:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (797, 3, '2026-05-21 13:45:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (798, 1, '2026-05-21 13:46:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (799, 2, '2026-05-21 13:46:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (800, 3, '2026-05-21 13:46:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (801, 1, '2026-05-21 13:47:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (802, 2, '2026-05-21 13:47:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (803, 3, '2026-05-21 13:47:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (804, 1, '2026-05-21 13:48:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (805, 2, '2026-05-21 13:48:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (806, 3, '2026-05-21 13:48:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (807, 1, '2026-05-21 13:49:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (808, 2, '2026-05-21 13:49:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (809, 3, '2026-05-21 13:49:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (810, 1, '2026-05-21 13:50:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (811, 2, '2026-05-21 13:50:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (812, 3, '2026-05-21 13:50:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (813, 1, '2026-05-21 13:51:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (814, 2, '2026-05-21 13:51:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (815, 3, '2026-05-21 13:51:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (816, 1, '2026-05-21 13:52:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (817, 2, '2026-05-21 13:52:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (818, 3, '2026-05-21 13:52:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (819, 1, '2026-05-21 13:53:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (820, 2, '2026-05-21 13:53:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (821, 3, '2026-05-21 13:53:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (822, 1, '2026-05-21 13:54:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (823, 2, '2026-05-21 13:54:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (824, 3, '2026-05-21 13:54:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (825, 2, '2026-05-21 13:55:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (826, 3, '2026-05-21 13:55:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (827, 2, '2026-05-21 13:56:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (828, 3, '2026-05-21 13:56:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (829, 2, '2026-05-21 13:57:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (830, 3, '2026-05-21 13:57:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (831, 2, '2026-05-21 13:58:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (832, 3, '2026-05-21 13:58:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (833, 2, '2026-05-21 13:59:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (834, 3, '2026-05-21 13:59:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (835, 2, '2026-05-21 14:00:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (836, 3, '2026-05-21 14:00:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (837, 2, '2026-05-21 14:01:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (838, 3, '2026-05-21 14:01:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (839, 2, '2026-05-21 14:02:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (840, 3, '2026-05-21 14:02:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (841, 2, '2026-05-21 14:03:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (842, 3, '2026-05-21 14:03:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (843, 2, '2026-05-21 14:04:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (844, 3, '2026-05-21 14:04:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (845, 2, '2026-05-21 14:05:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (846, 3, '2026-05-21 14:05:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (847, 2, '2026-05-21 14:06:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (848, 3, '2026-05-21 14:06:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (849, 2, '2026-05-21 14:07:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (850, 3, '2026-05-21 14:07:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (851, 2, '2026-05-21 14:08:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (852, 3, '2026-05-21 14:08:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (853, 2, '2026-05-21 14:09:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (854, 3, '2026-05-21 14:09:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (855, 2, '2026-05-21 14:10:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (856, 3, '2026-05-21 14:10:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (857, 2, '2026-05-21 14:11:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (858, 3, '2026-05-21 14:11:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (859, 2, '2026-05-21 14:12:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (860, 3, '2026-05-21 14:12:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (861, 2, '2026-05-21 14:13:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (862, 3, '2026-05-21 14:13:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (863, 2, '2026-05-21 14:14:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (864, 3, '2026-05-21 14:14:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (865, 2, '2026-05-21 14:15:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (866, 3, '2026-05-21 14:15:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (867, 2, '2026-05-21 14:16:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (868, 3, '2026-05-21 14:16:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (869, 2, '2026-05-21 14:17:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (870, 3, '2026-05-21 14:17:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (871, 2, '2026-05-21 14:18:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (872, 3, '2026-05-21 14:18:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (873, 2, '2026-05-21 14:19:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (874, 3, '2026-05-21 14:19:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (875, 2, '2026-05-21 14:20:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (876, 3, '2026-05-21 14:20:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (877, 2, '2026-05-21 14:21:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (878, 3, '2026-05-21 14:21:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (879, 2, '2026-05-21 14:22:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (880, 3, '2026-05-21 14:22:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (881, 2, '2026-05-21 14:23:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (882, 3, '2026-05-21 14:23:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (883, 2, '2026-05-21 14:24:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (884, 3, '2026-05-21 14:24:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (885, 2, '2026-05-21 14:25:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (886, 3, '2026-05-21 14:25:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (887, 2, '2026-05-21 14:26:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (888, 3, '2026-05-21 14:26:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (889, 2, '2026-05-21 14:27:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (890, 3, '2026-05-21 14:27:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (891, 2, '2026-05-21 14:28:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (892, 3, '2026-05-21 14:28:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (893, 2, '2026-05-21 14:29:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (894, 3, '2026-05-21 14:29:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (895, 2, '2026-05-21 14:30:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (896, 3, '2026-05-21 14:30:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (897, 2, '2026-05-21 14:31:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (898, 3, '2026-05-21 14:31:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (899, 2, '2026-05-21 14:32:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (900, 3, '2026-05-21 14:32:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (901, 2, '2026-05-21 14:33:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (902, 3, '2026-05-21 14:33:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (903, 2, '2026-05-21 14:34:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (904, 3, '2026-05-21 14:34:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (905, 2, '2026-05-21 14:35:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (906, 3, '2026-05-21 14:35:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (907, 2, '2026-05-21 14:36:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (908, 3, '2026-05-21 14:36:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (909, 2, '2026-05-21 14:37:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (910, 3, '2026-05-21 14:37:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (911, 2, '2026-05-21 14:38:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (912, 3, '2026-05-21 14:38:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (913, 2, '2026-05-21 14:39:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (914, 3, '2026-05-21 14:39:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (915, 2, '2026-05-21 14:40:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (916, 3, '2026-05-21 14:40:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (917, 2, '2026-05-21 14:41:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (918, 3, '2026-05-21 14:41:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (919, 2, '2026-05-21 14:42:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (920, 3, '2026-05-21 14:42:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (921, 2, '2026-05-21 14:43:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (922, 3, '2026-05-21 14:43:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (923, 2, '2026-05-21 14:44:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (924, 3, '2026-05-21 14:44:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (925, 2, '2026-05-21 14:45:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (926, 3, '2026-05-21 14:45:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (927, 2, '2026-05-21 14:46:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (928, 3, '2026-05-21 14:46:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (929, 2, '2026-05-21 14:47:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (930, 3, '2026-05-21 14:47:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (931, 2, '2026-05-21 14:48:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (932, 3, '2026-05-21 14:48:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (933, 2, '2026-05-21 14:49:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (934, 3, '2026-05-21 14:49:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (935, 2, '2026-05-21 14:50:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (936, 3, '2026-05-21 14:50:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (937, 2, '2026-05-21 14:51:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (938, 3, '2026-05-21 14:51:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (939, 2, '2026-05-21 14:52:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (940, 3, '2026-05-21 14:52:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (941, 2, '2026-05-21 14:53:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (942, 3, '2026-05-21 14:53:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (943, 2, '2026-05-21 14:54:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (944, 3, '2026-05-21 14:54:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (945, 2, '2026-05-21 14:55:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (946, 3, '2026-05-21 14:55:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (947, 2, '2026-05-21 14:56:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (948, 3, '2026-05-21 14:56:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (949, 2, '2026-05-21 14:57:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (950, 3, '2026-05-21 14:57:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (951, 2, '2026-05-21 14:58:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (952, 3, '2026-05-21 14:58:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (953, 2, '2026-05-21 14:59:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (954, 3, '2026-05-21 14:59:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (955, 2, '2026-05-21 15:00:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (956, 3, '2026-05-21 15:00:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (957, 2, '2026-05-21 15:01:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (958, 3, '2026-05-21 15:01:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (959, 2, '2026-05-21 15:02:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (960, 3, '2026-05-21 15:02:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (961, 2, '2026-05-21 15:03:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (962, 3, '2026-05-21 15:03:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (963, 2, '2026-05-21 15:04:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (964, 3, '2026-05-21 15:04:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (965, 2, '2026-05-21 15:05:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (966, 3, '2026-05-21 15:05:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (967, 2, '2026-05-21 15:06:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (968, 3, '2026-05-21 15:06:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (969, 2, '2026-05-21 15:07:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (970, 3, '2026-05-21 15:07:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (971, 2, '2026-05-21 15:08:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (972, 3, '2026-05-21 15:08:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (973, 2, '2026-05-21 15:09:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (974, 3, '2026-05-21 15:09:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (975, 2, '2026-05-21 15:10:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (976, 3, '2026-05-21 15:10:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (977, 2, '2026-05-21 15:11:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (978, 3, '2026-05-21 15:11:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (979, 2, '2026-05-21 15:12:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (980, 3, '2026-05-21 15:12:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (981, 2, '2026-05-21 15:13:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (982, 3, '2026-05-21 15:13:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (983, 2, '2026-05-21 15:14:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (984, 3, '2026-05-21 15:14:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (985, 2, '2026-05-21 15:15:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (986, 3, '2026-05-21 15:15:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (987, 2, '2026-05-21 15:16:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (988, 3, '2026-05-21 15:16:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (989, 2, '2026-05-21 15:17:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (990, 3, '2026-05-21 15:17:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (991, 2, '2026-05-21 15:18:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (992, 3, '2026-05-21 15:18:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (993, 2, '2026-05-21 15:19:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (994, 3, '2026-05-21 15:19:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (995, 2, '2026-05-21 15:20:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (996, 3, '2026-05-21 15:20:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (997, 2, '2026-05-21 15:21:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (998, 3, '2026-05-21 15:21:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (999, 2, '2026-05-21 15:22:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1000, 3, '2026-05-21 15:22:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1001, 2, '2026-05-21 15:23:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1002, 3, '2026-05-21 15:23:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1003, 2, '2026-05-21 15:24:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1004, 3, '2026-05-21 15:24:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1005, 2, '2026-05-21 15:25:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1006, 3, '2026-05-21 15:25:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1007, 2, '2026-05-21 15:26:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1008, 3, '2026-05-21 15:26:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1009, 2, '2026-05-21 15:27:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1010, 3, '2026-05-21 15:27:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1011, 2, '2026-05-21 15:28:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1012, 3, '2026-05-21 15:28:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1013, 2, '2026-05-21 15:29:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1014, 3, '2026-05-21 15:29:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1015, 2, '2026-05-21 15:30:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1016, 3, '2026-05-21 15:30:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1017, 2, '2026-05-21 15:31:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1018, 3, '2026-05-21 15:31:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1019, 2, '2026-05-21 15:32:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1020, 3, '2026-05-21 15:32:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1021, 2, '2026-05-21 15:33:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1022, 3, '2026-05-21 15:33:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1023, 2, '2026-05-21 15:34:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1024, 3, '2026-05-21 15:34:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1025, 2, '2026-05-21 15:35:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1026, 3, '2026-05-21 15:35:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1027, 2, '2026-05-21 15:36:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1028, 3, '2026-05-21 15:36:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1029, 2, '2026-05-21 15:37:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1030, 3, '2026-05-21 15:37:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1031, 2, '2026-05-21 15:38:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1032, 3, '2026-05-21 15:38:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1033, 2, '2026-05-21 15:39:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1034, 3, '2026-05-21 15:39:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1035, 2, '2026-05-21 15:40:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1036, 3, '2026-05-21 15:40:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1037, 2, '2026-05-21 15:41:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1038, 3, '2026-05-21 15:41:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1039, 2, '2026-05-21 15:42:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1040, 3, '2026-05-21 15:42:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1041, 2, '2026-05-21 15:43:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1042, 3, '2026-05-21 15:43:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1043, 2, '2026-05-21 15:44:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1044, 3, '2026-05-21 15:44:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1045, 2, '2026-05-21 15:45:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1046, 3, '2026-05-21 15:45:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1047, 2, '2026-05-21 15:46:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1048, 3, '2026-05-21 15:46:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1049, 2, '2026-05-21 15:47:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1050, 3, '2026-05-21 15:47:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1051, 2, '2026-05-21 15:48:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1052, 3, '2026-05-21 15:48:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1053, 2, '2026-05-21 15:49:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1054, 3, '2026-05-21 15:49:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1055, 2, '2026-05-21 15:50:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1056, 3, '2026-05-21 15:50:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1057, 2, '2026-05-21 15:51:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1058, 3, '2026-05-21 15:51:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1059, 2, '2026-05-21 15:52:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1060, 3, '2026-05-21 15:52:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1061, 2, '2026-05-21 15:53:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1062, 3, '2026-05-21 15:53:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1063, 2, '2026-05-21 15:54:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1064, 3, '2026-05-21 15:54:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1065, 2, '2026-05-21 15:55:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1066, 3, '2026-05-21 15:55:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1067, 2, '2026-05-21 15:56:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1068, 3, '2026-05-21 15:56:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1069, 2, '2026-05-21 15:57:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1070, 3, '2026-05-21 15:57:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1071, 2, '2026-05-21 15:58:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1072, 3, '2026-05-21 15:58:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1073, 2, '2026-05-21 15:59:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1074, 3, '2026-05-21 15:59:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1075, 2, '2026-05-21 16:00:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1076, 3, '2026-05-21 16:00:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1077, 2, '2026-05-21 16:01:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1078, 3, '2026-05-21 16:01:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1079, 2, '2026-05-21 16:02:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1080, 3, '2026-05-21 16:02:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1081, 2, '2026-05-21 16:03:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1082, 3, '2026-05-21 16:03:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1083, 2, '2026-05-21 16:04:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1084, 3, '2026-05-21 16:04:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1085, 2, '2026-05-21 16:05:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1086, 3, '2026-05-21 16:05:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1087, 2, '2026-05-21 16:06:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1088, 3, '2026-05-21 16:06:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1089, 2, '2026-05-21 16:07:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1090, 3, '2026-05-21 16:07:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1091, 2, '2026-05-21 16:08:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1092, 3, '2026-05-21 16:08:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1093, 2, '2026-05-21 16:09:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1094, 3, '2026-05-21 16:09:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1095, 2, '2026-05-21 16:10:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1096, 3, '2026-05-21 16:10:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1097, 2, '2026-05-21 16:11:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1098, 3, '2026-05-21 16:11:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1099, 2, '2026-05-21 16:12:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1100, 3, '2026-05-21 16:12:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1101, 2, '2026-05-21 16:13:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1102, 3, '2026-05-21 16:13:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1103, 2, '2026-05-21 16:14:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1104, 3, '2026-05-21 16:14:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1105, 2, '2026-05-21 16:15:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1106, 3, '2026-05-21 16:15:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1107, 2, '2026-05-21 16:16:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1108, 3, '2026-05-21 16:16:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1109, 2, '2026-05-21 16:17:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1110, 3, '2026-05-21 16:17:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1111, 2, '2026-05-21 16:18:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1112, 3, '2026-05-21 16:18:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1113, 2, '2026-05-21 16:19:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1114, 3, '2026-05-21 16:19:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1115, 2, '2026-05-21 16:20:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1116, 3, '2026-05-21 16:20:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1117, 2, '2026-05-21 16:21:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1118, 3, '2026-05-21 16:21:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1119, 2, '2026-05-21 16:22:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1120, 3, '2026-05-21 16:22:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1121, 2, '2026-05-21 16:23:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1122, 3, '2026-05-21 16:23:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1123, 2, '2026-05-21 16:24:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1124, 3, '2026-05-21 16:24:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1125, 2, '2026-05-21 16:25:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1126, 3, '2026-05-21 16:25:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1127, 2, '2026-05-21 16:26:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1128, 3, '2026-05-21 16:26:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1129, 2, '2026-05-21 16:27:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1130, 3, '2026-05-21 16:27:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1131, 2, '2026-05-21 16:28:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1132, 3, '2026-05-21 16:28:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1133, 2, '2026-05-21 16:29:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1134, 3, '2026-05-21 16:29:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1135, 2, '2026-05-21 16:30:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1136, 3, '2026-05-21 16:30:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1137, 2, '2026-05-21 16:31:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1138, 3, '2026-05-21 16:31:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1139, 2, '2026-05-21 16:32:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1140, 3, '2026-05-21 16:32:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1141, 2, '2026-05-21 16:33:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1142, 3, '2026-05-21 16:33:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1143, 2, '2026-05-21 16:34:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1144, 3, '2026-05-21 16:34:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1145, 2, '2026-05-21 16:35:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1146, 3, '2026-05-21 16:35:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1147, 2, '2026-05-21 16:36:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1148, 3, '2026-05-21 16:36:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1149, 2, '2026-05-21 16:37:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1150, 3, '2026-05-21 16:37:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1151, 2, '2026-05-21 16:38:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1152, 3, '2026-05-21 16:38:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1153, 2, '2026-05-21 16:39:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1154, 3, '2026-05-21 16:39:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1155, 2, '2026-05-21 16:40:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1156, 3, '2026-05-21 16:40:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1157, 2, '2026-05-21 16:41:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1158, 3, '2026-05-21 16:41:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1159, 2, '2026-05-21 16:42:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1160, 3, '2026-05-21 16:42:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1161, 2, '2026-05-21 16:43:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1162, 3, '2026-05-21 16:43:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1163, 2, '2026-05-21 16:44:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1164, 3, '2026-05-21 16:44:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1165, 2, '2026-05-21 16:45:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1166, 3, '2026-05-21 16:45:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1167, 2, '2026-05-21 16:46:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1168, 3, '2026-05-21 16:46:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1169, 2, '2026-05-21 16:47:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1170, 3, '2026-05-21 16:47:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1171, 2, '2026-05-21 16:48:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1172, 3, '2026-05-21 16:48:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1173, 1, '2026-05-28 11:15:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1174, 2, '2026-05-28 11:15:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1175, 2, '2026-05-28 11:16:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1176, 3, '2026-05-28 11:16:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1177, 2, '2026-05-28 18:25:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1178, 2, '2026-05-28 18:26:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1179, 2, '2026-05-28 18:27:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1180, 2, '2026-05-28 18:28:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1181, 2, '2026-05-28 18:29:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1182, 2, '2026-05-28 18:30:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1183, 2, '2026-05-28 18:31:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1184, 2, '2026-05-28 18:32:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1185, 2, '2026-05-28 18:33:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1186, 1, '2026-05-28 18:34:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1187, 2, '2026-05-28 18:34:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1188, 1, '2026-05-28 18:35:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1189, 1, '2026-05-28 18:36:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1190, 1, '2026-05-28 18:37:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1191, 1, '2026-05-28 18:38:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1192, 1, '2026-05-28 18:39:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1193, 1, '2026-05-28 18:40:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1194, 1, '2026-05-28 18:41:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1195, 3, '2026-05-28 18:41:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1196, 1, '2026-05-28 18:42:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1197, 1, '2026-05-28 18:43:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1198, 1, '2026-05-28 18:44:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1199, 1, '2026-05-28 18:45:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1200, 1, '2026-05-28 18:46:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1201, 1, '2026-05-28 18:47:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1202, 1, '2026-05-28 18:48:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1203, 1, '2026-05-28 18:49:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1204, 2, '2026-05-28 18:50:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1205, 2, '2026-07-01 11:10:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1206, 2, '2026-07-01 11:11:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1207, 3, '2026-07-01 11:11:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1208, 3, '2026-07-01 11:12:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1209, 3, '2026-07-01 11:13:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1210, 3, '2026-07-01 11:14:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1211, 3, '2026-07-01 11:15:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1212, 3, '2026-07-01 11:16:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1213, 3, '2026-07-01 11:17:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1214, 3, '2026-07-01 11:18:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1215, 2, '2026-07-01 11:19:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1216, 3, '2026-07-01 11:19:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1217, 2, '2026-07-01 11:20:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1218, 2, '2026-07-01 11:21:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1219, 2, '2026-07-01 11:22:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1220, 2, '2026-07-01 11:23:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1221, 2, '2026-07-01 11:24:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1222, 2, '2026-07-01 11:25:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1223, 2, '2026-07-01 11:26:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1224, 2, '2026-07-01 11:27:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1225, 2, '2026-07-01 11:28:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1226, 2, '2026-07-01 11:29:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1227, 2, '2026-07-01 11:30:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1228, 2, '2026-07-01 11:31:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1229, 2, '2026-07-01 11:32:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1230, 4, '2026-07-01 11:32:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1231, 2, '2026-07-01 11:33:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1232, 4, '2026-07-01 11:33:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1233, 2, '2026-07-01 11:34:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1234, 2, '2026-07-01 11:35:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1235, 2, '2026-07-01 11:36:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1236, 2, '2026-07-02 01:22:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1237, 4, '2026-07-02 01:22:00', 0, 0, 0.00);

-- ----------------------------
-- Table structure for lp_room_play_task
-- ----------------------------
DROP TABLE IF EXISTS `lp_room_play_task`;
CREATE TABLE `lp_room_play_task`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `room_id` bigint(20) UNSIGNED NOT NULL COMMENT '房间ID',
  `task_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '任务类型:public interaction privilege',
  `ref_task_id` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '关联任务ID',
  `mode` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '播放模式',
  `priority` int(11) NOT NULL DEFAULT 0 COMMENT '优先级',
  `status` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '状态',
  `scheduled_at` datetime NULL DEFAULT NULL COMMENT '调度时间',
  `started_at` datetime NULL DEFAULT NULL COMMENT '开始时间',
  `ended_at` datetime NULL DEFAULT NULL COMMENT '结束时间',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_room_status_priority`(`room_id` ASC, `status` ASC, `priority` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '房间播放任务' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_room_play_task
-- ----------------------------

-- ----------------------------
-- Table structure for lp_room_state_snapshot
-- ----------------------------
DROP TABLE IF EXISTS `lp_room_state_snapshot`;
CREATE TABLE `lp_room_state_snapshot`  (
  `room_id` bigint(20) UNSIGNED NOT NULL COMMENT '房间ID',
  `current_state` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public_ready' COMMENT '当前状态',
  `current_mode` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public' COMMENT '当前模式:public interaction privilege',
  `current_task_id` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '当前任务ID',
  `privilege_expire_at` datetime NULL DEFAULT NULL COMMENT '特权过期时间',
  `version` int(11) NOT NULL DEFAULT 1 COMMENT '乐观锁版本',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`room_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '房间状态快照' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_room_state_snapshot
-- ----------------------------

-- ----------------------------
-- Table structure for lp_room_switch_task
-- ----------------------------
DROP TABLE IF EXISTS `lp_room_switch_task`;
CREATE TABLE `lp_room_switch_task`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `task_no` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '切换任务号',
  `room_id` bigint(20) UNSIGNED NOT NULL COMMENT '房间ID',
  `trigger_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '触发类型:gift timeout system',
  `trigger_ref_id` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '触发引用ID',
  `from_mode` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '原模式',
  `to_mode` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '目标模式',
  `duration_sec` int(11) NOT NULL DEFAULT 0 COMMENT '持续时长',
  `status` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '状态',
  `scheduled_at` datetime NULL DEFAULT NULL COMMENT '调度时间',
  `started_at` datetime NULL DEFAULT NULL COMMENT '开始时间',
  `ended_at` datetime NULL DEFAULT NULL COMMENT '结束时间',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_switch_task_no`(`task_no` ASC) USING BTREE,
  INDEX `idx_room_status_scheduled`(`room_id` ASC, `status` ASC, `scheduled_at` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '房间切换任务' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_room_switch_task
-- ----------------------------

-- ----------------------------
-- Table structure for lp_room_tag
-- ----------------------------
DROP TABLE IF EXISTS `lp_room_tag`;
CREATE TABLE `lp_room_tag`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `room_id` bigint(20) UNSIGNED NOT NULL COMMENT '房间ID',
  `tag_name` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '标签名',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_room_tag`(`room_id` ASC, `tag_name` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 35 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '房间标签' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_room_tag
-- ----------------------------
INSERT INTO `lp_room_tag` VALUES (21, 1, '情感');
INSERT INTO `lp_room_tag` VALUES (22, 1, '热门');
INSERT INTO `lp_room_tag` VALUES (23, 2, '放松');
INSERT INTO `lp_room_tag` VALUES (24, 2, '轻音乐');
INSERT INTO `lp_room_tag` VALUES (33, 3, '专注');
INSERT INTO `lp_room_tag` VALUES (34, 3, '学习');
INSERT INTO `lp_room_tag` VALUES (27, 4, '测试');
INSERT INTO `lp_room_tag` VALUES (28, 4, '联调');

-- ----------------------------
-- Table structure for lp_stream_template
-- ----------------------------
DROP TABLE IF EXISTS `lp_stream_template`;
CREATE TABLE `lp_stream_template`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `template_code` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '模板编码',
  `webrtc_app` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'live' COMMENT 'SRS app',
  `stream_alias_prefix` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'room' COMMENT '逻辑流前缀',
  `auth_required` tinyint(4) NOT NULL DEFAULT 1 COMMENT '是否鉴权',
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '状态',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_stream_template_code`(`template_code` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '流模板' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_stream_template
-- ----------------------------
INSERT INTO `lp_stream_template` VALUES (1, 'default_live', 'live', 'room', 1, 1);

-- ----------------------------
-- Table structure for lp_user
-- ----------------------------
DROP TABLE IF EXISTS `lp_user`;
CREATE TABLE `lp_user`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_no` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '用户编号',
  `nickname` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '昵称',
  `email` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '邮箱',
  `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '头像',
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '状态:0禁用 1正常',
  `level` int(11) NOT NULL DEFAULT 1 COMMENT '用户等级',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_user_no`(`user_no` ASC) USING BTREE,
  INDEX `idx_status`(`status` ASC) USING BTREE,
  INDEX `idx_email`(`email` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 12 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '用户主表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_user
-- ----------------------------
INSERT INTO `lp_user` VALUES (1, 'U202605170158154714', 'TestUser', '', '', 1, 1, '2026-05-17 01:58:15', '2026-05-17 01:58:15');
INSERT INTO `lp_user` VALUES (2, 'U202605170210264299', 'Test002', '', '', 1, 1, '2026-05-17 02:10:26', '2026-05-17 02:10:26');
INSERT INTO `lp_user` VALUES (3, 'U202605170226285230', 'Test004', '', '', 1, 1, '2026-05-17 02:26:28', '2026-05-17 02:26:28');
INSERT INTO `lp_user` VALUES (4, 'U202605170929394765', 'User092939', '', '', 1, 1, '2026-05-17 09:29:39', '2026-05-17 09:29:39');
INSERT INTO `lp_user` VALUES (5, 'U202605170931033603', 'User093103', '', '', 1, 1, '2026-05-17 09:31:03', '2026-05-17 09:31:03');
INSERT INTO `lp_user` VALUES (6, 'U202605170932304243', 'User093230_new', '', '', 1, 1, '2026-05-17 09:32:30', '2026-05-17 09:32:31');
INSERT INTO `lp_user` VALUES (7, 'U202605170932573978', 'User093257_new', '', '', 1, 1, '2026-05-17 09:32:57', '2026-05-17 09:32:58');
INSERT INTO `lp_user` VALUES (8, 'U202607020129306435', 'testuser1', 'test1@test.com', '', 1, 1, '2026-07-02 01:29:30', '2026-07-02 01:29:30');
INSERT INTO `lp_user` VALUES (9, 'U202607020132151682', 'ever2', '1033022842@qq.com', '', 1, 1, '2026-07-02 01:32:15', '2026-07-02 01:32:15');
INSERT INTO `lp_user` VALUES (10, 'U202607020136098144', 'testerquick', 'tq@test.com', '', 1, 1, '2026-07-02 01:36:09', '2026-07-02 01:36:09');
INSERT INTO `lp_user` VALUES (11, 'U202607041639195142', 'ever', '1033022843@qq.com', '', 1, 1, '2026-07-04 16:39:19', '2026-07-04 16:39:19');

-- ----------------------------
-- Table structure for lp_user_auth
-- ----------------------------
DROP TABLE IF EXISTS `lp_user_auth`;
CREATE TABLE `lp_user_auth`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id` bigint(20) UNSIGNED NOT NULL COMMENT '用户ID',
  `auth_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '登录方式:mobile email third_party',
  `auth_key` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '手机号/邮箱/第三方唯一标识',
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '密码哈希',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_auth_type_key`(`auth_type` ASC, `auth_key` ASC) USING BTREE,
  INDEX `idx_user_id`(`user_id` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 12 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '用户认证表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_user_auth
-- ----------------------------
INSERT INTO `lp_user_auth` VALUES (1, 1, 'username', 'test001', '$2y$10$wL6/F89QOCUBwiXsc7uFHOZ1y.QM0XPzFumNgOT8OMgQJWGFy0UWu', '2026-05-17 01:58:15');
INSERT INTO `lp_user_auth` VALUES (2, 2, 'username', 'test002', '$2y$10$dLCXXLvFsiPvT6voe4NCteNWJE29cNjxWMm87h/O6BbnzDGTwgfD2', '2026-05-17 02:10:26');
INSERT INTO `lp_user_auth` VALUES (3, 3, 'username', 'test004', '$2y$10$AlfdAvL2CKgAKBJpvVz1aeL/dktJ6Dgz5dX3ZTTzgo6SfhpWNjojW', '2026-05-17 02:26:28');
INSERT INTO `lp_user_auth` VALUES (4, 4, 'username', 'test092939', '$2y$10$LlojAq7cZfKOxf27GIhB5.vrKdu7XhuoZ2pvIkOFEgNW8Rie2w04O', '2026-05-17 09:29:39');
INSERT INTO `lp_user_auth` VALUES (5, 5, 'username', 'test093103', '$2y$10$PNuAzLN3ychQa5NJXmyAneba13phnHIrmUb/XXXdnXuVbapuEwraC', '2026-05-17 09:31:03');
INSERT INTO `lp_user_auth` VALUES (6, 6, 'username', 'test093230', '$2y$10$XZpGhGqMrVj5ZTTW7gbYZukbzNqo4F3zT/O5MEyfca9tj4jZ1vYRi', '2026-05-17 09:32:31');
INSERT INTO `lp_user_auth` VALUES (7, 7, 'username', 'test093257', '$2y$10$coBv3QNnnS5OM5G86wtrc.AtiOChKsYjSYypcP./CLuR2og/x5VbS', '2026-05-17 09:32:58');
INSERT INTO `lp_user_auth` VALUES (8, 8, 'email', 'test1@test.com', '$2y$10$n7KK5dgwv1Eukz8R2Nt9Q.0B0NOV8jAochI.zVtm8xBO3YS/OfoKC', '2026-07-02 01:29:31');
INSERT INTO `lp_user_auth` VALUES (9, 9, 'email', '1033022842@qq.com', '$2y$10$qVzGGEYwubV28SwHgJACgetFQTQPIeCfovjaSqVBL5NXrl7bMS4T2', '2026-07-02 01:32:15');
INSERT INTO `lp_user_auth` VALUES (10, 10, 'email', 'tq@test.com', '$2y$10$jZPUQfIzQy6w.h/xB5ZccuGW8gi82NTi8RcM2LX3cWGAukxSzdXDe', '2026-07-02 01:36:09');
INSERT INTO `lp_user_auth` VALUES (11, 11, 'email', '1033022843@qq.com', '$2y$10$XylGrCqNibNj6k96rJAqYuaqQXm/C/8uFFTv1Yc3r78GiDbFGZS/.', '2026-07-04 16:39:19');

-- ----------------------------
-- Table structure for lp_user_device
-- ----------------------------
DROP TABLE IF EXISTS `lp_user_device`;
CREATE TABLE `lp_user_device`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id` bigint(20) UNSIGNED NOT NULL COMMENT '用户ID',
  `device_id` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '设备ID',
  `platform` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'web' COMMENT '平台',
  `app_version` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '应用版本',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_user_device`(`user_id` ASC, `device_id` ASC) USING BTREE,
  INDEX `idx_device_id`(`device_id` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '用户设备表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_user_device
-- ----------------------------

-- ----------------------------
-- Table structure for lp_user_profile
-- ----------------------------
DROP TABLE IF EXISTS `lp_user_profile`;
CREATE TABLE `lp_user_profile`  (
  `user_id` bigint(20) UNSIGNED NOT NULL COMMENT '用户ID',
  `gender` tinyint(4) NOT NULL DEFAULT 0 COMMENT '性别:0未知 1男 2女',
  `bio` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '简介',
  `country_code` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '国家区号',
  `last_login_ip` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '最近登录IP',
  `last_login_at` datetime NULL DEFAULT NULL COMMENT '最近登录时间',
  PRIMARY KEY (`user_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '用户扩展资料' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_user_profile
-- ----------------------------
INSERT INTO `lp_user_profile` VALUES (1, 0, '', '', '127.0.0.1', '2026-05-17 01:58:38');
INSERT INTO `lp_user_profile` VALUES (2, 0, '', '', '127.0.0.1', '2026-05-17 02:10:40');
INSERT INTO `lp_user_profile` VALUES (3, 0, '', '', '127.0.0.1', '2026-05-17 10:34:30');
INSERT INTO `lp_user_profile` VALUES (4, 0, '', '', '127.0.0.1', '2026-05-17 09:29:39');
INSERT INTO `lp_user_profile` VALUES (5, 0, '', '', '127.0.0.1', '2026-05-17 09:31:03');
INSERT INTO `lp_user_profile` VALUES (6, 1, 'hello', '', '127.0.0.1', '2026-05-17 09:32:31');
INSERT INTO `lp_user_profile` VALUES (7, 1, 'hello', '', '127.0.0.1', '2026-05-17 09:32:58');
INSERT INTO `lp_user_profile` VALUES (8, 0, '', '', '127.0.0.1', '2026-07-02 01:29:31');
INSERT INTO `lp_user_profile` VALUES (9, 0, '', '', '127.0.0.1', '2026-07-02 01:51:27');
INSERT INTO `lp_user_profile` VALUES (10, 0, '', '', '127.0.0.1', '2026-07-02 01:36:09');
INSERT INTO `lp_user_profile` VALUES (11, 0, '', '', '127.0.0.1', '2026-07-04 16:39:34');

-- ----------------------------
-- Table structure for lp_wallet_account
-- ----------------------------
DROP TABLE IF EXISTS `lp_wallet_account`;
CREATE TABLE `lp_wallet_account`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id` bigint(20) UNSIGNED NOT NULL COMMENT '用户ID',
  `diamond_balance` decimal(18, 2) NOT NULL DEFAULT 0.00 COMMENT '钻石余额',
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '状态:0冻结 1正常',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_wallet_user_id`(`user_id` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '钱包账户' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_wallet_account
-- ----------------------------

-- ----------------------------
-- Table structure for lp_wallet_ledger
-- ----------------------------
DROP TABLE IF EXISTS `lp_wallet_ledger`;
CREATE TABLE `lp_wallet_ledger`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id` bigint(20) UNSIGNED NOT NULL COMMENT '用户ID',
  `biz_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '业务类型:recharge gift refund adjust',
  `direction` tinyint(4) NOT NULL COMMENT '方向:1收入 2支出',
  `asset_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'diamond' COMMENT '资产类型,固定为diamond',
  `amount` decimal(18, 2) NOT NULL COMMENT '变动金额',
  `balance_before` decimal(18, 2) NOT NULL COMMENT '变动前余额',
  `balance_after` decimal(18, 2) NOT NULL COMMENT '变动后余额',
  `biz_id` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '业务ID',
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '备注',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_user_created`(`user_id` ASC, `created_at` ASC) USING BTREE,
  INDEX `idx_biz_type_biz_id`(`biz_type` ASC, `biz_id` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '钱包流水' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_wallet_ledger
-- ----------------------------

SET FOREIGN_KEY_CHECKS = 1;
