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

 Date: 06/07/2026 15:15:48
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

SET FOREIGN_KEY_CHECKS = 1;
