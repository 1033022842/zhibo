/*
 Navicat Premium Data Transfer

 Source Server         : localhost
 Source Server Type    : MySQL
 Source Server Version : 80012
 Source Host           : localhost:3306
 Source Schema         : live_platform

 Target Server Type    : MySQL
 Target Server Version : 80012
 File Encoding         : 65001

 Date: 07/08/2026 14:26:09
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
  UNIQUE INDEX `username`(`username`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '管理员表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of ba_admin
-- ----------------------------
INSERT INTO `ba_admin` VALUES (1, 'admin', 'Admin', '', 'admin@buildadmin.com', '18888888888', 0, 1786079815, '127.0.0.1', '$2y$10$b/w7wjNIymPfjY62LTInBuelicvFAjdMLXNUJhrd7ZvI/ckt3FQDm', '', '', 'enable', 1786079815, 1778942775);

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
) ENGINE = InnoDB AUTO_INCREMENT = 5 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '管理分组表' ROW_FORMAT = Dynamic;

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
  INDEX `uid`(`uid`) USING BTREE,
  INDEX `group_id`(`group_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '管理分组映射表' ROW_FORMAT = Dynamic;

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
) ENGINE = InnoDB AUTO_INCREMENT = 328 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '管理员日志表' ROW_FORMAT = Dynamic;

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
INSERT INTO `ba_admin_log` VALUES (46, 1, 'admin', '/admin/Index/login', '登录', '{\"username\":\"admin\",\"password\":\"***\",\"keep\":\"\",\"captchaId\":\"d5b25089-4650-4fe1-b82d-25b7ac971b2c\",\"captchaInfo\":\"266,120-40,105;350;200\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1783322832);
INSERT INTO `ba_admin_log` VALUES (47, 1, 'admin', '/admin/live.MerchantCertification/approve?id=1', '商家认证审核-未知(approve)', '{\"id\":\"1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1783339964);
INSERT INTO `ba_admin_log` VALUES (48, 1, 'admin', '/admin/Index/login', '登录', '{\"username\":\"admin\",\"password\":\"***\",\"keep\":\"\",\"captchaId\":\"d0554da9-9b5f-4390-9a16-e218921fe022\",\"captchaInfo\":\"221,83-142,98;350;200\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1784959866);
INSERT INTO `ba_admin_log` VALUES (49, 1, 'admin', '/admin/live.MerchantCertification/approve?id=2', '商家认证审核-未知(approve)', '{\"id\":\"2\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1784963329);
INSERT INTO `ba_admin_log` VALUES (50, 0, 'admin', '/admin/Index/login', '登录', '{\"username\":\"admin\",\"password\":\"***\",\"keep\":\"\",\"captchaId\":\"c6cc1f8c-9d62-41a2-b945-d65abffb84fc\",\"captchaInfo\":\"225,100-269,52;350;200\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.107.1 Chrome/142.0.7444.235 Electron/39.2.7 Safari/537.36', 1785162972);
INSERT INTO `ba_admin_log` VALUES (51, 0, 'admin', '/admin/Index/login', '登录', '{\"username\":\"admin\",\"password\":\"***\",\"keep\":\"\",\"captchaId\":\"c6cc1f8c-9d62-41a2-b945-d65abffb84fc\",\"captchaInfo\":\"83,26-216,138;350;200\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.107.1 Chrome/142.0.7444.235 Electron/39.2.7 Safari/537.36', 1785163042);
INSERT INTO `ba_admin_log` VALUES (52, 1, 'admin', '/admin/Index/login', '登录', '{\"username\":\"admin\",\"password\":\"***\",\"keep\":\"\",\"captchaId\":\"c6cc1f8c-9d62-41a2-b945-d65abffb84fc\",\"captchaInfo\":\"193,64-294,126;350;200\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.107.1 Chrome/142.0.7444.235 Electron/39.2.7 Safari/537.36', 1785163063);
INSERT INTO `ba_admin_log` VALUES (53, 1, 'admin', '/admin/Index/login', '登录', '{\"username\":\"admin\",\"password\":\"***\",\"keep\":\"\",\"captchaId\":\"1d1ab15e-2919-4f3c-882b-4197c0cba3a6\",\"captchaInfo\":\"230,81-272,41;350;200\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785221287);
INSERT INTO `ba_admin_log` VALUES (54, 1, 'admin', '/admin/live.Gift/edit', '礼物管理-编辑', '{\"id\":\"2\",\"gift_code\":\"vip_30s\",\"name\":\"\\u4e13\\u5c5e\\u793c\\u7269\",\"price_diamond\":\"199.00\",\"trigger_mode\":\"privilege\",\"trigger_duration_sec\":\"30\",\"effect_code\":\"vip_effect\",\"status\":\"1\",\"created_at\":\"2026-05-17 00:01:21\",\"keyword\":null}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785222360);
INSERT INTO `ba_admin_log` VALUES (55, 1, 'admin', '/admin/live.Gift/edit', '礼物管理-编辑', '{\"id\":\"2\",\"gift_code\":\"vip_30s\",\"name\":\"\\u4e13\\u5c5e\\u793c\\u7269\",\"price_diamond\":\"199.00\",\"trigger_mode\":\"privilege\",\"trigger_duration_sec\":\"30\",\"effect_code\":\"vip_effect\",\"status\":\"1\",\"created_at\":\"2026-05-17 00:01:21\",\"keyword\":\"\\u5f00\\u5fc3\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785222574);
INSERT INTO `ba_admin_log` VALUES (56, 1, 'admin', '/admin/live.Gift/edit', '礼物管理-编辑', '{\"id\":\"1\",\"gift_code\":\"rose\",\"name\":\"\\u73ab\\u7470\",\"price_diamond\":\"10.00\",\"trigger_mode\":\"none\",\"trigger_duration_sec\":\"0\",\"effect_code\":\"rose_effect\",\"status\":\"1\",\"created_at\":\"2026-05-17 00:01:21\",\"keyword\":\"\\u4fa7\\u989c\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785222588);
INSERT INTO `ba_admin_log` VALUES (57, 1, 'admin', '/admin/live.Room/add', '房间管理-新增', '{\"room_type\":\"live\",\"status\":\"1\",\"sort\":\"0\",\"tag_names\":\"\",\"asset_ids\":[\"388\",\"384\",\"386\",\"387\",\"385\",\"383\",\"382\",\"381\",\"380\",\"379\"],\"persona_id\":\"4\",\"room_no\":\"111\",\"title\":\"111\",\"subtitle\":\"1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785228704);
INSERT INTO `ba_admin_log` VALUES (58, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"\",\"asset_ids\":[\"388\",\"387\",\"386\",\"385\",\"384\",\"383\",\"382\",\"381\",\"380\",\"379\"],\"playlist_name\":\"\",\"id\":\"5\",\"room_no\":\"R202607062055320610\",\"title\":\"ala\\u7684\\u76f4\\u64ad\\u95f4\",\"subtitle\":\"\",\"persona_id\":\"8\",\"room_type\":\"live\",\"status\":\"1\",\"cover_url\":\"http:\\/\\/127.0.0.1:8000\\/storage\\/ai\\/20260706\\/abg3eee9a61cd0efe5b435b3cb05c93f02be77fd3ad.png\",\"sort\":\"0\",\"created_at\":\"2026-07-06 20:55:32\",\"updated_at\":\"2026-07-06 20:55:32\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785228731);
INSERT INTO `ba_admin_log` VALUES (59, 1, 'admin', '/admin/ajax/upload?uuid=01f52da4-f81a-4c49-b46d-fd9c8e07eaa5', '上传文件', '{\"uuid\":\"01f52da4-f81a-4c49-b46d-fd9c8e07eaa5\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785229179);
INSERT INTO `ba_admin_log` VALUES (60, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"\",\"asset_ids\":[\"388\",\"384\",\"386\",\"387\",\"385\",\"383\",\"382\",\"381\",\"380\",\"379\"],\"playlist_name\":\"111\\u64ad\\u5355\",\"id\":\"8\",\"room_no\":\"111\",\"title\":\"111\",\"subtitle\":\"1\",\"persona_id\":\"4\",\"room_type\":\"live\",\"status\":\"1\",\"cover_url\":\"\\/storage\\/default\\/20260728\\/170212328aeddda760d71ac05e15158904817cc316339ab.png\",\"sort\":\"0\",\"created_at\":\"2026-07-28 16:51:44\",\"updated_at\":\"2026-07-28 16:51:44\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785229181);
INSERT INTO `ba_admin_log` VALUES (61, 1, 'admin', '/admin/auth.Rule/del?ids%5B%5D=126', '菜单规则管理-删除', '{\"ids\":[\"126\"]}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785232192);
INSERT INTO `ba_admin_log` VALUES (62, 1, 'admin', '/admin/auth.Rule/del?ids%5B%5D=127', '菜单规则管理-删除', '{\"ids\":[\"127\"]}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785232198);
INSERT INTO `ba_admin_log` VALUES (63, 1, 'admin', '/admin/Index/login', '登录', '{\"username\":\"admin\",\"password\":\"***\",\"keep\":\"\",\"captchaId\":\"e82c0a8e-e886-4292-91dd-291545f3abee\",\"captchaInfo\":\"118,54-164,62;350;200\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785330939);
INSERT INTO `ba_admin_log` VALUES (64, 1, 'admin', '/admin/live.Gift/edit', '礼物管理-编辑', '{\"id\":\"2\",\"gift_code\":\"vip_30s\",\"name\":\"\\u4e13\\u5c5e\\u793c\\u7269\",\"price_diamond\":\"199.00\",\"trigger_mode\":\"keyword\",\"trigger_duration_sec\":\"5\",\"effect_code\":\"vip_effect\",\"status\":\"1\",\"created_at\":\"2026-05-17 00:01:21\",\"keyword\":\"\\u5f00\\u5fc3\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785330982);
INSERT INTO `ba_admin_log` VALUES (65, 1, 'admin', '/admin/live.Gift/edit', '礼物管理-编辑', '{\"id\":\"1\",\"gift_code\":\"rose\",\"name\":\"\\u73ab\\u7470\",\"price_diamond\":\"10.00\",\"trigger_mode\":\"keyword\",\"trigger_duration_sec\":\"5\",\"effect_code\":\"rose_effect\",\"status\":\"1\",\"created_at\":\"2026-05-17 00:01:21\",\"keyword\":\"\\u5bb3\\u7f9e\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785330998);
INSERT INTO `ba_admin_log` VALUES (66, 1, 'admin', '/admin/Index/login', '登录', '{\"username\":\"admin\",\"password\":\"***\",\"keep\":\"\",\"captchaId\":\"bc33288c-ca90-4032-9a40-979546b9b61d\",\"captchaInfo\":\"239,134-83,53;350;200\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785399420);
INSERT INTO `ba_admin_log` VALUES (67, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"\\u966a\\u4f34\",\"asset_ids\":[\"388\",\"387\",\"386\",\"385\",\"384\",\"383\",\"382\",\"381\",\"380\",\"379\",\"376\",\"375\",\"374\",\"373\",\"372\",\"371\",\"370\",\"369\",\"366\",\"365\",\"364\",\"363\",\"362\",\"361\",\"360\",\"359\"],\"playlist_name\":\"\",\"id\":\"7\",\"room_no\":\"R202607281443360064\",\"title\":\"111\\u7684\\u76f4\\u64ad\\u95f4\",\"subtitle\":\"\",\"persona_id\":\"10\",\"room_type\":\"live\",\"status\":\"1\",\"cover_url\":\"http:\\/\\/127.0.0.1:8000\\/storage\\/ai\\/20260728\\/170212328aeddda760d71ac05e15158904817cc316339ab.png\",\"sort\":\"0\",\"created_at\":\"2026-07-28 14:43:36\",\"updated_at\":\"2026-07-28 14:43:36\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785401775);
INSERT INTO `ba_admin_log` VALUES (68, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"22\",\"asset_ids\":[\"8\",\"7\",\"6\",\"5\",\"4\",\"3\",\"2\",\"1\",\"18\",\"17\",\"16\",\"15\",\"14\",\"13\"],\"playlist_name\":\"\",\"id\":\"6\",\"room_no\":\"R202607062104058298\",\"title\":\"ala\\u7684\\u76f4\\u64ad\\u95f4\",\"subtitle\":\"\",\"persona_id\":\"9\",\"room_type\":\"live\",\"status\":\"2\",\"cover_url\":\"http:\\/\\/127.0.0.1:8000\\/storage\\/ai\\/20260706\\/abg3eee9a61cd0efe5b435b3cb05c93f02be77fd3ad.png\",\"sort\":\"0\",\"created_at\":\"2026-07-06 21:04:05\",\"updated_at\":\"2026-07-06 21:04:05\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785401800);
INSERT INTO `ba_admin_log` VALUES (69, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"22\",\"asset_ids\":[\"8\",\"7\",\"6\",\"5\",\"4\",\"3\",\"2\",\"1\",\"18\",\"17\",\"16\",\"15\",\"14\",\"13\"],\"playlist_name\":\"ala\\u7684\\u76f4\\u64ad\\u95f4\\u64ad\\u5355\",\"id\":\"6\",\"room_no\":\"R202607062104058298\",\"title\":\"ala\\u7684\\u76f4\\u64ad\\u95f4\",\"subtitle\":\"\",\"persona_id\":\"9\",\"room_type\":\"live\",\"status\":\"1\",\"cover_url\":\"http:\\/\\/127.0.0.1:8000\\/storage\\/ai\\/20260706\\/abg3eee9a61cd0efe5b435b3cb05c93f02be77fd3ad.png\",\"sort\":\"0\",\"created_at\":\"2026-07-06 21:04:05\",\"updated_at\":\"2026-07-06 21:04:05\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785401807);
INSERT INTO `ba_admin_log` VALUES (70, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785401935);
INSERT INTO `ba_admin_log` VALUES (71, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785401938);
INSERT INTO `ba_admin_log` VALUES (72, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"5\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785401940);
INSERT INTO `ba_admin_log` VALUES (73, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785401942);
INSERT INTO `ba_admin_log` VALUES (74, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"\",\"asset_ids\":[\"388\",\"387\",\"386\",\"385\",\"384\",\"383\",\"382\",\"381\",\"380\",\"379\"],\"playlist_name\":\"ala\\u7684\\u76f4\\u64ad\\u95f4\\u64ad\\u5355\",\"id\":\"5\",\"room_no\":\"R202607062055320610\",\"title\":\"ai\\u76f4\\u64ad5\",\"subtitle\":\"\",\"persona_id\":\"8\",\"room_type\":\"live\",\"status\":\"1\",\"cover_url\":\"http:\\/\\/127.0.0.1:8000\\/storage\\/ai\\/20260706\\/abg3eee9a61cd0efe5b435b3cb05c93f02be77fd3ad.png\",\"sort\":\"0\",\"created_at\":\"2026-07-06 20:55:32\",\"updated_at\":\"2026-07-28 16:52:11\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785402430);
INSERT INTO `ba_admin_log` VALUES (75, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"22\",\"asset_ids\":[\"8\",\"7\",\"6\",\"5\",\"4\",\"3\",\"2\",\"1\",\"18\",\"17\",\"16\",\"15\",\"14\",\"13\"],\"playlist_name\":\"ala\\u7684\\u76f4\\u64ad\\u95f4\\u64ad\\u5355\",\"id\":\"6\",\"room_no\":\"R202607062104058298\",\"title\":\"ai\\u76f4\\u64ad6\",\"subtitle\":\"\",\"persona_id\":\"9\",\"room_type\":\"live\",\"status\":\"1\",\"cover_url\":\"http:\\/\\/127.0.0.1:8000\\/storage\\/ai\\/20260706\\/abg3eee9a61cd0efe5b435b3cb05c93f02be77fd3ad.png\",\"sort\":\"0\",\"created_at\":\"2026-07-06 21:04:05\",\"updated_at\":\"2026-07-30 16:56:47\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785402439);
INSERT INTO `ba_admin_log` VALUES (76, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"\\u966a\\u4f34\",\"asset_ids\":[\"388\",\"387\",\"386\",\"385\",\"384\",\"383\",\"382\",\"381\",\"380\",\"379\",\"376\",\"375\",\"374\",\"373\",\"372\",\"371\",\"370\",\"369\",\"366\",\"365\",\"364\",\"363\",\"362\",\"361\",\"360\",\"359\"],\"playlist_name\":\"111\\u7684\\u76f4\\u64ad\\u95f4\\u64ad\\u5355\",\"id\":\"7\",\"room_no\":\"R202607281443360064\",\"title\":\"ai\\u76f4\\u64ad7\",\"subtitle\":\"\",\"persona_id\":\"10\",\"room_type\":\"live\",\"status\":\"1\",\"cover_url\":\"http:\\/\\/127.0.0.1:8000\\/storage\\/ai\\/20260728\\/170212328aeddda760d71ac05e15158904817cc316339ab.png\",\"sort\":\"0\",\"created_at\":\"2026-07-28 14:43:36\",\"updated_at\":\"2026-07-30 16:56:15\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785402455);
INSERT INTO `ba_admin_log` VALUES (77, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"\",\"asset_ids\":[\"8\",\"7\",\"6\",\"5\",\"4\",\"3\",\"2\",\"1\",\"18\",\"17\",\"16\",\"15\",\"14\",\"13\"],\"playlist_name\":\"ai\\u76f4\\u64ad6\\u64ad\\u5355\",\"id\":\"8\",\"room_no\":\"111\",\"title\":\"ai\\u76f4\\u64ad8\",\"subtitle\":\"1\",\"persona_id\":\"4\",\"room_type\":\"live\",\"status\":\"1\",\"cover_url\":\"\\/storage\\/default\\/20260728\\/170212328aeddda760d71ac05e15158904817cc316339ab.png\",\"sort\":\"0\",\"created_at\":\"2026-07-28 16:51:44\",\"updated_at\":\"2026-07-28 16:59:41\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785402467);
INSERT INTO `ba_admin_log` VALUES (78, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785403375);
INSERT INTO `ba_admin_log` VALUES (79, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785403377);
INSERT INTO `ba_admin_log` VALUES (80, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785403378);
INSERT INTO `ba_admin_log` VALUES (81, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"5\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785403379);
INSERT INTO `ba_admin_log` VALUES (82, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"5\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785403383);
INSERT INTO `ba_admin_log` VALUES (83, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785403387);
INSERT INTO `ba_admin_log` VALUES (84, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785403389);
INSERT INTO `ba_admin_log` VALUES (85, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"\",\"asset_ids\":[\"388\",\"387\",\"386\",\"385\",\"384\",\"383\",\"382\",\"381\",\"380\",\"379\"],\"playlist_name\":\"ai\\u76f4\\u64ad5\\u64ad\\u5355\",\"id\":\"5\",\"room_no\":\"R202607062055320610\",\"title\":\"ai\\u76f4\\u64ad5\",\"subtitle\":\"5\",\"persona_id\":\"8\",\"room_type\":\"live\",\"status\":\"1\",\"cover_url\":\"http:\\/\\/127.0.0.1:8000\\/storage\\/ai\\/20260706\\/abg3eee9a61cd0efe5b435b3cb05c93f02be77fd3ad.png\",\"sort\":\"0\",\"created_at\":\"2026-07-06 20:55:32\",\"updated_at\":\"2026-07-30 17:07:10\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785403452);
INSERT INTO `ba_admin_log` VALUES (86, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"22\",\"asset_ids\":[\"8\",\"7\",\"6\",\"5\",\"4\",\"3\",\"2\",\"1\",\"18\",\"17\",\"16\",\"15\",\"14\",\"13\"],\"playlist_name\":\"ai\\u76f4\\u64ad8\\u64ad\\u5355\",\"id\":\"6\",\"room_no\":\"R202607062104058298\",\"title\":\"ai\\u76f4\\u64ad6\",\"subtitle\":\"6\",\"persona_id\":\"9\",\"room_type\":\"live\",\"status\":\"1\",\"cover_url\":\"http:\\/\\/127.0.0.1:8000\\/storage\\/ai\\/20260706\\/abg3eee9a61cd0efe5b435b3cb05c93f02be77fd3ad.png\",\"sort\":\"0\",\"created_at\":\"2026-07-06 21:04:05\",\"updated_at\":\"2026-07-30 17:07:19\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785403459);
INSERT INTO `ba_admin_log` VALUES (87, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"\\u966a\\u4f34\",\"asset_ids\":[\"388\",\"387\",\"386\",\"385\",\"384\",\"383\",\"382\",\"381\",\"380\",\"379\",\"376\",\"375\",\"374\",\"373\",\"372\",\"371\",\"370\",\"369\",\"366\",\"365\",\"364\",\"363\",\"362\",\"361\",\"360\",\"359\"],\"playlist_name\":\"ai\\u76f4\\u64ad7\\u64ad\\u5355\",\"id\":\"7\",\"room_no\":\"R202607281443360064\",\"title\":\"ai\\u76f4\\u64ad7\",\"subtitle\":\"7\",\"persona_id\":\"10\",\"room_type\":\"live\",\"status\":\"1\",\"cover_url\":\"http:\\/\\/127.0.0.1:8000\\/storage\\/ai\\/20260728\\/170212328aeddda760d71ac05e15158904817cc316339ab.png\",\"sort\":\"0\",\"created_at\":\"2026-07-28 14:43:36\",\"updated_at\":\"2026-07-30 17:07:35\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785403464);
INSERT INTO `ba_admin_log` VALUES (88, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"\",\"asset_ids\":[\"8\",\"7\",\"6\",\"5\",\"4\",\"3\",\"2\",\"1\",\"18\",\"17\",\"16\",\"15\",\"14\",\"13\"],\"playlist_name\":\"ai\\u76f4\\u64ad6\\u64ad\\u5355\",\"id\":\"8\",\"room_no\":\"111\",\"title\":\"ai\\u76f4\\u64ad8\",\"subtitle\":\"8\",\"persona_id\":\"4\",\"room_type\":\"live\",\"status\":\"1\",\"cover_url\":\"\\/storage\\/default\\/20260728\\/170212328aeddda760d71ac05e15158904817cc316339ab.png\",\"sort\":\"0\",\"created_at\":\"2026-07-28 16:51:44\",\"updated_at\":\"2026-07-30 17:07:47\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785403475);
INSERT INTO `ba_admin_log` VALUES (89, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785403477);
INSERT INTO `ba_admin_log` VALUES (90, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785403479);
INSERT INTO `ba_admin_log` VALUES (91, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785403480);
INSERT INTO `ba_admin_log` VALUES (92, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"5\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785403482);
INSERT INTO `ba_admin_log` VALUES (93, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785403897);
INSERT INTO `ba_admin_log` VALUES (94, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"5\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785403902);
INSERT INTO `ba_admin_log` VALUES (95, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785403907);
INSERT INTO `ba_admin_log` VALUES (96, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"5\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785403920);
INSERT INTO `ba_admin_log` VALUES (97, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785403976);
INSERT INTO `ba_admin_log` VALUES (98, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785403978);
INSERT INTO `ba_admin_log` VALUES (99, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"5\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785403984);
INSERT INTO `ba_admin_log` VALUES (100, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785403989);
INSERT INTO `ba_admin_log` VALUES (101, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"5\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785404086);
INSERT INTO `ba_admin_log` VALUES (102, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785404088);
INSERT INTO `ba_admin_log` VALUES (103, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785404089);
INSERT INTO `ba_admin_log` VALUES (104, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785404091);
INSERT INTO `ba_admin_log` VALUES (105, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"\",\"asset_ids\":[\"8\",\"7\",\"6\",\"5\",\"4\",\"3\",\"2\",\"1\",\"18\",\"17\",\"16\",\"15\",\"14\",\"13\"],\"playlist_name\":\"ai\\u76f4\\u64ad8\\u64ad\\u5355\",\"id\":\"8\",\"room_no\":\"111\",\"title\":\"ai\\u76f4\\u64ad8\",\"subtitle\":\"8\",\"persona_id\":\"4\",\"room_type\":\"live\",\"status\":\"0\",\"cover_url\":\"\\/storage\\/default\\/20260728\\/170212328aeddda760d71ac05e15158904817cc316339ab.png\",\"sort\":\"0\",\"created_at\":\"2026-07-28 16:51:44\",\"updated_at\":\"2026-07-30 17:24:35\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785404100);
INSERT INTO `ba_admin_log` VALUES (106, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"\\u966a\\u4f34\",\"asset_ids\":[\"388\",\"387\",\"386\",\"385\",\"384\",\"383\",\"382\",\"381\",\"380\",\"379\",\"376\",\"375\",\"374\",\"373\",\"372\",\"371\",\"370\",\"369\",\"366\",\"365\",\"364\",\"363\",\"362\",\"361\",\"360\",\"359\"],\"playlist_name\":\"ai\\u76f4\\u64ad7\\u64ad\\u5355\",\"id\":\"7\",\"room_no\":\"R202607281443360064\",\"title\":\"ai\\u76f4\\u64ad7\",\"subtitle\":\"7\",\"persona_id\":\"10\",\"room_type\":\"live\",\"status\":\"0\",\"cover_url\":\"http:\\/\\/127.0.0.1:8000\\/storage\\/ai\\/20260728\\/170212328aeddda760d71ac05e15158904817cc316339ab.png\",\"sort\":\"0\",\"created_at\":\"2026-07-28 14:43:36\",\"updated_at\":\"2026-07-30 17:24:24\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785404108);
INSERT INTO `ba_admin_log` VALUES (107, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"22\",\"asset_ids\":[\"8\",\"7\",\"6\",\"5\",\"4\",\"3\",\"2\",\"1\",\"18\",\"17\",\"16\",\"15\",\"14\",\"13\"],\"playlist_name\":\"ai\\u76f4\\u64ad8\\u64ad\\u5355\",\"id\":\"6\",\"room_no\":\"R202607062104058298\",\"title\":\"ai\\u76f4\\u64ad6\",\"subtitle\":\"6\",\"persona_id\":\"9\",\"room_type\":\"live\",\"status\":\"0\",\"cover_url\":\"http:\\/\\/127.0.0.1:8000\\/storage\\/ai\\/20260706\\/abg3eee9a61cd0efe5b435b3cb05c93f02be77fd3ad.png\",\"sort\":\"0\",\"created_at\":\"2026-07-06 21:04:05\",\"updated_at\":\"2026-07-30 17:24:19\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785404113);
INSERT INTO `ba_admin_log` VALUES (108, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"\",\"asset_ids\":[\"388\",\"387\",\"386\",\"385\",\"384\",\"383\",\"382\",\"381\",\"380\",\"379\"],\"playlist_name\":\"ai\\u76f4\\u64ad5\\u64ad\\u5355\",\"id\":\"5\",\"room_no\":\"R202607062055320610\",\"title\":\"ai\\u76f4\\u64ad5\",\"subtitle\":\"5\",\"persona_id\":\"8\",\"room_type\":\"live\",\"status\":\"0\",\"cover_url\":\"http:\\/\\/127.0.0.1:8000\\/storage\\/ai\\/20260706\\/abg3eee9a61cd0efe5b435b3cb05c93f02be77fd3ad.png\",\"sort\":\"0\",\"created_at\":\"2026-07-06 20:55:32\",\"updated_at\":\"2026-07-30 17:24:11\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785404119);
INSERT INTO `ba_admin_log` VALUES (109, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785404124);
INSERT INTO `ba_admin_log` VALUES (110, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"\",\"asset_ids\":[\"8\",\"7\",\"6\",\"5\",\"4\",\"3\",\"2\",\"1\",\"18\",\"17\",\"16\",\"15\",\"14\",\"13\"],\"playlist_name\":\"ai\\u76f4\\u64ad6\\u64ad\\u5355\",\"id\":\"8\",\"room_no\":\"111\",\"title\":\"ai\\u76f4\\u64ad8\",\"subtitle\":\"8\",\"persona_id\":\"4\",\"room_type\":\"live\",\"status\":\"1\",\"cover_url\":\"\\/storage\\/default\\/20260728\\/170212328aeddda760d71ac05e15158904817cc316339ab.png\",\"sort\":\"0\",\"created_at\":\"2026-07-28 16:51:44\",\"updated_at\":\"2026-07-30 17:35:00\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785404130);
INSERT INTO `ba_admin_log` VALUES (111, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"\\u966a\\u4f34\",\"asset_ids\":[\"388\",\"387\",\"386\",\"385\",\"384\",\"383\",\"382\",\"381\",\"380\",\"379\",\"376\",\"375\",\"374\",\"373\",\"372\",\"371\",\"370\",\"369\",\"366\",\"365\",\"364\",\"363\",\"362\",\"361\",\"360\",\"359\"],\"playlist_name\":\"ai\\u76f4\\u64ad7\\u64ad\\u5355\",\"id\":\"7\",\"room_no\":\"R202607281443360064\",\"title\":\"ai\\u76f4\\u64ad7\",\"subtitle\":\"7\",\"persona_id\":\"10\",\"room_type\":\"live\",\"status\":\"1\",\"cover_url\":\"http:\\/\\/127.0.0.1:8000\\/storage\\/ai\\/20260728\\/170212328aeddda760d71ac05e15158904817cc316339ab.png\",\"sort\":\"0\",\"created_at\":\"2026-07-28 14:43:36\",\"updated_at\":\"2026-07-30 17:35:08\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785404135);
INSERT INTO `ba_admin_log` VALUES (112, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"22\",\"asset_ids\":[\"8\",\"7\",\"6\",\"5\",\"4\",\"3\",\"2\",\"1\",\"18\",\"17\",\"16\",\"15\",\"14\",\"13\"],\"playlist_name\":\"ai\\u76f4\\u64ad8\\u64ad\\u5355\",\"id\":\"6\",\"room_no\":\"R202607062104058298\",\"title\":\"ai\\u76f4\\u64ad6\",\"subtitle\":\"6\",\"persona_id\":\"9\",\"room_type\":\"live\",\"status\":\"1\",\"cover_url\":\"http:\\/\\/127.0.0.1:8000\\/storage\\/ai\\/20260706\\/abg3eee9a61cd0efe5b435b3cb05c93f02be77fd3ad.png\",\"sort\":\"0\",\"created_at\":\"2026-07-06 21:04:05\",\"updated_at\":\"2026-07-30 17:35:13\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785404142);
INSERT INTO `ba_admin_log` VALUES (113, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"\",\"asset_ids\":[\"388\",\"387\",\"386\",\"385\",\"384\",\"383\",\"382\",\"381\",\"380\",\"379\"],\"playlist_name\":\"ai\\u76f4\\u64ad5\\u64ad\\u5355\",\"id\":\"5\",\"room_no\":\"R202607062055320610\",\"title\":\"ai\\u76f4\\u64ad5\",\"subtitle\":\"5\",\"persona_id\":\"8\",\"room_type\":\"live\",\"status\":\"1\",\"cover_url\":\"http:\\/\\/127.0.0.1:8000\\/storage\\/ai\\/20260706\\/abg3eee9a61cd0efe5b435b3cb05c93f02be77fd3ad.png\",\"sort\":\"0\",\"created_at\":\"2026-07-06 20:55:32\",\"updated_at\":\"2026-07-30 17:35:19\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785404147);
INSERT INTO `ba_admin_log` VALUES (114, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"5\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785404455);
INSERT INTO `ba_admin_log` VALUES (115, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785404456);
INSERT INTO `ba_admin_log` VALUES (116, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"5\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785404461);
INSERT INTO `ba_admin_log` VALUES (117, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785404477);
INSERT INTO `ba_admin_log` VALUES (118, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785404493);
INSERT INTO `ba_admin_log` VALUES (119, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785404499);
INSERT INTO `ba_admin_log` VALUES (120, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785404523);
INSERT INTO `ba_admin_log` VALUES (121, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785404585);
INSERT INTO `ba_admin_log` VALUES (122, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785404616);
INSERT INTO `ba_admin_log` VALUES (123, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785404618);
INSERT INTO `ba_admin_log` VALUES (124, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785404619);
INSERT INTO `ba_admin_log` VALUES (125, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"5\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785404621);
INSERT INTO `ba_admin_log` VALUES (126, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785405150);
INSERT INTO `ba_admin_log` VALUES (127, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785405151);
INSERT INTO `ba_admin_log` VALUES (128, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785405152);
INSERT INTO `ba_admin_log` VALUES (129, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"5\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785405154);
INSERT INTO `ba_admin_log` VALUES (130, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785409775);
INSERT INTO `ba_admin_log` VALUES (131, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785409777);
INSERT INTO `ba_admin_log` VALUES (132, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785409778);
INSERT INTO `ba_admin_log` VALUES (133, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"5\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785409779);
INSERT INTO `ba_admin_log` VALUES (134, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"5\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785409782);
INSERT INTO `ba_admin_log` VALUES (135, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785409970);
INSERT INTO `ba_admin_log` VALUES (136, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785409975);
INSERT INTO `ba_admin_log` VALUES (137, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785409976);
INSERT INTO `ba_admin_log` VALUES (138, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785409978);
INSERT INTO `ba_admin_log` VALUES (139, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785410000);
INSERT INTO `ba_admin_log` VALUES (140, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785410002);
INSERT INTO `ba_admin_log` VALUES (141, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785410164);
INSERT INTO `ba_admin_log` VALUES (142, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785410166);
INSERT INTO `ba_admin_log` VALUES (143, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785410812);
INSERT INTO `ba_admin_log` VALUES (144, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785410813);
INSERT INTO `ba_admin_log` VALUES (145, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785416080);
INSERT INTO `ba_admin_log` VALUES (146, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785416082);
INSERT INTO `ba_admin_log` VALUES (147, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785416193);
INSERT INTO `ba_admin_log` VALUES (148, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785416195);
INSERT INTO `ba_admin_log` VALUES (149, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785416197);
INSERT INTO `ba_admin_log` VALUES (150, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"5\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785416198);
INSERT INTO `ba_admin_log` VALUES (151, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785416561);
INSERT INTO `ba_admin_log` VALUES (152, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785416563);
INSERT INTO `ba_admin_log` VALUES (153, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785416565);
INSERT INTO `ba_admin_log` VALUES (154, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"5\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785416566);
INSERT INTO `ba_admin_log` VALUES (155, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785416567);
INSERT INTO `ba_admin_log` VALUES (156, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785416569);
INSERT INTO `ba_admin_log` VALUES (157, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785416570);
INSERT INTO `ba_admin_log` VALUES (158, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785416572);
INSERT INTO `ba_admin_log` VALUES (159, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"5\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785416573);
INSERT INTO `ba_admin_log` VALUES (160, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785417330);
INSERT INTO `ba_admin_log` VALUES (161, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785417334);
INSERT INTO `ba_admin_log` VALUES (162, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785417336);
INSERT INTO `ba_admin_log` VALUES (163, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785417370);
INSERT INTO `ba_admin_log` VALUES (164, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785417375);
INSERT INTO `ba_admin_log` VALUES (165, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785417376);
INSERT INTO `ba_admin_log` VALUES (166, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785417378);
INSERT INTO `ba_admin_log` VALUES (167, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"5\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785417380);
INSERT INTO `ba_admin_log` VALUES (168, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"5\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785417391);
INSERT INTO `ba_admin_log` VALUES (169, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785417393);
INSERT INTO `ba_admin_log` VALUES (170, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785417395);
INSERT INTO `ba_admin_log` VALUES (171, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785417401);
INSERT INTO `ba_admin_log` VALUES (172, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785417817);
INSERT INTO `ba_admin_log` VALUES (173, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785417819);
INSERT INTO `ba_admin_log` VALUES (174, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785418732);
INSERT INTO `ba_admin_log` VALUES (175, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785418733);
INSERT INTO `ba_admin_log` VALUES (176, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785419707);
INSERT INTO `ba_admin_log` VALUES (177, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785420899);
INSERT INTO `ba_admin_log` VALUES (178, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785420901);
INSERT INTO `ba_admin_log` VALUES (179, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785421003);
INSERT INTO `ba_admin_log` VALUES (180, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785421014);
INSERT INTO `ba_admin_log` VALUES (181, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785422317);
INSERT INTO `ba_admin_log` VALUES (182, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785423278);
INSERT INTO `ba_admin_log` VALUES (183, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785423838);
INSERT INTO `ba_admin_log` VALUES (184, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785424317);
INSERT INTO `ba_admin_log` VALUES (185, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785424808);
INSERT INTO `ba_admin_log` VALUES (186, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785424840);
INSERT INTO `ba_admin_log` VALUES (187, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785424841);
INSERT INTO `ba_admin_log` VALUES (188, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785424895);
INSERT INTO `ba_admin_log` VALUES (189, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785424897);
INSERT INTO `ba_admin_log` VALUES (190, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785424912);
INSERT INTO `ba_admin_log` VALUES (191, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"5\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785424915);
INSERT INTO `ba_admin_log` VALUES (192, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785425011);
INSERT INTO `ba_admin_log` VALUES (193, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785425013);
INSERT INTO `ba_admin_log` VALUES (194, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785426435);
INSERT INTO `ba_admin_log` VALUES (195, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785426436);
INSERT INTO `ba_admin_log` VALUES (196, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785426826);
INSERT INTO `ba_admin_log` VALUES (197, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785426828);
INSERT INTO `ba_admin_log` VALUES (198, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785426829);
INSERT INTO `ba_admin_log` VALUES (199, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785426830);
INSERT INTO `ba_admin_log` VALUES (200, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785426913);
INSERT INTO `ba_admin_log` VALUES (201, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785426914);
INSERT INTO `ba_admin_log` VALUES (202, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785427019);
INSERT INTO `ba_admin_log` VALUES (203, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785427022);
INSERT INTO `ba_admin_log` VALUES (204, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785427023);
INSERT INTO `ba_admin_log` VALUES (205, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785427024);
INSERT INTO `ba_admin_log` VALUES (206, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785427101);
INSERT INTO `ba_admin_log` VALUES (207, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785427103);
INSERT INTO `ba_admin_log` VALUES (208, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785427583);
INSERT INTO `ba_admin_log` VALUES (209, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785427585);
INSERT INTO `ba_admin_log` VALUES (210, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785427587);
INSERT INTO `ba_admin_log` VALUES (211, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785427588);
INSERT INTO `ba_admin_log` VALUES (212, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785427820);
INSERT INTO `ba_admin_log` VALUES (213, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785427822);
INSERT INTO `ba_admin_log` VALUES (214, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785427823);
INSERT INTO `ba_admin_log` VALUES (215, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785427824);
INSERT INTO `ba_admin_log` VALUES (216, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785427825);
INSERT INTO `ba_admin_log` VALUES (217, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785427858);
INSERT INTO `ba_admin_log` VALUES (218, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785427860);
INSERT INTO `ba_admin_log` VALUES (219, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785428063);
INSERT INTO `ba_admin_log` VALUES (220, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785428065);
INSERT INTO `ba_admin_log` VALUES (221, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785428067);
INSERT INTO `ba_admin_log` VALUES (222, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785428068);
INSERT INTO `ba_admin_log` VALUES (223, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785428276);
INSERT INTO `ba_admin_log` VALUES (224, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785428279);
INSERT INTO `ba_admin_log` VALUES (225, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785428280);
INSERT INTO `ba_admin_log` VALUES (226, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785428316);
INSERT INTO `ba_admin_log` VALUES (227, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785428317);
INSERT INTO `ba_admin_log` VALUES (228, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785428862);
INSERT INTO `ba_admin_log` VALUES (229, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785428863);
INSERT INTO `ba_admin_log` VALUES (230, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785429091);
INSERT INTO `ba_admin_log` VALUES (231, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785429093);
INSERT INTO `ba_admin_log` VALUES (232, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785429095);
INSERT INTO `ba_admin_log` VALUES (233, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"5\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785429097);
INSERT INTO `ba_admin_log` VALUES (234, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785429976);
INSERT INTO `ba_admin_log` VALUES (235, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785429979);
INSERT INTO `ba_admin_log` VALUES (236, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"22\",\"asset_ids\":[\"13\"],\"playlist_name\":\"ai\\u76f4\\u64ad6\\u64ad\\u5355\",\"id\":\"6\",\"room_no\":\"R202607062104058298\",\"title\":\"ai\\u76f4\\u64ad6\",\"subtitle\":\"6\",\"persona_id\":\"9\",\"room_type\":\"live\",\"status\":\"1\",\"cover_url\":\"http:\\/\\/127.0.0.1:8000\\/storage\\/ai\\/20260706\\/abg3eee9a61cd0efe5b435b3cb05c93f02be77fd3ad.png\",\"sort\":\"0\",\"created_at\":\"2026-07-06 21:04:05\",\"updated_at\":\"2026-07-30 17:35:42\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785430015);
INSERT INTO `ba_admin_log` VALUES (237, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785430018);
INSERT INTO `ba_admin_log` VALUES (238, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785430076);
INSERT INTO `ba_admin_log` VALUES (239, 1, 'admin', '/admin/live.Room/edit', '房间管理-编辑', '{\"tag_names\":\"22\",\"asset_ids\":[\"388\"],\"playlist_name\":\"ai\\u76f4\\u64ad6\\u64ad\\u5355\",\"id\":\"6\",\"room_no\":\"R202607062104058298\",\"title\":\"ai\\u76f4\\u64ad6\",\"subtitle\":\"6\",\"persona_id\":\"9\",\"room_type\":\"live\",\"status\":\"1\",\"cover_url\":\"http:\\/\\/127.0.0.1:8000\\/storage\\/ai\\/20260706\\/abg3eee9a61cd0efe5b435b3cb05c93f02be77fd3ad.png\",\"sort\":\"0\",\"created_at\":\"2026-07-06 21:04:05\",\"updated_at\":\"2026-07-30 17:35:42\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785430085);
INSERT INTO `ba_admin_log` VALUES (240, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785430088);
INSERT INTO `ba_admin_log` VALUES (241, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785430229);
INSERT INTO `ba_admin_log` VALUES (242, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785430231);
INSERT INTO `ba_admin_log` VALUES (243, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785431003);
INSERT INTO `ba_admin_log` VALUES (244, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785431004);
INSERT INTO `ba_admin_log` VALUES (245, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785432939);
INSERT INTO `ba_admin_log` VALUES (246, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785432940);
INSERT INTO `ba_admin_log` VALUES (247, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785478323);
INSERT INTO `ba_admin_log` VALUES (248, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785478325);
INSERT INTO `ba_admin_log` VALUES (249, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785478375);
INSERT INTO `ba_admin_log` VALUES (250, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785478613);
INSERT INTO `ba_admin_log` VALUES (251, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785478614);
INSERT INTO `ba_admin_log` VALUES (252, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785481829);
INSERT INTO `ba_admin_log` VALUES (253, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785481830);
INSERT INTO `ba_admin_log` VALUES (254, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785481832);
INSERT INTO `ba_admin_log` VALUES (255, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785484537);
INSERT INTO `ba_admin_log` VALUES (256, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785484538);
INSERT INTO `ba_admin_log` VALUES (257, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785486748);
INSERT INTO `ba_admin_log` VALUES (258, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785486749);
INSERT INTO `ba_admin_log` VALUES (259, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785489227);
INSERT INTO `ba_admin_log` VALUES (260, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785489228);
INSERT INTO `ba_admin_log` VALUES (261, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785490564);
INSERT INTO `ba_admin_log` VALUES (262, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785490566);
INSERT INTO `ba_admin_log` VALUES (263, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785492272);
INSERT INTO `ba_admin_log` VALUES (264, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785492273);
INSERT INTO `ba_admin_log` VALUES (265, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785492302);
INSERT INTO `ba_admin_log` VALUES (266, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785492306);
INSERT INTO `ba_admin_log` VALUES (267, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785492858);
INSERT INTO `ba_admin_log` VALUES (268, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785492860);
INSERT INTO `ba_admin_log` VALUES (269, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785493459);
INSERT INTO `ba_admin_log` VALUES (270, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785493461);
INSERT INTO `ba_admin_log` VALUES (271, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785494259);
INSERT INTO `ba_admin_log` VALUES (272, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785494260);
INSERT INTO `ba_admin_log` VALUES (273, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785496709);
INSERT INTO `ba_admin_log` VALUES (274, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785496713);
INSERT INTO `ba_admin_log` VALUES (275, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785497499);
INSERT INTO `ba_admin_log` VALUES (276, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785499443);
INSERT INTO `ba_admin_log` VALUES (277, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785501864);
INSERT INTO `ba_admin_log` VALUES (278, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785502215);
INSERT INTO `ba_admin_log` VALUES (279, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785502859);
INSERT INTO `ba_admin_log` VALUES (280, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"5\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785502905);
INSERT INTO `ba_admin_log` VALUES (281, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785507326);
INSERT INTO `ba_admin_log` VALUES (282, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785507328);
INSERT INTO `ba_admin_log` VALUES (283, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785507329);
INSERT INTO `ba_admin_log` VALUES (284, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"5\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785507331);
INSERT INTO `ba_admin_log` VALUES (285, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785507344);
INSERT INTO `ba_admin_log` VALUES (286, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"5\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785507346);
INSERT INTO `ba_admin_log` VALUES (287, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785507348);
INSERT INTO `ba_admin_log` VALUES (288, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785507366);
INSERT INTO `ba_admin_log` VALUES (289, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785507508);
INSERT INTO `ba_admin_log` VALUES (290, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785509613);
INSERT INTO `ba_admin_log` VALUES (291, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785511778);
INSERT INTO `ba_admin_log` VALUES (292, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785512696);
INSERT INTO `ba_admin_log` VALUES (293, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"8\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785513894);
INSERT INTO `ba_admin_log` VALUES (294, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785567278);
INSERT INTO `ba_admin_log` VALUES (295, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785567280);
INSERT INTO `ba_admin_log` VALUES (296, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785569318);
INSERT INTO `ba_admin_log` VALUES (297, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785569319);
INSERT INTO `ba_admin_log` VALUES (298, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785570185);
INSERT INTO `ba_admin_log` VALUES (299, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785570187);
INSERT INTO `ba_admin_log` VALUES (300, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785570747);
INSERT INTO `ba_admin_log` VALUES (301, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785570749);
INSERT INTO `ba_admin_log` VALUES (302, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785571542);
INSERT INTO `ba_admin_log` VALUES (303, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"7\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785571544);
INSERT INTO `ba_admin_log` VALUES (304, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785571567);
INSERT INTO `ba_admin_log` VALUES (305, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785571572);
INSERT INTO `ba_admin_log` VALUES (306, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785572360);
INSERT INTO `ba_admin_log` VALUES (307, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785573883);
INSERT INTO `ba_admin_log` VALUES (308, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785574160);
INSERT INTO `ba_admin_log` VALUES (309, 1, 'admin', '/admin/live.Room/stopStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785574173);
INSERT INTO `ba_admin_log` VALUES (310, 1, 'admin', '/admin/live.Room/startStream', '房间管理-??', '{\"id\":\"6\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 1785574183);
INSERT INTO `ba_admin_log` VALUES (311, 1, 'admin', '/admin/Index/login', '登录', '{\"username\":\"admin\",\"password\":\"***\",\"keep\":\"\",\"captchaId\":\"ed4e4ec4-a86a-4e72-b9fc-53e17c9125e7\",\"captchaInfo\":\"252,85-82,166;350;200\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', 1786075761);
INSERT INTO `ba_admin_log` VALUES (312, 0, '未知', '/admin/live.RechargeChannel/editChannel', '充值渠道-未知(editchannel)', '{\"name\":\"555\",\"type\":\"other\",\"address\":\"123\",\"diamond_rate\":\"100\",\"min_amount\":\"10\",\"sort\":\"0\",\"status\":\"1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', 1786076840);
INSERT INTO `ba_admin_log` VALUES (313, 0, '未知', '/admin/live.RechargeChannel/editChannel?id=2', '充值渠道-未知(editchannel)', '{\"id\":\"2\",\"name\":\"555\",\"type\":\"other\",\"address\":\"123\",\"diamond_rate\":\"100.00\",\"min_amount\":\"10.00\",\"sort\":\"0\",\"status\":\"1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', 1786076893);
INSERT INTO `ba_admin_log` VALUES (314, 0, '未知', '/admin/live.RechargeOrder/approve?id=1', '充值审核-未知(approve)', '{\"id\":\"1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', 1786077084);
INSERT INTO `ba_admin_log` VALUES (315, 0, '未知', '/admin/live.RechargeOrder/approve?id=1', '充值审核-未知(approve)', '{\"id\":\"1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', 1786077088);
INSERT INTO `ba_admin_log` VALUES (316, 0, '未知', '/admin/live.RechargeOrder/approve?id=2', '充值审核-未知(approve)', '{\"id\":\"2\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', 1786077185);
INSERT INTO `ba_admin_log` VALUES (317, 0, '未知', '/admin/live.RechargeChannel/editChannel?id=1', '充值渠道-未知(editchannel)', '{\"id\":\"1\",\"name\":\"USDT-TRC20\",\"type\":\"usdt_trc20\",\"address\":\"TXNfC3v8bqPzKnDPKxEzMxEMMzRzUvSRKx\",\"diamond_rate\":\"100.00\",\"min_amount\":\"10.00\",\"sort\":\"1\",\"status\":\"1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', 1786077758);
INSERT INTO `ba_admin_log` VALUES (318, 0, '未知', '/admin/live.RechargeOrder/approve', '充值审核-未知(approve)', '{\"id\":\"3\",\"remark\":\"\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', 1786078394);
INSERT INTO `ba_admin_log` VALUES (319, 0, '未知', '/admin/live.RechargeOrder/approve', '充值审核-未知(approve)', '{\"id\":\"3\",\"remark\":\"4\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', 1786078397);
INSERT INTO `ba_admin_log` VALUES (320, 0, '未知', '/admin/live.RechargeOrder/approve', '充值审核-未知(approve)', '{\"id\":\"1\",\"remark\":\"test\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; zh-CN) WindowsPowerShell/5.1.19041.6456', 1786078578);
INSERT INTO `ba_admin_log` VALUES (321, 0, '未知', '/admin/live.RechargeOrder/approve', '充值审核-未知(approve)', '{\"id\":\"4\",\"remark\":\"\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', 1786078839);
INSERT INTO `ba_admin_log` VALUES (322, 0, '未知', '/admin/live.RechargeOrder/approve', '充值审核-未知(approve)', '{\"id\":\"4\",\"remark\":\"55\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', 1786078842);
INSERT INTO `ba_admin_log` VALUES (323, 0, '未知', '/admin/live.RechargeOrder/reject', '充值审核-未知(reject)', '{\"id\":\"5\",\"reason\":\"1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', 1786078978);
INSERT INTO `ba_admin_log` VALUES (324, 0, '未知', '/admin/live.RechargeOrder/reject', '充值审核-未知(reject)', '{\"id\":\"5\",\"reason\":\"1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', 1786079005);
INSERT INTO `ba_admin_log` VALUES (325, 0, '未知', '/admin/live.RechargeOrder/approve', '充值审核-未知(approve)', '{\"id\":\"6\",\"remark\":\"1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', 1786079018);
INSERT INTO `ba_admin_log` VALUES (326, 0, '未知', '/admin/live.RechargeOrder/approve', '充值审核-未知(approve)', '{\"id\":\"4\",\"remark\":\"test\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; zh-CN) WindowsPowerShell/5.1.19041.6456', 1786079088);
INSERT INTO `ba_admin_log` VALUES (327, 0, '未知', '/admin/live.RechargeOrder/approve', '充值审核-未知(approve)', '{\"id\":\"7\",\"remark\":\"test\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; zh-CN) WindowsPowerShell/5.1.19041.6456', 1786079127);

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
  INDEX `pid`(`pid`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 150 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '菜单和权限规则表' ROW_FORMAT = Dynamic;

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
INSERT INTO `ba_admin_rule` VALUES (76, 0, 'menu', 'BuildAdmin', 'buildadmin', 'buildadmin', 'local-logo', 'link', 'https://doc.buildadmin.com', '', 1, 'none', '', 0, 0, 1778942775, 1778942775);
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
INSERT INTO `ba_admin_rule` VALUES (90, 0, 'menu_dir', '直播运营', 'live', 'live', 'fa fa-video-camera', NULL, '', 'Layout', 1, 'none', '直播后台运营菜单', 120, 1, 1779248396, 1779085601);
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
INSERT INTO `ba_admin_rule` VALUES (109, 90, 'menu', '礼物管理', 'live/gift', 'live/gift', 'fa fa-gift', 'tab', '', '/src/views/backend/live/gift/index.vue', 1, 'none', '直播礼物配置', 116, 1, 1779248396, 1779248396);
INSERT INTO `ba_admin_rule` VALUES (110, 109, 'button', '查看', 'live/gift/index', '', '', 'tab', '', '', 0, 'none', '', 10, 1, 1779248396, 1779248396);
INSERT INTO `ba_admin_rule` VALUES (111, 109, 'button', '新增', 'live/gift/add', '', '', 'tab', '', '', 0, 'none', '', 9, 1, 1779248396, 1779248396);
INSERT INTO `ba_admin_rule` VALUES (112, 109, 'button', '编辑', 'live/gift/edit', '', '', 'tab', '', '', 0, 'none', '', 8, 1, 1779248396, 1779248396);
INSERT INTO `ba_admin_rule` VALUES (113, 109, 'button', '删除', 'live/gift/del', '', '', 'tab', '', '', 0, 'none', '', 7, 1, 1779248396, 1779248396);
INSERT INTO `ba_admin_rule` VALUES (114, 109, 'button', '选择', 'live/gift/select', '', '', 'tab', '', '', 0, 'none', '', 6, 1, 1779248396, 1779248396);
INSERT INTO `ba_admin_rule` VALUES (115, 90, 'menu', '直播平台用户', 'user/liveUser', 'live/liveUser', 'fa fa-users', 'tab', '', '/src/views/backend/user/liveUser/index.vue', 1, 'none', '', 115, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (121, 44, 'menu', '短信服务配置', 'routine/smsConfig', 'routine/smsConfig', 'fa fa-message', 'tab', '', '/src/views/backend/routine/smsConfig/index.vue', 1, 'none', '', 4, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (128, 90, 'menu_dir', '????', 'live/maintenance', 'live/maintenance', 'fa fa-clock-o', NULL, '', '', 0, 'none', '', 4, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (129, 128, 'menu', '????', 'live/maintenanceTask', 'live/maintenanceTask', 'fa fa-list', 'tab', '', '/src/views/backend/live/maintenanceTask/index.vue', 1, 'none', '', 1, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (130, 129, 'button', '??', 'live/maintenanceTask/index', '', '', NULL, '', '', 0, 'none', '', 10, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (131, 129, 'button', '??', 'live/maintenanceTask/add', '', '', NULL, '', '', 0, 'none', '', 9, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (132, 129, 'button', '??', 'live/maintenanceTask/edit', '', '', NULL, '', '', 0, 'none', '', 8, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (133, 129, 'button', '??', 'live/maintenanceTask/del', '', '', NULL, '', '', 0, 'none', '', 7, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (134, 128, 'menu', 'TG????', 'live/maintenanceConfig', 'live/maintenanceConfig', 'fa fa-telegram', 'tab', '', '/src/views/backend/live/maintenanceConfig/index.vue', 1, 'none', '', 2, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (135, 0, 'button', '??', 'live/replayClip/index', '', '', NULL, '', '', 0, 'none', '', 10, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (136, 0, 'button', '??', 'live/replayClip/add', '', '', NULL, '', '', 0, 'none', '', 9, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (137, 0, 'button', '??', 'live/replayClip/edit', '', '', NULL, '', '', 0, 'none', '', 8, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (138, 0, 'button', '??', 'live/replayClip/del', '', '', NULL, '', '', 0, 'none', '', 7, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (139, 129, 'button', '??', 'live/maintenanceTask/index', '', '', NULL, '', '', 0, 'none', '', 10, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (140, 129, 'button', '??', 'live/maintenanceTask/add', '', '', NULL, '', '', 0, 'none', '', 9, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (141, 129, 'button', '??', 'live/maintenanceTask/edit', '', '', NULL, '', '', 0, 'none', '', 8, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (142, 129, 'button', '??', 'live/maintenanceTask/del', '', '', NULL, '', '', 0, 'none', '', 7, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (143, 103, 'button', '??', 'live/room/startStream', '', '', NULL, '', '', 0, 'none', '', 0, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (144, 103, 'button', '??', 'live/room/stopStream', '', '', NULL, '', '', 0, 'none', '', 0, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (145, 103, 'button', '????', 'live/room/streamStatus', '', '', NULL, '', '', 0, 'none', '', 0, 1, NULL, NULL);
INSERT INTO `ba_admin_rule` VALUES (146, 90, 'menu', '商家认证审核', 'live/merchantCertification', 'live/merchantCertification', 'fa fa-certificate', 'iframe', '/admin/live.MerchantCertification/index', '', 0, 'none', '', 120, 1, 1786075946, 1786075946);
INSERT INTO `ba_admin_rule` VALUES (147, 90, 'menu', '众筹项目', 'live/crowdfunding', 'live/crowdfunding', 'fa fa-rocket', 'iframe', '/admin/live.Crowdfunding/index', '', 0, 'none', '', 121, 1, 1786075946, 1786075946);
INSERT INTO `ba_admin_rule` VALUES (148, 90, 'menu', '充值渠道', 'live/rechargeChannel', 'live/rechargeChannel', 'fa fa-credit-card', 'iframe', '/admin/live.RechargeChannel/index', '', 0, 'none', '', 122, 1, 1786075946, 1786075946);
INSERT INTO `ba_admin_rule` VALUES (149, 90, 'menu', '充值审核', 'live/rechargeOrder', 'live/rechargeOrder', 'fa fa-check-circle', 'iframe', '/admin/live.RechargeOrder/index', '', 0, 'none', '', 123, 1, 1786075946, 1786075946);

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
) ENGINE = InnoDB AUTO_INCREMENT = 19 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '附件表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of ba_attachment
-- ----------------------------
INSERT INTO `ba_attachment` VALUES (1, 'live', 1, 0, '/storage/live/20260518/demo_live_asset0e2b8da6ffb71124ee7e28e25094fd6fcfda45ec.mp4', 0, 0, 'demo_live_asset_room_2.mp4', 5485935, 'video/mp4', 1, 'local', '0e2b8da6ffb71124ee7e28e25094fd6fcfda45ec', 1779096312, 1779096312);
INSERT INTO `ba_attachment` VALUES (2, 'ai', 0, 9, '/storage/ai/20260702/aiimageedit-1774431bc69c712377d1cb9d5b9d78f263e19afc039.png', 1302, 1208, 'aiimageedit-1778840506202.png', 2146360, 'image/png', 3, 'local', '4431bc69c712377d1cb9d5b9d78f263e19afc039', 1782928658, 1783154460);
INSERT INTO `ba_attachment` VALUES (3, 'ai', 0, 11, '/storage/ai/20260704/idlefish-msg-170fa9ab807607a1423118b4663652b447b4279af7.jpg', 316, 307, 'idlefish-msg-1781663477531.png.jpg', 14488, 'image/jpeg', 1, 'local', '0fa9ab807607a1423118b4663652b447b4279af7', 1783154491, 1783154491);
INSERT INTO `ba_attachment` VALUES (4, 'ai', 0, 11, '/storage/ai/20260704/idlefish-msg-177e42ed9da5222e1b3dabbdf033acdbc19495c749.jpg', 336, 300, 'idlefish-msg-1781663596861.png.jpg', 15418, 'image/jpeg', 1, 'local', '7e42ed9da5222e1b3dabbdf033acdbc19495c749', 1783154594, 1783154594);
INSERT INTO `ba_attachment` VALUES (5, 'certification', 0, 2, '/storage/certification/20260706/abg3eee9a61cd0efe5b435b3cb05c93f02be77fd3ad.png', 235, 300, 'abg.png', 34942, 'image/png', 10, 'local', '3eee9a61cd0efe5b435b3cb05c93f02be77fd3ad', 1783332041, 1783345349);
INSERT INTO `ba_attachment` VALUES (6, 'certification', 0, 2, '/storage/certification/20260706/ag4c132c074ce3c669af07cf17ec20e5ce99b3631d.png', 700, 475, 'ag.png', 382316, 'image/png', 7, 'local', '4c132c074ce3c669af07cf17ec20e5ce99b3631d', 1783332045, 1783345352);
INSERT INTO `ba_attachment` VALUES (7, 'ai', 0, 2, '/storage/ai/20260706/abg3eee9a61cd0efe5b435b3cb05c93f02be77fd3ad.png', 235, 300, 'abg.png', 34942, 'image/png', 3, 'local', '3eee9a61cd0efe5b435b3cb05c93f02be77fd3ad', 1783342530, 1783343043);
INSERT INTO `ba_attachment` VALUES (8, 'ai', 0, 2, '/storage/ai/20260706/bbin(1)fe11891ae3e32bb9b6d1c7450c04c264ca63be02.png', 235, 300, 'bbin(1).png', 31585, 'image/png', 1, 'local', 'fe11891ae3e32bb9b6d1c7450c04c264ca63be02', 1783342954, 1783342954);
INSERT INTO `ba_attachment` VALUES (9, 'ai', 0, 2, '/storage/ai/20260706/ag4c132c074ce3c669af07cf17ec20e5ce99b3631d.png', 700, 475, 'ag.png', 382316, 'image/png', 1, 'local', '4c132c074ce3c669af07cf17ec20e5ce99b3631d', 1783342964, 1783342964);
INSERT INTO `ba_attachment` VALUES (10, 'certification', 0, 2, '/storage/certification/20260725/145112282aad7d13417c22b0e5130fc969a1086f4b7c407.png', 240, 240, '1451122.png', 32584, 'image/png', 1, 'local', '82aad7d13417c22b0e5130fc969a1086f4b7c407', 1784959779, 1784959779);
INSERT INTO `ba_attachment` VALUES (11, 'certification', 0, 2, '/storage/certification/20260725/1615454c8cc10a37a2e0df72d7121259b7cc8d6920874b1.png', 240, 240, '1615454.png', 30372, 'image/png', 1, 'local', 'c8cc10a37a2e0df72d7121259b7cc8d6920874b1', 1784959782, 1784959782);
INSERT INTO `ba_attachment` VALUES (12, 'ai', 0, 2, '/storage/ai/20260728/170212328aeddda760d71ac05e15158904817cc316339ab.png', 200, 200, '1702123.png', 83635, 'image/png', 1, 'local', '28aeddda760d71ac05e15158904817cc316339ab', 1785221014, 1785221014);
INSERT INTO `ba_attachment` VALUES (13, 'default', 1, 0, '/storage/default/20260728/170212328aeddda760d71ac05e15158904817cc316339ab.png', 200, 200, '1702123.png', 83635, 'image/png', 1, 'local', '28aeddda760d71ac05e15158904817cc316339ab', 1785229179, 1785229179);
INSERT INTO `ba_attachment` VALUES (14, 'certification', 0, 2, '/storage/certification/20260807/Screenshot_2026bcf68c994e62239c0d50aae9caeb385ce16ed3d8.jpg', 1440, 3200, 'Screenshot_2026-08-05-17-06-15-879_ttcp.uniapp..jpg', 1966679, 'image/jpeg', 1, 'local', 'bcf68c994e62239c0d50aae9caeb385ce16ed3d8', 1786032014, 1786032014);
INSERT INTO `ba_attachment` VALUES (15, 'recharge_qr', 0, 0, '/storage/recharge_qr/20260807/层级4d3991ba4a42ffa7e34553a283420232ed7370f7.jpg', 744, 384, '层级.jpg', 37174, 'image/jpeg', 2, 'local', '4d3991ba4a42ffa7e34553a283420232ed7370f7', 1786076840, 1786076893);
INSERT INTO `ba_attachment` VALUES (16, 'recharge', 0, 2, '/storage/recharge/20260807/满月80997c616a2a42d27990abaf961040e27f0fdd17.jpg', 744, 384, '满月.jpg', 25400, 'image/jpeg', 1, 'local', '80997c616a2a42d27990abaf961040e27f0fdd17', 1786077108, 1786077108);
INSERT INTO `ba_attachment` VALUES (17, 'recharge', 0, 2, '/storage/recharge/20260807/Screenshot_2026bcf68c994e62239c0d50aae9caeb385ce16ed3d8.jpg', 1440, 3200, 'Screenshot_2026-08-05-17-06-15-879_ttcp.uniapp..jpg', 1966679, 'image/jpeg', 1, 'local', 'bcf68c994e62239c0d50aae9caeb385ce16ed3d8', 1786077377, 1786077377);
INSERT INTO `ba_attachment` VALUES (18, 'recharge_qr', 0, 0, '/storage/recharge_qr/20260807/7ad64959b834bf5bf89da3c2211a4c7b25930c67.jpg', 360, 184, '.jpg', 10638, 'image/jpeg', 1, 'local', '7ad64959b834bf5bf89da3c2211a4c7b25930c67', 1786077758, 1786077758);

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
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '验证码表' ROW_FORMAT = Dynamic;

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
  UNIQUE INDEX `name`(`name`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 20 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '系统配置' ROW_FORMAT = Dynamic;

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
) ENGINE = InnoDB AUTO_INCREMENT = 6 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '回收规则表' ROW_FORMAT = Dynamic;

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
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '数据回收记录表' ROW_FORMAT = Dynamic;

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
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '敏感数据规则表' ROW_FORMAT = Dynamic;

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
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '敏感数据修改记录' ROW_FORMAT = Dynamic;

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
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '知识库表' ROW_FORMAT = Dynamic;

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
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '用户Token表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of ba_token
-- ----------------------------
INSERT INTO `ba_token` VALUES ('98c5b9fedc23e306ade22887c63524429d504dff', 'admin', 1, 1786075761, 1786334961);

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
  UNIQUE INDEX `username`(`username`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '会员表' ROW_FORMAT = Dynamic;

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
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '会员组表' ROW_FORMAT = Dynamic;

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
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '会员余额变动表' ROW_FORMAT = Dynamic;

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
  INDEX `pid`(`pid`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 6 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '会员菜单权限规则表' ROW_FORMAT = Dynamic;

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
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '会员积分变动表' ROW_FORMAT = Dynamic;

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
  `deadline_at` datetime(0) NULL DEFAULT NULL COMMENT '截止时间',
  `accepted_at` datetime(0) NULL DEFAULT NULL COMMENT '接单时间',
  `finished_at` datetime(0) NULL DEFAULT NULL COMMENT '完成时间',
  `failed_at` datetime(0) NULL DEFAULT NULL COMMENT '失败时间',
  `created_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_task_no`(`task_no`) USING BTREE,
  INDEX `idx_room_status_priority`(`room_id`, `status`, `priority`) USING BTREE,
  INDEX `idx_worker_id`(`worker_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'AI任务' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for lp_ai_task_log
-- ----------------------------
DROP TABLE IF EXISTS `lp_ai_task_log`;
CREATE TABLE `lp_ai_task_log`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `task_id` bigint(20) UNSIGNED NOT NULL COMMENT '任务ID',
  `event_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '事件类型',
  `payload_json` json NULL COMMENT '事件数据',
  `created_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_task_created`(`task_id`, `created_at`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'AI任务日志' ROW_FORMAT = Dynamic;

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
  `created_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_channel_chain_created`(`pay_channel`, `chain_type`, `created_at`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '充值汇率快照' ROW_FORMAT = Dynamic;

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
  `created_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_room_created`(`room_id`, `created_at`) USING BTREE,
  INDEX `idx_user_created`(`user_id`, `created_at`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 35 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '聊天消息' ROW_FORMAT = Dynamic;

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
INSERT INTO `lp_chat_message` VALUES (29, 8, 1, 'text', '324', 1, '2026-07-28 17:00:10');
INSERT INTO `lp_chat_message` VALUES (30, 8, 1, 'text', '3', 1, '2026-07-28 17:00:13');
INSERT INTO `lp_chat_message` VALUES (31, 8, 1, 'text', '1', 1, '2026-07-29 18:22:26');
INSERT INTO `lp_chat_message` VALUES (32, 7, 1, 'text', '1', 1, '2026-07-30 22:45:10');
INSERT INTO `lp_chat_message` VALUES (33, 7, 1, 'text', '2', 1, '2026-07-30 22:45:12');
INSERT INTO `lp_chat_message` VALUES (34, 7, 1, 'text', '3', 1, '2026-07-30 22:45:15');
INSERT INTO `lp_chat_message` VALUES (35, 7, 1, 'text', '4', 1, '2026-07-30 22:45:19');

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
  `created_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_room_created`(`room_id`, `created_at`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '控制命令日志' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for lp_crowdfunding_pledge
-- ----------------------------
DROP TABLE IF EXISTS `lp_crowdfunding_pledge`;
CREATE TABLE `lp_crowdfunding_pledge`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `project_id` bigint(20) UNSIGNED NOT NULL COMMENT '众筹项目ID',
  `user_id` bigint(20) UNSIGNED NOT NULL COMMENT '支持者用户ID',
  `amount` decimal(18, 2) NOT NULL COMMENT '支持金额（钻石）',
  `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '状态:0冻结中 1已划转(成功) 2已退款(失败)',
  `refunded_at` datetime(0) NULL DEFAULT NULL COMMENT '退款时间',
  `created_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_project_id`(`project_id`) USING BTREE,
  INDEX `idx_user_id`(`user_id`) USING BTREE,
  INDEX `idx_project_user`(`project_id`, `user_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '众筹支持记录' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_crowdfunding_pledge
-- ----------------------------
INSERT INTO `lp_crowdfunding_pledge` VALUES (1, 1, 1, 10.00, 0, NULL, '2026-08-07 14:24:45', '2026-08-07 14:24:45');
INSERT INTO `lp_crowdfunding_pledge` VALUES (2, 1, 1, 10.00, 0, NULL, '2026-08-07 14:24:52', '2026-08-07 14:24:52');

-- ----------------------------
-- Table structure for lp_crowdfunding_project
-- ----------------------------
DROP TABLE IF EXISTS `lp_crowdfunding_project`;
CREATE TABLE `lp_crowdfunding_project`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id` bigint(20) UNSIGNED NOT NULL COMMENT '发起商家用户ID',
  `title` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '项目标题',
  `persona_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '角色名称',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '富文本描述（角色创意/人设/风格）',
  `cover_url` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '封面图',
  `target_amount` decimal(18, 2) NOT NULL COMMENT '目标金额（钻石）',
  `raised_amount` decimal(18, 2) NOT NULL DEFAULT 0.00 COMMENT '已筹金额（钻石）',
  `supporter_count` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '支持人数',
  `deadline` datetime(0) NOT NULL COMMENT '截止时间',
  `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '状态:0进行中 1已成功 2已失败(已退款)',
  `persona_id` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '关联角色ID（成功后商家手动创建关联）',
  `created_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_user_id`(`user_id`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `idx_deadline`(`deadline`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '众筹项目' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_crowdfunding_project
-- ----------------------------
INSERT INTO `lp_crowdfunding_project` VALUES (1, 2, '1', '1', '1', 'http://127.0.0.1:8001/storage/certification/20260807/Screenshot_2026bcf68c994e62239c0d50aae9caeb385ce16ed3d8.jpg', 100.00, 20.00, 2, '2026-08-31 00:00:00', 0, NULL, '2026-08-07 00:00:29', '2026-08-07 14:24:52');

-- ----------------------------
-- Table structure for lp_gift
-- ----------------------------
DROP TABLE IF EXISTS `lp_gift`;
CREATE TABLE `lp_gift`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `gift_code` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '礼物编码',
  `name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '礼物名称',
  `price_diamond` decimal(18, 2) NOT NULL COMMENT '钻石价格',
  `trigger_mode` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'keyword' COMMENT '触发模式:none privilege interaction keyword',
  `trigger_duration_sec` int(11) NOT NULL DEFAULT 0 COMMENT '触发时长秒',
  `effect_code` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '特效编码',
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '状态',
  `created_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_gift_code`(`gift_code`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '礼物配置' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_gift
-- ----------------------------
INSERT INTO `lp_gift` VALUES (1, 'rose', '玫瑰', 10.00, 'keyword', 5, 'rose_effect', 1, '2026-05-17 00:01:21');
INSERT INTO `lp_gift` VALUES (2, 'vip_30s', '专属礼物', 199.00, 'keyword', 5, 'vip_effect', 1, '2026-05-17 00:01:21');

-- ----------------------------
-- Table structure for lp_gift_keyword
-- ----------------------------
DROP TABLE IF EXISTS `lp_gift_keyword`;
CREATE TABLE `lp_gift_keyword`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `gift_id` bigint(20) UNSIGNED NOT NULL COMMENT '礼物ID',
  `keyword` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '触发关键词',
  `priority` int(11) NOT NULL DEFAULT 0 COMMENT '优先级（同礼物多关键词时按优先级取）',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_gift_kw`(`gift_id`, `keyword`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 5 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '礼物关键词映射' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_gift_keyword
-- ----------------------------
INSERT INTO `lp_gift_keyword` VALUES (3, 2, '投喂', 0);
INSERT INTO `lp_gift_keyword` VALUES (4, 1, '比心', 0);

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
  `created_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_gift_order_no`(`order_no`) USING BTREE,
  INDEX `idx_room_created`(`room_id`, `created_at`) USING BTREE,
  INDEX `idx_user_created`(`user_id`, `created_at`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 250 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '礼物订单' ROW_FORMAT = Dynamic;

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
INSERT INTO `lp_gift_order` VALUES (21, 'G202607281550382ACDE5AD', 1, 3, 2, 1, 199.00, NULL, 1, '2026-07-28 15:50:38');
INSERT INTO `lp_gift_order` VALUES (22, 'G202607281550400DD1C268', 1, 3, 1, 1, 10.00, NULL, 1, '2026-07-28 15:50:40');
INSERT INTO `lp_gift_order` VALUES (23, 'G20260728165239E3CABA48', 1, 8, 1, 1, 10.00, NULL, 1, '2026-07-28 16:52:39');
INSERT INTO `lp_gift_order` VALUES (24, 'G20260728165242D2B4F1C9', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-28 16:52:42');
INSERT INTO `lp_gift_order` VALUES (25, 'G20260729145835EE3F6C4E', 1, 8, 1, 1, 10.00, NULL, 1, '2026-07-29 14:58:35');
INSERT INTO `lp_gift_order` VALUES (26, 'G202607291458380D5F6FE3', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 14:58:38');
INSERT INTO `lp_gift_order` VALUES (27, 'G202607291459030CEEC981', 1, 8, 1, 1, 10.00, NULL, 1, '2026-07-29 14:59:03');
INSERT INTO `lp_gift_order` VALUES (28, 'G20260729145904819902EF', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 14:59:04');
INSERT INTO `lp_gift_order` VALUES (29, 'G20260729145936F504EAB6', 1, 8, 1, 1, 10.00, NULL, 1, '2026-07-29 14:59:36');
INSERT INTO `lp_gift_order` VALUES (30, 'G20260729145940F8B057BF', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 14:59:40');
INSERT INTO `lp_gift_order` VALUES (31, 'G20260729182229608720A1', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 18:22:29');
INSERT INTO `lp_gift_order` VALUES (32, 'G2026072918225224FEB417', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 18:22:52');
INSERT INTO `lp_gift_order` VALUES (33, 'G202607291822578B20373C', 1, 8, 1, 1, 10.00, NULL, 1, '2026-07-29 18:22:57');
INSERT INTO `lp_gift_order` VALUES (34, 'G20260729200056EB5ACEA5', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 20:00:56');
INSERT INTO `lp_gift_order` VALUES (35, 'G202607292001021EC2737F', 1, 8, 1, 1, 10.00, NULL, 1, '2026-07-29 20:01:02');
INSERT INTO `lp_gift_order` VALUES (36, 'G2026072920010664795F46', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 20:01:06');
INSERT INTO `lp_gift_order` VALUES (37, 'G202607292001128582B836', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 20:01:12');
INSERT INTO `lp_gift_order` VALUES (38, 'G2026072920011767E72EA8', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 20:01:17');
INSERT INTO `lp_gift_order` VALUES (39, 'G20260729200124A4AE3D43', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 20:01:24');
INSERT INTO `lp_gift_order` VALUES (40, 'G202607292001259D5D0A3C', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 20:01:25');
INSERT INTO `lp_gift_order` VALUES (41, 'G20260729200125D5828356', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 20:01:25');
INSERT INTO `lp_gift_order` VALUES (42, 'G202607292001252FD1D2F7', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 20:01:25');
INSERT INTO `lp_gift_order` VALUES (43, 'G20260729200144E6657D3C', 1, 8, 1, 1, 10.00, NULL, 1, '2026-07-29 20:01:44');
INSERT INTO `lp_gift_order` VALUES (44, 'G20260729200151F5B7A222', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 20:01:51');
INSERT INTO `lp_gift_order` VALUES (45, 'G202607292001586F28D891', 1, 8, 1, 1, 10.00, NULL, 1, '2026-07-29 20:01:58');
INSERT INTO `lp_gift_order` VALUES (46, 'G2026072920020673A8F51E', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 20:02:06');
INSERT INTO `lp_gift_order` VALUES (47, 'G20260729200214B4D3683E', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 20:02:14');
INSERT INTO `lp_gift_order` VALUES (48, 'G2026072920574621D2EC86', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 20:57:46');
INSERT INTO `lp_gift_order` VALUES (49, 'G2026072920580550CE9AAA', 1, 8, 1, 1, 10.00, NULL, 1, '2026-07-29 20:58:05');
INSERT INTO `lp_gift_order` VALUES (50, 'G202607292058196D227AD3', 1, 8, 1, 1, 10.00, NULL, 1, '2026-07-29 20:58:19');
INSERT INTO `lp_gift_order` VALUES (51, 'G20260729205826377322D1', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 20:58:26');
INSERT INTO `lp_gift_order` VALUES (52, 'G202607292058329968AD77', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 20:58:32');
INSERT INTO `lp_gift_order` VALUES (53, 'G20260729205838FAB18E3E', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 20:58:38');
INSERT INTO `lp_gift_order` VALUES (54, 'G202607292058408D28793F', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 20:58:40');
INSERT INTO `lp_gift_order` VALUES (55, 'G20260729205841A92EF755', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 20:58:41');
INSERT INTO `lp_gift_order` VALUES (56, 'G2026072920584232DF24B4', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 20:58:42');
INSERT INTO `lp_gift_order` VALUES (57, 'G20260729205842A198D7AC', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 20:58:42');
INSERT INTO `lp_gift_order` VALUES (58, 'G20260729205843FD117893', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 20:58:43');
INSERT INTO `lp_gift_order` VALUES (59, 'G20260729205843744F507C', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 20:58:43');
INSERT INTO `lp_gift_order` VALUES (60, 'G20260729205843F986B692', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 20:58:43');
INSERT INTO `lp_gift_order` VALUES (61, 'G202607292058434EA5A55C', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 20:58:43');
INSERT INTO `lp_gift_order` VALUES (62, 'G2026072921000088BC3EC0', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 21:00:00');
INSERT INTO `lp_gift_order` VALUES (63, 'G20260729210005F85595A2', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 21:00:05');
INSERT INTO `lp_gift_order` VALUES (64, 'G202607292100065FC46DA9', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 21:00:06');
INSERT INTO `lp_gift_order` VALUES (65, 'G202607292100061912D485', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 21:00:06');
INSERT INTO `lp_gift_order` VALUES (66, 'G20260729210007B8B04C7E', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 21:00:07');
INSERT INTO `lp_gift_order` VALUES (67, 'G202607292100073E70986D', 1, 8, 1, 1, 10.00, NULL, 1, '2026-07-29 21:00:07');
INSERT INTO `lp_gift_order` VALUES (68, 'G202607292102171449BA33', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 21:02:17');
INSERT INTO `lp_gift_order` VALUES (69, 'G202607292116537D99663F', 1, 8, 1, 1, 10.00, NULL, 1, '2026-07-29 21:16:53');
INSERT INTO `lp_gift_order` VALUES (70, 'G20260729211705674C1FEB', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 21:17:05');
INSERT INTO `lp_gift_order` VALUES (71, 'G202607292117171B504A5D', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 21:17:17');
INSERT INTO `lp_gift_order` VALUES (72, 'G2026072921172524FAE686', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 21:17:25');
INSERT INTO `lp_gift_order` VALUES (73, 'G202607292117387B3DE776', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 21:17:38');
INSERT INTO `lp_gift_order` VALUES (74, 'G20260729211748E910F31B', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 21:17:48');
INSERT INTO `lp_gift_order` VALUES (75, 'G2026072921175099AE995A', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 21:17:50');
INSERT INTO `lp_gift_order` VALUES (76, 'G20260729212012628FC026', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-29 21:20:12');
INSERT INTO `lp_gift_order` VALUES (77, 'G20260730152952F243385D', 1, 8, 1, 1, 10.00, NULL, 1, '2026-07-30 15:29:52');
INSERT INTO `lp_gift_order` VALUES (78, 'G202607301529571EF90CCD', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-30 15:29:57');
INSERT INTO `lp_gift_order` VALUES (79, 'G20260730153009009F8E36', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-30 15:30:09');
INSERT INTO `lp_gift_order` VALUES (80, 'G202607301530377D319B39', 1, 8, 1, 1, 10.00, NULL, 1, '2026-07-30 15:30:37');
INSERT INTO `lp_gift_order` VALUES (81, 'G202607301530488E0EA127', 1, 8, 1, 1, 10.00, NULL, 1, '2026-07-30 15:30:48');
INSERT INTO `lp_gift_order` VALUES (82, 'G2026073015305090B5A730', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-30 15:30:50');
INSERT INTO `lp_gift_order` VALUES (83, 'G202607301531027A25CAB6', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-30 15:31:02');
INSERT INTO `lp_gift_order` VALUES (84, 'G202607301531048EA73654', 1, 8, 1, 1, 10.00, NULL, 1, '2026-07-30 15:31:04');
INSERT INTO `lp_gift_order` VALUES (85, 'G20260730155055569D96FF', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-30 15:50:55');
INSERT INTO `lp_gift_order` VALUES (86, 'G202607301552367A128294', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-30 15:52:36');
INSERT INTO `lp_gift_order` VALUES (87, 'G20260730155252397D7DDE', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-30 15:52:52');
INSERT INTO `lp_gift_order` VALUES (88, 'G20260730155311D4B4F806', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-30 15:53:11');
INSERT INTO `lp_gift_order` VALUES (89, 'G2026073015532235C3A45D', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-30 15:53:22');
INSERT INTO `lp_gift_order` VALUES (90, 'G2026073015533943D071C9', 1, 8, 1, 1, 10.00, NULL, 1, '2026-07-30 15:53:39');
INSERT INTO `lp_gift_order` VALUES (91, 'G202607301608006A9A0BE7', 1, 8, 1, 1, 10.00, NULL, 1, '2026-07-30 16:08:00');
INSERT INTO `lp_gift_order` VALUES (92, 'G2026073016084520FBB82B', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-30 16:08:45');
INSERT INTO `lp_gift_order` VALUES (93, 'G20260730175317F105FAE7', 1, 8, 2, 1, 199.00, NULL, 1, '2026-07-30 17:53:17');
INSERT INTO `lp_gift_order` VALUES (94, 'G20260730175634C8B797F4', 1, 7, 2, 1, 199.00, NULL, 1, '2026-07-30 17:56:34');
INSERT INTO `lp_gift_order` VALUES (95, 'G20260730194241BA704ED1', 1, 7, 2, 1, 199.00, NULL, 1, '2026-07-30 19:42:41');
INSERT INTO `lp_gift_order` VALUES (96, 'G20260730221800791A01A0', 1, 7, 2, 1, 199.00, NULL, 1, '2026-07-30 22:18:00');
INSERT INTO `lp_gift_order` VALUES (97, 'G20260730224049900AC6F0', 1, 7, 2, 1, 199.00, NULL, 1, '2026-07-30 22:40:49');
INSERT INTO `lp_gift_order` VALUES (98, 'G202607302240541A81DEB8', 1, 7, 1, 1, 10.00, NULL, 1, '2026-07-30 22:40:54');
INSERT INTO `lp_gift_order` VALUES (99, 'G202607302240552AFBCD9A', 1, 7, 2, 1, 199.00, NULL, 1, '2026-07-30 22:40:55');
INSERT INTO `lp_gift_order` VALUES (100, 'G20260730224055EFBE54B8', 1, 7, 2, 1, 199.00, NULL, 1, '2026-07-30 22:40:55');
INSERT INTO `lp_gift_order` VALUES (101, 'G20260730224346C31EDA17', 1, 7, 2, 1, 199.00, NULL, 1, '2026-07-30 22:43:46');
INSERT INTO `lp_gift_order` VALUES (102, 'G20260730230612C2E912C9', 1, 7, 2, 1, 199.00, NULL, 1, '2026-07-30 23:06:12');
INSERT INTO `lp_gift_order` VALUES (103, 'G202607302325247628FBD7', 3, 7, 2, 1, 199.00, NULL, 1, '2026-07-30 23:25:24');
INSERT INTO `lp_gift_order` VALUES (104, 'G202607310019290E9B3527', 3, 7, 2, 1, 199.00, NULL, 1, '2026-07-31 00:19:29');
INSERT INTO `lp_gift_order` VALUES (105, 'G20260731003944C7E8D484', 2, 5, 1, 1, 10.00, NULL, 1, '2026-07-31 00:39:44');
INSERT INTO `lp_gift_order` VALUES (106, 'G202607310051069215A7BA', 1, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 00:51:06');
INSERT INTO `lp_gift_order` VALUES (107, 'G20260731005113B93C6FB5', 2, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 00:51:13');
INSERT INTO `lp_gift_order` VALUES (108, 'G2026073100512103267C3F', 2, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 00:51:21');
INSERT INTO `lp_gift_order` VALUES (109, 'G202607310051250B75567E', 2, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 00:51:25');
INSERT INTO `lp_gift_order` VALUES (110, 'G2026073100521314716045', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 00:52:13');
INSERT INTO `lp_gift_order` VALUES (111, 'G20260731005229A1951BF4', 1, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 00:52:29');
INSERT INTO `lp_gift_order` VALUES (112, 'G20260731010237EE320FE5', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 01:02:37');
INSERT INTO `lp_gift_order` VALUES (113, 'G202607310103485E431FE0', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 01:03:48');
INSERT INTO `lp_gift_order` VALUES (114, 'G20260731010356A9486985', 1, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 01:03:56');
INSERT INTO `lp_gift_order` VALUES (115, 'G20260731010408F1C2D80E', 1, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 01:04:08');
INSERT INTO `lp_gift_order` VALUES (116, 'G20260731010414AF0705A2', 1, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 01:04:14');
INSERT INTO `lp_gift_order` VALUES (117, 'G20260731013409A0ED9F79', 1, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 01:34:09');
INSERT INTO `lp_gift_order` VALUES (118, 'G202607310134211A0833C6', 1, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 01:34:21');
INSERT INTO `lp_gift_order` VALUES (119, 'G20260731013550FC4DD21C', 1, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 01:35:50');
INSERT INTO `lp_gift_order` VALUES (120, 'G20260731013558FAF6DE0B', 1, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 01:35:58');
INSERT INTO `lp_gift_order` VALUES (121, 'G20260731013603A46F520D', 1, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 01:36:03');
INSERT INTO `lp_gift_order` VALUES (122, 'G2026073101360405774FB5', 1, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 01:36:04');
INSERT INTO `lp_gift_order` VALUES (123, 'G20260731013606805FBD80', 1, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 01:36:06');
INSERT INTO `lp_gift_order` VALUES (124, 'G2026073101360689DE6673', 1, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 01:36:06');
INSERT INTO `lp_gift_order` VALUES (125, 'G20260731141308C16EE992', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 14:13:08');
INSERT INTO `lp_gift_order` VALUES (126, 'G2026073114132990C91A8D', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 14:13:29');
INSERT INTO `lp_gift_order` VALUES (127, 'G2026073114133736F9BB53', 2, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 14:13:37');
INSERT INTO `lp_gift_order` VALUES (128, 'G20260731141611176F7F18', 2, 8, 1, 1, 10.00, NULL, 1, '2026-07-31 14:16:11');
INSERT INTO `lp_gift_order` VALUES (129, 'G2026073114171410CC5F87', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 14:17:14');
INSERT INTO `lp_gift_order` VALUES (130, 'G20260731145218AC7A6AAD', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 14:52:18');
INSERT INTO `lp_gift_order` VALUES (131, 'G2026073114522254B556D7', 2, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 14:52:22');
INSERT INTO `lp_gift_order` VALUES (132, 'G20260731151109E9E8AEE1', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 15:11:09');
INSERT INTO `lp_gift_order` VALUES (133, 'G2026073115112500904CF1', 2, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 15:11:25');
INSERT INTO `lp_gift_order` VALUES (134, 'G2026073115422471D48296', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 15:42:24');
INSERT INTO `lp_gift_order` VALUES (135, 'G20260731154242270E87E4', 2, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 15:42:42');
INSERT INTO `lp_gift_order` VALUES (136, 'G20260731154246DF1CADB1', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 15:42:46');
INSERT INTO `lp_gift_order` VALUES (137, 'G20260731154247020D7F13', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 15:42:47');
INSERT INTO `lp_gift_order` VALUES (138, 'G2026073115424841435087', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 15:42:48');
INSERT INTO `lp_gift_order` VALUES (139, 'G202607311542485CD5A9FA', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 15:42:48');
INSERT INTO `lp_gift_order` VALUES (140, 'G2026073115424873C3F70C', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 15:42:48');
INSERT INTO `lp_gift_order` VALUES (141, 'G2026073115424820E1BE53', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 15:42:48');
INSERT INTO `lp_gift_order` VALUES (142, 'G202607311542498E86183D', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 15:42:49');
INSERT INTO `lp_gift_order` VALUES (143, 'G20260731154727725B25DD', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 15:47:27');
INSERT INTO `lp_gift_order` VALUES (144, 'G202607311547287CA28DAE', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 15:47:28');
INSERT INTO `lp_gift_order` VALUES (145, 'G20260731154728E930F653', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 15:47:28');
INSERT INTO `lp_gift_order` VALUES (146, 'G20260731154729E09FDC8A', 2, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 15:47:29');
INSERT INTO `lp_gift_order` VALUES (147, 'G202607311547306FECF79B', 2, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 15:47:30');
INSERT INTO `lp_gift_order` VALUES (148, 'G20260731154730A2410709', 2, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 15:47:30');
INSERT INTO `lp_gift_order` VALUES (149, 'G202607311547308962AA70', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 15:47:30');
INSERT INTO `lp_gift_order` VALUES (150, 'G20260731154730C1B54A7C', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 15:47:30');
INSERT INTO `lp_gift_order` VALUES (151, 'G2026073115473107372C69', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 15:47:31');
INSERT INTO `lp_gift_order` VALUES (152, 'G20260731154846010DAD7E', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 15:48:46');
INSERT INTO `lp_gift_order` VALUES (153, 'G20260731154847C46063B2', 2, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 15:48:47');
INSERT INTO `lp_gift_order` VALUES (154, 'G20260731154847637D1411', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 15:48:47');
INSERT INTO `lp_gift_order` VALUES (155, 'G20260731154848EF258E75', 2, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 15:48:48');
INSERT INTO `lp_gift_order` VALUES (156, 'G20260731154849BA7BAB3D', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 15:48:49');
INSERT INTO `lp_gift_order` VALUES (157, 'G20260731154849F5603461', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 15:48:49');
INSERT INTO `lp_gift_order` VALUES (158, 'G202607311555526E49ECEB', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 15:55:52');
INSERT INTO `lp_gift_order` VALUES (159, 'G2026073115555895ACB7A7', 2, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 15:55:58');
INSERT INTO `lp_gift_order` VALUES (160, 'G202607311555584A6CBF71', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 15:55:58');
INSERT INTO `lp_gift_order` VALUES (161, 'G202607311556051546F8DD', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 15:56:05');
INSERT INTO `lp_gift_order` VALUES (162, 'G202607311606513890F484', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 16:06:51');
INSERT INTO `lp_gift_order` VALUES (163, 'G20260731161953BEA230C6', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 16:19:53');
INSERT INTO `lp_gift_order` VALUES (164, 'G202607311620253D68AD15', 2, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 16:20:25');
INSERT INTO `lp_gift_order` VALUES (165, 'G202607311620492D59103D', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 16:20:49');
INSERT INTO `lp_gift_order` VALUES (166, 'G20260731164104EDE4772C', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 16:41:04');
INSERT INTO `lp_gift_order` VALUES (167, 'G2026073116411671FA2E82', 2, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 16:41:16');
INSERT INTO `lp_gift_order` VALUES (168, 'G20260731165832613A6A03', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 16:58:32');
INSERT INTO `lp_gift_order` VALUES (169, 'G2026073116584743B4B44C', 2, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 16:58:47');
INSERT INTO `lp_gift_order` VALUES (170, 'G20260731171401889EC85A', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 17:14:01');
INSERT INTO `lp_gift_order` VALUES (171, 'G202607311714160DC4D0E6', 2, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 17:14:16');
INSERT INTO `lp_gift_order` VALUES (172, 'G20260731173622F6B1424D', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 17:36:22');
INSERT INTO `lp_gift_order` VALUES (173, 'G202607311736404A9AD70F', 2, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 17:36:40');
INSERT INTO `lp_gift_order` VALUES (174, 'G2026073118044309B7888E', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 18:04:43');
INSERT INTO `lp_gift_order` VALUES (175, 'G202607311804545C4DCB8E', 2, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 18:04:54');
INSERT INTO `lp_gift_order` VALUES (176, 'G202607311805497AFE4186', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 18:05:49');
INSERT INTO `lp_gift_order` VALUES (177, 'G202607311814360AEFB4D9', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 18:14:36');
INSERT INTO `lp_gift_order` VALUES (178, 'G20260731182331380BF63D', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 18:23:31');
INSERT INTO `lp_gift_order` VALUES (179, 'G2026073118233941563077', 2, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 18:23:39');
INSERT INTO `lp_gift_order` VALUES (180, 'G20260731183755D293A2F2', 2, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 18:37:55');
INSERT INTO `lp_gift_order` VALUES (181, 'G20260731183804DA405ACA', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 18:38:04');
INSERT INTO `lp_gift_order` VALUES (182, 'G202607311918593046E368', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 19:18:59');
INSERT INTO `lp_gift_order` VALUES (183, 'G2026073119324207776A00', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 19:32:42');
INSERT INTO `lp_gift_order` VALUES (184, 'G20260731193259E579B264', 2, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 19:32:59');
INSERT INTO `lp_gift_order` VALUES (185, 'G2026073119344861815D0A', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 19:34:48');
INSERT INTO `lp_gift_order` VALUES (186, 'G20260731200424F0954180', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 20:04:24');
INSERT INTO `lp_gift_order` VALUES (187, 'G20260731201545A5A9AE90', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 20:15:45');
INSERT INTO `lp_gift_order` VALUES (188, 'G20260731201556886309D4', 2, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 20:15:56');
INSERT INTO `lp_gift_order` VALUES (189, 'G20260731202704248EF5FC', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 20:27:04');
INSERT INTO `lp_gift_order` VALUES (190, 'G20260731202710D3199540', 2, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 20:27:10');
INSERT INTO `lp_gift_order` VALUES (191, 'G202607312043444837427C', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 20:43:44');
INSERT INTO `lp_gift_order` VALUES (192, 'G20260731204351BBCF31DD', 2, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 20:43:51');
INSERT INTO `lp_gift_order` VALUES (193, 'G202607312043548D49AC60', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 20:43:54');
INSERT INTO `lp_gift_order` VALUES (194, 'G20260731204601F3DA0C61', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 20:46:01');
INSERT INTO `lp_gift_order` VALUES (195, 'G20260731204744D65CAF15', 2, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 20:47:44');
INSERT INTO `lp_gift_order` VALUES (196, 'G20260731205055FB4EF24F', 2, 7, 2, 1, 199.00, NULL, 1, '2026-07-31 20:50:55');
INSERT INTO `lp_gift_order` VALUES (197, 'G20260731205127C1809490', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 20:51:27');
INSERT INTO `lp_gift_order` VALUES (198, 'G202607312058094439CF85', 2, 7, 1, 1, 10.00, NULL, 1, '2026-07-31 20:58:09');
INSERT INTO `lp_gift_order` VALUES (199, 'G2026073120581914404F1B', 2, 7, 2, 1, 199.00, NULL, 1, '2026-07-31 20:58:19');
INSERT INTO `lp_gift_order` VALUES (200, 'G20260731205842679DDECC', 2, 7, 2, 1, 199.00, NULL, 1, '2026-07-31 20:58:42');
INSERT INTO `lp_gift_order` VALUES (201, 'G202607312100038FF719D6', 2, 7, 2, 1, 199.00, NULL, 1, '2026-07-31 21:00:03');
INSERT INTO `lp_gift_order` VALUES (202, 'G20260731210019114CA832', 2, 7, 2, 1, 199.00, NULL, 1, '2026-07-31 21:00:19');
INSERT INTO `lp_gift_order` VALUES (203, 'G20260731210036672CAA96', 2, 7, 1, 1, 10.00, NULL, 1, '2026-07-31 21:00:36');
INSERT INTO `lp_gift_order` VALUES (204, 'G20260731222046640C13C0', 2, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 22:20:46');
INSERT INTO `lp_gift_order` VALUES (205, 'G20260731222112945F14B0', 2, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 22:21:12');
INSERT INTO `lp_gift_order` VALUES (206, 'G20260731222136490911EE', 2, 5, 2, 1, 199.00, NULL, 1, '2026-07-31 22:21:36');
INSERT INTO `lp_gift_order` VALUES (207, 'G20260731233026497CF884', 3, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 23:30:26');
INSERT INTO `lp_gift_order` VALUES (208, 'G202607312330378B1A51CA', 3, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 23:30:37');
INSERT INTO `lp_gift_order` VALUES (209, 'G2026073123453540A18433', 3, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 23:45:35');
INSERT INTO `lp_gift_order` VALUES (210, 'G20260731234551EC3B6EFF', 3, 6, 1, 1, 10.00, NULL, 1, '2026-07-31 23:45:51');
INSERT INTO `lp_gift_order` VALUES (211, 'G20260731235031BF1B7B5E', 3, 6, 2, 1, 199.00, NULL, 1, '2026-07-31 23:50:31');
INSERT INTO `lp_gift_order` VALUES (212, 'G202608010017421B5915F4', 3, 6, 2, 1, 199.00, NULL, 1, '2026-08-01 00:17:42');
INSERT INTO `lp_gift_order` VALUES (213, 'G2026080100175986BD1F4E', 3, 6, 1, 1, 10.00, NULL, 1, '2026-08-01 00:17:59');
INSERT INTO `lp_gift_order` VALUES (214, 'G2026080100343075500D61', 3, 6, 2, 1, 199.00, NULL, 1, '2026-08-01 00:34:30');
INSERT INTO `lp_gift_order` VALUES (215, 'G20260801003442C334C3CC', 3, 6, 1, 1, 10.00, NULL, 1, '2026-08-01 00:34:42');
INSERT INTO `lp_gift_order` VALUES (216, 'G20260801003448A307B203', 3, 6, 2, 1, 199.00, NULL, 1, '2026-08-01 00:34:48');
INSERT INTO `lp_gift_order` VALUES (217, 'G202608010036010958FD84', 3, 6, 2, 1, 199.00, NULL, 1, '2026-08-01 00:36:01');
INSERT INTO `lp_gift_order` VALUES (218, 'G20260801003603F902DBD7', 3, 6, 2, 1, 199.00, NULL, 1, '2026-08-01 00:36:03');
INSERT INTO `lp_gift_order` VALUES (219, 'G20260731164341B5AAFA49', 1, 6, 2, 1, 199.00, NULL, 1, '2026-08-01 00:43:41');
INSERT INTO `lp_gift_order` VALUES (220, 'G20260801012943B3D5359D', 3, 6, 2, 1, 199.00, NULL, 1, '2026-08-01 01:29:43');
INSERT INTO `lp_gift_order` VALUES (221, 'G20260801012953F9E105FE', 3, 6, 2, 1, 199.00, NULL, 1, '2026-08-01 01:29:53');
INSERT INTO `lp_gift_order` VALUES (222, 'G202608010129580F461A8B', 3, 6, 1, 1, 10.00, NULL, 1, '2026-08-01 01:29:58');
INSERT INTO `lp_gift_order` VALUES (223, 'G202608010130157AE4036C', 3, 6, 2, 1, 199.00, NULL, 1, '2026-08-01 01:30:15');
INSERT INTO `lp_gift_order` VALUES (224, 'G202608010200555900D97B', 3, 6, 2, 1, 199.00, NULL, 1, '2026-08-01 02:00:55');
INSERT INTO `lp_gift_order` VALUES (225, 'G20260801021147CE93285F', 3, 6, 2, 1, 199.00, NULL, 1, '2026-08-01 02:11:47');
INSERT INTO `lp_gift_order` VALUES (226, 'G202608010211550EC6DAED', 3, 6, 2, 1, 199.00, NULL, 1, '2026-08-01 02:11:55');
INSERT INTO `lp_gift_order` VALUES (227, 'G2026080102115821C542D7', 3, 6, 1, 1, 10.00, NULL, 1, '2026-08-01 02:11:58');
INSERT INTO `lp_gift_order` VALUES (228, 'G2026080102122623D5CB87', 3, 6, 2, 1, 199.00, NULL, 1, '2026-08-01 02:12:26');
INSERT INTO `lp_gift_order` VALUES (229, 'G20260801023028E2AF4E22', 3, 6, 2, 1, 199.00, NULL, 1, '2026-08-01 02:30:28');
INSERT INTO `lp_gift_order` VALUES (230, 'G2026080102315093D707CF', 3, 6, 2, 1, 199.00, NULL, 1, '2026-08-01 02:31:50');
INSERT INTO `lp_gift_order` VALUES (231, 'G20260801023157344D21DD', 3, 6, 1, 1, 10.00, NULL, 1, '2026-08-01 02:31:57');
INSERT INTO `lp_gift_order` VALUES (232, 'G202608010231588DCC2F31', 3, 6, 2, 1, 199.00, NULL, 1, '2026-08-01 02:31:58');
INSERT INTO `lp_gift_order` VALUES (233, 'G20260801024349E1268E33', 3, 6, 2, 1, 199.00, NULL, 1, '2026-08-01 02:43:49');
INSERT INTO `lp_gift_order` VALUES (234, 'G2026080102435617B60914', 3, 6, 1, 1, 10.00, NULL, 1, '2026-08-01 02:43:56');
INSERT INTO `lp_gift_order` VALUES (235, 'G202608010243566FF8B487', 3, 6, 2, 1, 199.00, NULL, 1, '2026-08-01 02:43:57');
INSERT INTO `lp_gift_order` VALUES (236, 'G20260801024357185266FB', 3, 6, 1, 1, 10.00, NULL, 1, '2026-08-01 02:43:57');
INSERT INTO `lp_gift_order` VALUES (237, 'G2026080102435814C6711C', 3, 6, 2, 1, 199.00, NULL, 1, '2026-08-01 02:43:58');
INSERT INTO `lp_gift_order` VALUES (238, 'G20260801030933D1D7B1E2', 3, 6, 2, 1, 199.00, NULL, 1, '2026-08-01 03:09:33');
INSERT INTO `lp_gift_order` VALUES (239, 'G20260801031021BB627142', 3, 6, 1, 1, 10.00, NULL, 1, '2026-08-01 03:10:21');
INSERT INTO `lp_gift_order` VALUES (240, 'G202608010326013883F50F', 3, 6, 2, 1, 199.00, NULL, 1, '2026-08-01 03:26:01');
INSERT INTO `lp_gift_order` VALUES (241, 'G202608010326166A9B9C14', 3, 6, 1, 1, 10.00, NULL, 1, '2026-08-01 03:26:16');
INSERT INTO `lp_gift_order` VALUES (242, 'G202608011607246B1A023D', 3, 6, 2, 1, 199.00, NULL, 1, '2026-08-01 16:07:24');
INSERT INTO `lp_gift_order` VALUES (243, 'G20260801160731BCACD241', 3, 6, 1, 1, 10.00, NULL, 1, '2026-08-01 16:07:31');
INSERT INTO `lp_gift_order` VALUES (244, 'G20260801160735D39AAB05', 3, 6, 2, 1, 199.00, NULL, 1, '2026-08-01 16:07:35');
INSERT INTO `lp_gift_order` VALUES (245, 'G2026080116241599C292E9', 3, 6, 2, 1, 199.00, NULL, 1, '2026-08-01 16:24:15');
INSERT INTO `lp_gift_order` VALUES (246, 'G20260801164104EDA8C4A9', 3, 6, 2, 1, 199.00, NULL, 1, '2026-08-01 16:41:04');
INSERT INTO `lp_gift_order` VALUES (247, 'G202608011644537E1E1827', 3, 6, 2, 1, 199.00, NULL, 1, '2026-08-01 16:44:53');
INSERT INTO `lp_gift_order` VALUES (248, 'G20260801164910709C166F', 3, 6, 2, 1, 199.00, NULL, 1, '2026-08-01 16:49:10');
INSERT INTO `lp_gift_order` VALUES (249, 'G2026080116535894C9E759', 3, 6, 2, 1, 199.00, NULL, 1, '2026-08-01 16:53:58');
INSERT INTO `lp_gift_order` VALUES (250, 'G20260801165845F4C757FD', 3, 6, 2, 1, 199.00, NULL, 1, '2026-08-01 16:58:45');

-- ----------------------------
-- Table structure for lp_like_action_log
-- ----------------------------
DROP TABLE IF EXISTS `lp_like_action_log`;
CREATE TABLE `lp_like_action_log`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `room_id` bigint(20) UNSIGNED NOT NULL COMMENT '房间ID',
  `user_id` bigint(20) UNSIGNED NOT NULL COMMENT '用户ID',
  `action_count` int(11) NOT NULL DEFAULT 1 COMMENT '点赞次数',
  `created_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_room_created`(`room_id`, `created_at`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '点赞日志' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for lp_maintenance_config
-- ----------------------------
DROP TABLE IF EXISTS `lp_maintenance_config`;
CREATE TABLE `lp_maintenance_config`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '??',
  `bot_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Telegram Bot Token',
  `chat_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Telegram Chat ID (???????)',
  `created_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '????',
  `updated_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '????',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'TG????' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_maintenance_config
-- ----------------------------
INSERT INTO `lp_maintenance_config` VALUES (1, '', '', '2026-07-24 21:39:01', '2026-07-24 21:39:01');

-- ----------------------------
-- Table structure for lp_maintenance_task
-- ----------------------------
DROP TABLE IF EXISTS `lp_maintenance_task`;
CREATE TABLE `lp_maintenance_task`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '??',
  `name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '??????',
  `due_date` date NOT NULL COMMENT '????',
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '??',
  `repeat_remind` tinyint(4) NOT NULL DEFAULT 0 COMMENT '????:0?? 1??',
  `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '??:0??? 1??? 2???',
  `last_notify_at` datetime(0) NULL DEFAULT NULL COMMENT '??????',
  `created_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '????',
  `updated_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '????',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_due_date`(`due_date`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '??????' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for lp_media_asset
-- ----------------------------
DROP TABLE IF EXISTS `lp_media_asset`;
CREATE TABLE `lp_media_asset`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `asset_code` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '素材编码',
  `asset_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '素材类型:video image audio subtitle',
  `scene_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '场景类型:public privilege interaction cover',
  `keywords` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '关键词标签，逗号分隔：比心,飞吻,挥手',
  `persona` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '人设：白毛女',
  `weight` int(11) NOT NULL DEFAULT 1 COMMENT '随机权重，数值越大播放概率越高',
  `title` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '标题',
  `file_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件地址',
  `duration_ms` int(11) NOT NULL DEFAULT 0 COMMENT '时长ms',
  `checksum` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '校验值',
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '状态',
  `created_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_asset_code`(`asset_code`) USING BTREE,
  INDEX `idx_scene_type_status`(`scene_type`, `status`) USING BTREE,
  INDEX `idx_persona_kw`(`persona`, `keywords`(100)) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 389 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '素材池' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_media_asset
-- ----------------------------
INSERT INTO `lp_media_asset` VALUES (1, 'demo_live_asset', 'video', 'public', '', '', 1, '默认直播演示素材', 'D:\\ever\\douyin\\douyin\\services\\channel-worker/runtime/assets/demo_live_asset.mp4', 5000, '', 1, '2026-05-17 23:11:55');
INSERT INTO `lp_media_asset` VALUES (2, 'demo_live_asset_room_1', 'video', 'public', '', '', 1, '深夜情感电台演示素材', 'D:\\ever\\douyin\\douyin\\services\\channel-worker/runtime/assets/demo_live_asset_room_1.mp4', 5000, '', 1, '2026-05-18 09:56:39');
INSERT INTO `lp_media_asset` VALUES (3, 'demo_live_asset_room_2', 'video', 'public', '', '', 1, '午后轻音乐直播间演示素材', 'D:\\ever\\douyin\\douyin\\services\\channel-worker/runtime/assets/demo_live_asset_room_2.mp4', 10000, '', 1, '2026-05-18 09:56:39');
INSERT INTO `lp_media_asset` VALUES (4, 'demo_live_asset_room_3', 'video', 'public', '', '', 1, '清晨自习直播间演示素材', 'D:\\ever\\douyin\\douyin\\services\\channel-worker/runtime/assets/demo_live_asset_room_3.mp4', 15000, '', 1, '2026-05-18 09:56:39');
INSERT INTO `lp_media_asset` VALUES (5, 'e2e_asset_20260518_1', 'video', 'public', '', '', 1, 'E2E联调素材1', '/storage/live/20260518/demo_live_asset0e2b8da6ffb71124ee7e28e25094fd6fcfda45ec.mp4', 10000, '0e2b8da6ffb71124ee7e28e25094fd6fcfda45ec', 1, '2026-05-18 17:27:17');
INSERT INTO `lp_media_asset` VALUES (6, '白毛女-出入场-001', 'video', 'public', '出入场', '白毛女', 1, 'Day 3-4： 20 组基础社交反馈（点头、摇头、挥', '视频成品/Day 3-4： 20 组基础社交反馈（点头、摇头、挥.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (7, '白毛女-比心-001', 'video', 'public', '比心', '白毛女', 1, 'OK手势', '视频成品/OK手势.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (8, '白毛女-投喂-001', 'video', 'public', '投喂', '白毛女', 1, 'POV 第一视角互动：喂食', '视频成品/POV 第一视角互动：喂食.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (9, '白毛女-安慰-001', 'video', 'public', '安慰', '白毛女', 1, 'POV 第一视角互动：擦眼泪', '视频成品/POV 第一视角互动：擦眼泪.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (10, '白毛女-话术-001', 'video', 'public', '话术', '白毛女', 1, '“不准看，再看我就要把你拉黑了”', '视频成品/“不准看，再看我就要把你拉黑了”.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (11, '白毛女-话术-002', 'video', 'public', '话术', '白毛女', 1, '“哼，我才不是特意给你买的，只是顺便！”', '视频成品/“哼，我才不是特意给你买的，只是顺便！”.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (12, '白毛女-话术-003', 'video', 'public', '话术', '白毛女', 1, '“没见过这么完美的马甲线吗？准你多看一眼。”', '视频成品/“没见过这么完美的马甲线吗？准你多看一眼。”.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (13, '白毛女-话术-004', 'video', 'public', '话术', '白毛女', 1, '“盯着我，告诉我你的价值在哪里”', '视频成品/“盯着我，告诉我你的价值在哪里”.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (14, '白毛女-话术-005', 'video', 'public', '话术', '白毛女', 1, '“笨蛋，这种事都要我教你吗”', '视频成品/“笨蛋，这种事都要我教你吗”.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (15, '白毛女-话术-006', 'video', 'public', '话术', '白毛女', 1, '“这个罐头打不开，你帮帮我好不好嘛～”', '视频成品/“这个罐头打不开，你帮帮我好不好嘛～”.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (16, '白毛女-话术-007', 'video', 'public', '话术', '白毛女', 1, '“这种低级错误，你觉得我会原谅第二次吗？”', '视频成品/“这种低级错误，你觉得我会原谅第二次吗？”.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (17, '白毛女-摸头杀-001', 'video', 'public', '摸头杀', '白毛女', 1, '【POV 第一视角互动】摸头杀', '视频成品/【POV 第一视角互动】摸头杀.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (18, '白毛女-专注-001', 'video', 'public', '专注', '白毛女', 1, '专注托腮看弹幕 —— 身体微前倾古典优雅', '视频成品/专注托腮看弹幕 —— 身体微前倾古典优雅.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (19, '白毛女-专注-002', 'video', 'public', '专注', '白毛女', 1, '专注托腮看弹幕 —— 身体微前倾清冷御姐', '视频成品/专注托腮看弹幕 —— 身体微前倾清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (20, '白毛女-专注-003', 'video', 'public', '专注', '白毛女', 1, '专注托腮看弹幕 —— 身体微前倾疯批美人', '视频成品/专注托腮看弹幕 —— 身体微前倾疯批美人.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (21, '白毛女-专注-004', 'video', 'public', '专注', '白毛女', 1, '专注托腮看弹幕-身体微...羞涩甜妹', '视频成品/专注托腮看弹幕-身体微...羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (22, '白毛女-专注-005', 'video', 'public', '专注', '白毛女', 1, '专注状态 低头玩手机', '视频成品/专注状态 低头玩手机.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (23, '白毛女-飞吻-001', 'video', 'public', '飞吻', '白毛女', 1, '互动飞吻', '视频成品/互动飞吻.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (24, '白毛女-日常-001', 'video', 'public', '日常', '白毛女', 1, '伸懒腰古典优雅', '视频成品/伸懒腰古典优雅.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (25, '白毛女-日常-002', 'video', 'public', '日常', '白毛女', 1, '伸懒腰清冷御姐', '视频成品/伸懒腰清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (26, '白毛女-日常-003', 'video', 'public', '日常', '白毛女', 1, '伸懒腰疯批美人', '视频成品/伸懒腰疯批美人.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (27, '白毛女-日常-004', 'video', 'public', '日常', '白毛女', 1, '伸懒腰羞涩甜妹', '视频成品/伸懒腰羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (28, '白毛女-感谢-001', 'video', 'public', '感谢', '白毛女', 1, '伸手 3、2、1 倒计时古典优雅', '视频成品/伸手 3、2、1 倒计时古典优雅.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (29, '白毛女-感谢-002', 'video', 'public', '感谢', '白毛女', 1, '伸手 3、2、1 倒计时清冷御姐', '视频成品/伸手 3、2、1 倒计时清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (30, '白毛女-感谢-003', 'video', 'public', '感谢', '白毛女', 1, '伸手 3、2、1 倒计时疯批美人', '视频成品/伸手 3、2、1 倒计时疯批美人.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (31, '白毛女-感谢-004', 'video', 'public', '感谢', '白毛女', 1, '伸手 3、2、1 倒计时羞涩甜妹', '视频成品/伸手 3、2、1 倒计时羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (32, '白毛女-侧颜-001', 'video', 'public', '侧颜', '白毛女', 1, '侧颜45度 展示下颌线，缓慢转头', '视频成品/侧颜45度 展示下颌线，缓慢转头.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (33, '白毛女-侧颜-002', 'video', 'public', '侧颜', '白毛女', 1, '俯身看向镜头', '视频成品/俯身看向镜头.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (34, '白毛女-傲娇-001', 'video', 'public', '傲娇', '白毛女', 1, '假装委屈低头-羞涩甜妹', '视频成品/假装委屈低头-羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (35, '白毛女-傲娇-002', 'video', 'public', '傲娇', '白毛女', 1, '假装委屈低头清冷御姐', '视频成品/假装委屈低头清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (36, '白毛女-傲娇-003', 'video', 'public', '傲娇', '白毛女', 1, '假装生气嘟嘴古典优雅', '视频成品/假装生气嘟嘴古典优雅.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (37, '白毛女-傲娇-004', 'video', 'public', '傲娇', '白毛女', 1, '假装生气嘟嘴清冷御姐', '视频成品/假装生气嘟嘴清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (38, '白毛女-傲娇-005', 'video', 'public', '傲娇', '白毛女', 1, '假装生气嘟嘴疯批美人', '视频成品/假装生气嘟嘴疯批美人.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (39, '白毛女-傲娇-006', 'video', 'public', '傲娇', '白毛女', 1, '假装生气嘟嘴羞涩甜妹', '视频成品/假装生气嘟嘴羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (40, '白毛女-傲娇-007', 'video', 'public', '傲娇', '白毛女', 1, '傲娇 鼓腮帮子撇头', '视频成品/傲娇 鼓腮帮子撇头.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (41, '白毛女-傲娇-008', 'video', 'public', '傲娇', '白毛女', 1, '傲娇型：抱胸转头', '视频成品/傲娇型：抱胸转头.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (42, '白毛女-傲娇-009', 'video', 'public', '傲娇', '白毛女', 1, '傲娇型：轻蔑斜视', '视频成品/傲娇型：轻蔑斜视.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (43, '白毛女-傲娇-010', 'video', 'public', '傲娇', '白毛女', 1, '傲娇大小姐1极度惊恐瞳孔地震、无声颤抖、眼眶红', '视频成品/傲娇大小姐1极度惊恐瞳孔地震、无声颤抖、眼眶红.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (44, '白毛女-傲娇-011', 'video', 'public', '傲娇', '白毛女', 1, '傲娇大小姐傲娇鼓腮子帮襒头', '视频成品/傲娇大小姐傲娇鼓腮子帮襒头.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (45, '白毛女-傲娇-012', 'video', 'public', '傲娇', '白毛女', 1, '傲娇大小姐情绪崩坏冷脸转狂笑', '视频成品/傲娇大小姐情绪崩坏冷脸转狂笑.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (46, '白毛女-傲娇-013', 'video', 'public', '傲娇', '白毛女', 1, '傲娇大小姐惊喜瞬间捂嘴蹦跳', '视频成品/傲娇大小姐惊喜瞬间捂嘴蹦跳.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (47, '白毛女-傲娇-014', 'video', 'public', '傲娇', '白毛女', 1, '傲娇大小姐惊喜瞬间捂嘴蹦跳眼神放光', '视频成品/傲娇大小姐惊喜瞬间捂嘴蹦跳眼神放光.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (48, '白毛女-傲娇-015', 'video', 'public', '傲娇', '白毛女', 1, '傲娇大小姐极度惊恐瞳孔地震、无声颤抖、眼眶红', '视频成品/傲娇大小姐极度惊恐瞳孔地震、无声颤抖、眼眶红.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (49, '白毛女-傲娇-016', 'video', 'public', '傲娇', '白毛女', 1, '傲娇大小姐极近贴近柔和', '视频成品/傲娇大小姐极近贴近柔和.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (50, '白毛女-傲娇-017', 'video', 'public', '傲娇', '白毛女', 1, '傲娇大小姐核心话术 引导关注', '视频成品/傲娇大小姐核心话术 引导关注.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (51, '白毛女-傲娇-018', 'video', 'public', '傲娇', '白毛女', 1, '傲娇大小姐核心话术 引导打赏', '视频成品/傲娇大小姐核心话术 引导打赏.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (52, '白毛女-傲娇-019', 'video', 'public', '傲娇', '白毛女', 1, '傲娇大小姐核心话术情感告白', '视频成品/傲娇大小姐核心话术情感告白.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (53, '白毛女-害羞-001', 'video', 'public', '害羞', '白毛女', 1, '傲娇大小姐模拟拥抱害羞张臂', '视频成品/傲娇大小姐模拟拥抱害羞张臂.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (54, '白毛女-傲娇-020', 'video', 'public', '傲娇', '白毛女', 1, '傲娇大小姐物理安慰手掌贴镜', '视频成品/傲娇大小姐物理安慰手掌贴镜.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (55, '白毛女-傲娇-021', 'video', 'public', '傲娇', '白毛女', 1, '傲娇大小姐物理拉扯向下抓衣角', '视频成品/傲娇大小姐物理拉扯向下抓衣角.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (56, '白毛女-傲娇-022', 'video', 'public', '傲娇', '白毛女', 1, '傲娇大小姐蔑视翻白眼', '视频成品/傲娇大小姐蔑视翻白眼.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (57, '白毛女-傲娇-023', 'video', 'public', '傲娇', '白毛女', 1, '傲娇小姐 斜视，先撇头不看，再偷偷用余光瞄', '视频成品/傲娇小姐 斜视，先撇头不看，再偷偷用余光瞄.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (58, '白毛女-开心-001', 'video', 'public', '开心', '白毛女', 1, '元气型：欢呼雀跃、大幅度挥手、双手点赞。', '视频成品/元气型：欢呼雀跃、大幅度挥手、双手点赞。.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (59, '白毛女-话术-008', 'video', 'public', '话术', '白毛女', 1, '别一直盯着我看，求你了，我我要钻到桌子下面去了', '视频成品/别一直盯着我看，求你了，我我要钻到桌子下面去了.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (60, '白毛女-话术-009', 'video', 'public', '话术', '白毛女', 1, '别乱动，不然我不知道自己会做什么', '视频成品/别乱动，不然我不知道自己会做什么.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (61, '白毛女-撩发-001', 'video', 'public', '撩发', '白毛女', 1, '动作习惯 (10段)：撩拨发际、整理衣领、轻抿', '视频成品/动作习惯 (10段)：撩拨发际、整理衣领、轻抿.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (62, '白毛女-诱惑-001', 'video', 'public', '诱惑', '白毛女', 1, '勾手过来', '视频成品/勾手过来.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (63, '白毛女-撩发-002', 'video', 'public', '撩发', '白毛女', 1, '单手扎马尾', '视频成品/单手扎马尾.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (64, '白毛女-拉扯-001', 'video', 'public', '拉扯', '白毛女', 1, '双手合十再挥手古典优雅', '视频成品/双手合十再挥手古典优雅.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (65, '白毛女-拉扯-002', 'video', 'public', '拉扯', '白毛女', 1, '双手合十再挥手清冷御姐', '视频成品/双手合十再挥手清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (66, '白毛女-拉扯-003', 'video', 'public', '拉扯', '白毛女', 1, '双手合十再挥手疯批美人', '视频成品/双手合十再挥手疯批美人.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (67, '白毛女-拉扯-004', 'video', 'public', '拉扯', '白毛女', 1, '双手合十再挥手羞涩甜妹', '视频成品/双手合十再挥手羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (68, '白毛女-害羞-002', 'video', 'public', '害羞', '白毛女', 1, '双手捂脸害羞，左右摇晃古典优雅', '视频成品/双手捂脸害羞，左右摇晃古典优雅.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (69, '白毛女-害羞-003', 'video', 'public', '害羞', '白毛女', 1, '双手捂脸害羞，左右摇晃清冷御姐', '视频成品/双手捂脸害羞，左右摇晃清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (70, '白毛女-害羞-004', 'video', 'public', '害羞', '白毛女', 1, '双手捂脸害羞，左右摇晃疯批美人', '视频成品/双手捂脸害羞，左右摇晃疯批美人.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (71, '白毛女-害羞-005', 'video', 'public', '害羞', '白毛女', 1, '双手捂脸害羞，左右摇晃羞涩甜妹', '视频成品/双手捂脸害羞，左右摇晃羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (72, '白毛女-比心-002', 'video', 'public', '比心', '白毛女', 1, '双手比关注手势古典优雅', '视频成品/双手比关注手势古典优雅.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (73, '白毛女-比心-003', 'video', 'public', '比心', '白毛女', 1, '双手比关注手势清冷御姐', '视频成品/双手比关注手势清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (74, '白毛女-比心-004', 'video', 'public', '比心', '白毛女', 1, '双手比关注手势疯批美人', '视频成品/双手比关注手势疯批美人.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (75, '白毛女-比心-005', 'video', 'public', '比心', '白毛女', 1, '双手比关注手势羞涩甜妹', '视频成品/双手比关注手势羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (76, '白毛女-开心-002', 'video', 'public', '开心', '白毛女', 1, '双手点赞', '视频成品/双手点赞.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (77, '白毛女-感谢-005', 'video', 'public', '感谢', '白毛女', 1, '古典优雅伸手 3、2、1 倒计时', '视频成品/古典优雅伸手 3、2、1 倒计时.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (78, '白毛女-日常-005', 'video', 'public', '日常', '白毛女', 1, '喝水动作', '视频成品/喝水动作.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (79, '白毛女-话术-010', 'video', 'public', '话术', '白毛女', 1, '嘿，别盯着屏幕看了，出来流点汗', '视频成品/嘿，别盯着屏幕看了，出来流点汗.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (80, '白毛女-侧颜-003', 'video', 'public', '侧颜', '白毛女', 1, '复杂光影走位：彩色氛围灯下走动测试', '视频成品/复杂光影走位：彩色氛围灯下走动测试.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (81, '白毛女-日常-006', 'video', 'public', '日常', '白毛女', 1, '大口喝水并擦嘴的动作', '视频成品/大口喝水并擦嘴的动作.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (82, '白毛女-出入场-002', 'video', 'public', '出入场', '白毛女', 1, '大幅度挥手', '视频成品/大幅度挥手.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (83, '白毛女-害羞-006', 'video', 'public', '害羞', '白毛女', 1, '害羞躲闪', '视频成品/害羞躲闪.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (84, '白毛女-待机-001', 'video', 'public', '待机', '白毛女', 1, '屏息的呼吸', '视频成品/屏息的呼吸.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (85, '白毛女-待机-002', 'video', 'public', '待机', '白毛女', 1, '平静的呼吸', '视频成品/平静的呼吸.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (86, '白毛女-开心-003', 'video', 'public', '开心', '白毛女', 1, '开心双手举高欢呼古典优雅', '视频成品/开心双手举高欢呼古典优雅.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (87, '白毛女-开心-004', 'video', 'public', '开心', '白毛女', 1, '开心双手举高欢呼清冷御姐', '视频成品/开心双手举高欢呼清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (88, '白毛女-开心-005', 'video', 'public', '开心', '白毛女', 1, '开心双手举高欢呼疯批美人', '视频成品/开心双手举高欢呼疯批美人.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (89, '白毛女-开心-006', 'video', 'public', '开心', '白毛女', 1, '开心双手举高欢呼羞涩甜妹', '视频成品/开心双手举高欢呼羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (90, '白毛女-开心-007', 'video', 'public', '开心', '白毛女', 1, '开心表情，双手合十感谢古典优雅', '视频成品/开心表情，双手合十感谢古典优雅.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (91, '白毛女-开心-008', 'video', 'public', '开心', '白毛女', 1, '开心表情，双手合十感谢清冷御姐', '视频成品/开心表情，双手合十感谢清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (92, '白毛女-开心-009', 'video', 'public', '开心', '白毛女', 1, '开心表情，双手合十感谢疯批美人', '视频成品/开心表情，双手合十感谢疯批美人.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (93, '白毛女-开心-010', 'video', 'public', '开心', '白毛女', 1, '开心表情，双手合十感谢羞涩甜妹', '视频成品/开心表情，双手合十感谢羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (94, '白毛女-开心-011', 'video', 'public', '开心', '白毛女', 1, '开心鞠躬欢迎·清冷御姐', '视频成品/开心鞠躬欢迎·清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (95, '白毛女-开心-012', 'video', 'public', '开心', '白毛女', 1, '开心鞠躬欢迎• 古典优雅', '视频成品/开心鞠躬欢迎• 古典优雅.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (96, '白毛女-开心-013', 'video', 'public', '开心', '白毛女', 1, '开心鞠躬欢迎• 疯批美人', '视频成品/开心鞠躬欢迎• 疯批美人.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (97, '白毛女-开心-014', 'video', 'public', '开心', '白毛女', 1, '开心鞠躬欢迎：羞涩甜妹', '视频成品/开心鞠躬欢迎：羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (98, '白毛女-待机-003', 'video', 'public', '待机', '白毛女', 1, '急促的呼吸', '视频成品/急促的呼吸.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (99, '白毛女-变脸-001', 'video', 'public', '变脸', '白毛女', 1, '情绪崩坏 冷脸转狂笑', '视频成品/情绪崩坏 冷脸转狂笑.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (100, '白毛女-变脸-002', 'video', 'public', '变脸', '白毛女', 1, '情绪崩坏 笑转冷脸', '视频成品/情绪崩坏 笑转冷脸.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (101, '白毛女-变脸-003', 'video', 'public', '变脸', '白毛女', 1, '惊喜瞬间：捂嘴蹦跳，眼神放光', '视频成品/惊喜瞬间：捂嘴蹦跳，眼神放光.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (102, '白毛女-比心-006', 'video', 'public', '比心', '白毛女', 1, '手势暗示 (15段)：屏幕比心、飞吻、食指嘘声', '视频成品/手势暗示 (15段)：屏幕比心、飞吻、食指嘘声.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (103, '白毛女-出入场-003', 'video', 'public', '出入场', '白毛女', 1, '手指嘘声', '视频成品/手指嘘声.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (104, '白毛女-专注-006', 'video', 'public', '专注', '白毛女', 1, '手指屏幕念弹幕 —— 身体微前倾，认真表情古典优雅', '视频成品/手指屏幕念弹幕 —— 身体微前倾，认真表情古典优雅.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (105, '白毛女-专注-007', 'video', 'public', '专注', '白毛女', 1, '手指屏幕念弹幕 —— 身体微前倾，认真表情清冷御姐', '视频成品/手指屏幕念弹幕 —— 身体微前倾，认真表情清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (106, '白毛女-专注-008', 'video', 'public', '专注', '白毛女', 1, '手指屏幕念弹幕 —— 身体微前倾，认真表情疯批美人', '视频成品/手指屏幕念弹幕 —— 身体微前倾，认真表情疯批美人.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (107, '白毛女-专注-009', 'video', 'public', '专注', '白毛女', 1, '手指屏幕念弹幕 —— 身体微前倾，认真表情羞涩甜妹', '视频成品/手指屏幕念弹幕 —— 身体微前倾，认真表情羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (108, '白毛女-专注-010', 'video', 'public', '专注', '白毛女', 1, '手指屏幕念弹幕-羞涩甜妹', '视频成品/手指屏幕念弹幕-羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (109, '白毛女-日常-007', 'video', 'public', '日常', '白毛女', 1, '打哈欠古典优雅', '视频成品/打哈欠古典优雅.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (110, '白毛女-日常-008', 'video', 'public', '日常', '白毛女', 1, '打哈欠清冷御姐', '视频成品/打哈欠清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (111, '白毛女-日常-009', 'video', 'public', '日常', '白毛女', 1, '打哈欠疯批美人', '视频成品/打哈欠疯批美人.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (112, '白毛女-日常-010', 'video', 'public', '日常', '白毛女', 1, '打哈欠羞涩甜妹', '视频成品/打哈欠羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (113, '白毛女-出入场-004', 'video', 'public', '出入场', '白毛女', 1, '打招呼古典优雅', '视频成品/打招呼古典优雅.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (114, '白毛女-比心-007', 'video', 'public', '比心', '白毛女', 1, '打招呼微笑单比心羞涩甜妹', '视频成品/打招呼微笑单比心羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (115, '白毛女-出入场-005', 'video', 'public', '出入场', '白毛女', 1, '打招呼清冷御姐', '视频成品/打招呼清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (116, '白毛女-出入场-006', 'video', 'public', '出入场', '白毛女', 1, '打招呼疯批美人', '视频成品/打招呼疯批美人.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (117, '白毛女-投喂-002', 'video', 'public', '投喂', '白毛女', 1, '投喂分享：手拿勺子或零食伸向镜头中心，张嘴做“啊—的配合', '视频成品/投喂分享：手拿勺子或零食伸向镜头中心，张嘴做“啊—的配合.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (118, '白毛女-害羞-007', 'video', 'public', '害羞', '白毛女', 1, '拉扯衣角：手向下抓握并轻微晃动，配合焦急或害羞的表情', '视频成品/拉扯衣角：手向下抓握并轻微晃动，配合焦急或害羞的表情.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (119, '白毛女-拉扯-005', 'video', 'public', '拉扯', '白毛女', 1, '拉扯衣领边缘', '视频成品/拉扯衣领边缘.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (120, '白毛女-开心-015', 'video', 'public', '开心', '白毛女', 1, '拍腿大笑古典优雅', '视频成品/拍腿大笑古典优雅.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (121, '白毛女-开心-016', 'video', 'public', '开心', '白毛女', 1, '拍腿大笑清冷御姐', '视频成品/拍腿大笑清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (122, '白毛女-开心-017', 'video', 'public', '开心', '白毛女', 1, '拍腿大笑疯批美人', '视频成品/拍腿大笑疯批美人.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (123, '白毛女-开心-018', 'video', 'public', '开心', '白毛女', 1, '拍腿大笑羞涩甜妹', '视频成品/拍腿大笑羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (124, '白毛女-专注-011', 'video', 'public', '专注', '白毛女', 1, '指点下方', '视频成品/指点下方.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (125, '白毛女-开心-019', 'video', 'public', '开心', '白毛女', 1, '挑眉调侃古典优雅', '视频成品/挑眉调侃古典优雅.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (126, '白毛女-开心-020', 'video', 'public', '开心', '白毛女', 1, '挑眉调侃清冷御姐', '视频成品/挑眉调侃清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (127, '白毛女-开心-021', 'video', 'public', '开心', '白毛女', 1, '挑眉调侃疯批美人', '视频成品/挑眉调侃疯批美人.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (128, '白毛女-开心-022', 'video', 'public', '开心', '白毛女', 1, '挑眉调侃羞涩甜妹', '视频成品/挑眉调侃羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (129, '白毛女-出入场-007', 'video', 'public', '出入场', '白毛女', 1, '挥手', '视频成品/挥手.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (130, '白毛女-变脸-004', 'video', 'public', '变脸', '白毛女', 1, '捂嘴惊喜，惊讶呆立原地古典优雅', '视频成品/捂嘴惊喜，惊讶呆立原地古典优雅.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (131, '白毛女-变脸-005', 'video', 'public', '变脸', '白毛女', 1, '捂嘴惊喜，惊讶呆立原地清冷御姐', '视频成品/捂嘴惊喜，惊讶呆立原地清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (132, '白毛女-变脸-006', 'video', 'public', '变脸', '白毛女', 1, '捂嘴惊喜，惊讶呆立原地疯批美人', '视频成品/捂嘴惊喜，惊讶呆立原地疯批美人.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (133, '白毛女-变脸-007', 'video', 'public', '变脸', '白毛女', 1, '捂嘴惊喜，惊讶呆立原地：羞涩甜妹', '视频成品/捂嘴惊喜，惊讶呆立原地：羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (134, '白毛女-开心-023', 'video', 'public', '开心', '白毛女', 1, '捂嘴笑 羞涩甜妹', '视频成品/捂嘴笑 羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (135, '白毛女-开心-024', 'video', 'public', '开心', '白毛女', 1, '捂嘴笑古典优雅', '视频成品/捂嘴笑古典优雅.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (136, '白毛女-开心-025', 'video', 'public', '开心', '白毛女', 1, '捂嘴笑清冷御姐', '视频成品/捂嘴笑清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (137, '白毛女-开心-026', 'video', 'public', '开心', '白毛女', 1, '捂嘴笑疯批美人', '视频成品/捂嘴笑疯批美人.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (138, '白毛女-害羞-008', 'video', 'public', '害羞', '白毛女', 1, '掩耳盗铃，用手捂住大半张脸，只露出一只眼睛偷看', '视频成品/掩耳盗铃，用手捂住大半张脸，只露出一只眼睛偷看.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (139, '白毛女-日常-011', 'video', 'public', '日常', '白毛女', 1, '揉眼睛古典优雅', '视频成品/揉眼睛古典优雅.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (140, '白毛女-日常-012', 'video', 'public', '日常', '白毛女', 1, '揉眼睛清冷御姐', '视频成品/揉眼睛清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (141, '白毛女-日常-013', 'video', 'public', '日常', '白毛女', 1, '揉眼睛疯批美人', '视频成品/揉眼睛疯批美人.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (142, '白毛女-日常-014', 'video', 'public', '日常', '白毛女', 1, '揉眼睛羞涩甜妹', '视频成品/揉眼睛羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (143, '白毛女-开心-027', 'video', 'public', '开心', '白毛女', 1, '握拳加油摇摆古典优雅', '视频成品/握拳加油摇摆古典优雅.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (144, '白毛女-开心-028', 'video', 'public', '开心', '白毛女', 1, '握拳加油摇摆清冷御姐', '视频成品/握拳加油摇摆清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (145, '白毛女-开心-029', 'video', 'public', '开心', '白毛女', 1, '握拳加油摇摆疯批美人', '视频成品/握拳加油摇摆疯批美人.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (146, '白毛女-开心-030', 'video', 'public', '开心', '白毛女', 1, '握拳加油摇摆羞涩甜妹', '视频成品/握拳加油摇摆羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (147, '白毛女-出入场-008', 'video', 'public', '出入场', '白毛女', 1, '摇头', '视频成品/摇头.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (148, '白毛女-专注-012', 'video', 'public', '专注', '白毛女', 1, '摇头笑回应 —— 前倾看弹幕，微笑摇头古典优雅', '视频成品/摇头笑回应 —— 前倾看弹幕，微笑摇头古典优雅.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (149, '白毛女-专注-013', 'video', 'public', '专注', '白毛女', 1, '摇头笑回应 —— 前倾看弹幕，微笑摇头清冷御姐', '视频成品/摇头笑回应 —— 前倾看弹幕，微笑摇头清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (150, '白毛女-专注-014', 'video', 'public', '专注', '白毛女', 1, '摇头笑回应 —— 前倾看弹幕，微笑摇头疯批美人', '视频成品/摇头笑回应 —— 前倾看弹幕，微笑摇头疯批美人.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (151, '白毛女-专注-015', 'video', 'public', '专注', '白毛女', 1, '摇头笑回应 —— 前倾看弹幕，微笑摇头羞涩甜妹', '视频成品/摇头笑回应 —— 前倾看弹幕，微笑摇头羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (152, '白毛女-摸头杀-002', 'video', 'public', '摸头杀', '白毛女', 1, '摸头杀捏脸：模特手向上伸出画面（模拟摸对方头），眼神温柔向下看。拉扯衣角：手向 ', '视频成品/摸头杀捏脸：模特手向上伸出画面（模拟摸对方头），眼神温柔向下看。拉扯衣角：手向 .mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (153, '白毛女-撩发-003', 'video', 'public', '撩发', '白毛女', 1, '撩发至耳后-清冷御姐', '视频成品/撩发至耳后-清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (154, '白毛女-撩发-004', 'video', 'public', '撩发', '白毛女', 1, '撩发至耳后-羞涩甜妹', '视频成品/撩发至耳后-羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (155, '白毛女-撩发-005', 'video', 'public', '撩发', '白毛女', 1, '撩发至耳后古典优雅', '视频成品/撩发至耳后古典优雅.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (156, '白毛女-撩发-006', 'video', 'public', '撩发', '白毛女', 1, '撩发至耳后清冷御姐', '视频成品/撩发至耳后清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (157, '白毛女-撩发-007', 'video', 'public', '撩发', '白毛女', 1, '撩发至耳后疯批美人', '视频成品/撩发至耳后疯批美人.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (158, '白毛女-撩发-008', 'video', 'public', '撩发', '白毛女', 1, '撩发至耳后羞涩甜妹', '视频成品/撩发至耳后羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (159, '白毛女-撩发-009', 'video', 'public', '撩发', '白毛女', 1, '撩拨发际', '视频成品/撩拨发际.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (160, '白毛女-撩发-010', 'video', 'public', '撩发', '白毛女', 1, '整理衣领', '视频成品/整理衣领.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (161, '白毛女-害羞-009', 'video', 'public', '害羞', '白毛女', 1, '极度羞怯 频繁闪躲，看 1 秒低头 3 秒，手挡住脸。 持续的浅呼吸（胸腔起伏明显）。', '视频成品/极度羞怯 频繁闪躲，看 1 秒低头 3 秒，手挡住脸。 持续的浅呼吸（胸腔起伏明显）。.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (162, '白毛女-专注-016', 'video', 'public', '专注', '白毛女', 1, '极度羞怯专注状态玩手机', '视频成品/极度羞怯专注状态玩手机.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (163, '白毛女-专注-017', 'video', 'public', '专注', '白毛女', 1, '极度羞怯专注状态看书', '视频成品/极度羞怯专注状态看书.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (164, '白毛女-侧颜-004', 'video', 'public', '侧颜', '白毛女', 1, '极度羞怯侧颜45度', '视频成品/极度羞怯侧颜45度.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (165, '白毛女-诱惑-002', 'video', 'public', '诱惑', '白毛女', 1, '极度羞怯极近贴镜偏执死盯', '视频成品/极度羞怯极近贴镜偏执死盯.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (166, '白毛女-诱惑-003', 'video', 'public', '诱惑', '白毛女', 1, '极度羞怯极近贴镜柔和', '视频成品/极度羞怯极近贴镜柔和.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (167, '白毛女-待机-004', 'video', 'public', '待机', '白毛女', 1, '极度羞怯标准呼吸眼神清冷', '视频成品/极度羞怯标准呼吸眼神清冷.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (168, '白毛女-待机-005', 'video', 'public', '待机', '白毛女', 1, '极度羞怯标准呼吸眼神温柔', '视频成品/极度羞怯标准呼吸眼神温柔.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (169, '白毛女-话术-011', 'video', 'public', '话术', '白毛女', 1, '极度羞怯核心话术情感告白', '视频成品/极度羞怯核心话术情感告白.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (170, '白毛女-害羞-010', 'video', 'public', '害羞', '白毛女', 1, '极度羞怯模拟拥抱害羞张臂', '视频成品/极度羞怯模拟拥抱害羞张臂.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (171, '白毛女-拥抱-001', 'video', 'public', '拥抱', '白毛女', 1, '极度羞怯模拟拥抱霸道环抱', '视频成品/极度羞怯模拟拥抱霸道环抱.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (172, '白毛女-日常-015', 'video', 'public', '日常', '白毛女', 1, '极度羞怯深夜困倦', '视频成品/极度羞怯深夜困倦.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (173, '白毛女-安慰-002', 'video', 'public', '安慰', '白毛女', 1, '极度羞怯物理安慰手伸向镜头抹泪', '视频成品/极度羞怯物理安慰手伸向镜头抹泪.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (174, '白毛女-安慰-003', 'video', 'public', '安慰', '白毛女', 1, '极度羞怯物理安慰手掌贴镜', '视频成品/极度羞怯物理安慰手掌贴镜.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (175, '白毛女-投喂-003', 'video', 'public', '投喂', '白毛女', 1, '极度羞怯物理投喂勺子', '视频成品/极度羞怯物理投喂勺子.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (176, '白毛女-投喂-004', 'video', 'public', '投喂', '白毛女', 1, '极度羞怯物理投喂零食', '视频成品/极度羞怯物理投喂零食.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (177, '白毛女-拉扯-006', 'video', 'public', '拉扯', '白毛女', 1, '极度羞怯物理拉扯双手合十', '视频成品/极度羞怯物理拉扯双手合十.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (178, '白毛女-拉扯-007', 'video', 'public', '拉扯', '白毛女', 1, '极度羞怯物理拉扯衣角', '视频成品/极度羞怯物理拉扯衣角.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (179, '白毛女-出入场-009', 'video', 'public', '出入场', '白毛女', 1, '极度羞怯离场', '视频成品/极度羞怯离场.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (180, '白毛女-出入场-010', 'video', 'public', '出入场', '白毛女', 1, '极度羞怯进场', '视频成品/极度羞怯进场.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (181, '白毛女-害羞-011', 'video', 'public', '害羞', '白毛女', 1, '极度羞怯遮挡偷看单手遮眼', '视频成品/极度羞怯遮挡偷看单手遮眼.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (182, '白毛女-害羞-012', 'video', 'public', '害羞', '白毛女', 1, '极度羞怯遮挡偷看双手捂脸', '视频成品/极度羞怯遮挡偷看双手捂脸.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (183, '白毛女-变脸-008', 'video', 'public', '变脸', '白毛女', 1, '极度羞涩极度惊恐瞳孔地震、无声颤抖、眼眶红', '视频成品/极度羞涩极度惊恐瞳孔地震、无声颤抖、眼眶红.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (184, '白毛女-诱惑-004', 'video', 'public', '诱惑', '白毛女', 1, '极近贴镜 偏执死盯', '视频成品/极近贴镜 偏执死盯.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (185, '白毛女-诱惑-005', 'video', 'public', '诱惑', '白毛女', 1, '极近贴镜 审视', '视频成品/极近贴镜 审视.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (186, '白毛女-诱惑-006', 'video', 'public', '诱惑', '白毛女', 1, '极近贴镜 柔和', '视频成品/极近贴镜 柔和.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (187, '白毛女-傲娇-024', 'video', 'public', '傲娇', '白毛女', 1, '柔和 惩罚蔑视', '视频成品/柔和 惩罚蔑视.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (188, '白毛女-投喂-005', 'video', 'public', '投喂', '白毛女', 1, '柔和奖励投喂', '视频成品/柔和奖励投喂.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (189, '白毛女-诱惑-007', 'video', 'public', '诱惑', '白毛女', 1, '柔和指尖引诱勾手', '视频成品/柔和指尖引诱勾手.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (190, '白毛女-诱惑-008', 'video', 'public', '诱惑', '白毛女', 1, '柔和束发散发转换', '视频成品/柔和束发散发转换.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (191, '白毛女-出入场-011', 'video', 'public', '出入场', '白毛女', 1, '柔和禁忌暗示（嘘）', '视频成品/柔和禁忌暗示（嘘）.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (192, '白毛女-出入场-012', 'video', 'public', '出入场', '白毛女', 1, '柔和镜头雾化哈气', '视频成品/柔和镜头雾化哈气.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (193, '白毛女-拉扯-008', 'video', 'public', '拉扯', '白毛女', 1, '柔和颈部与锁骨拉扯', '视频成品/柔和颈部与锁骨拉扯.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (194, '白毛女-待机-006', 'video', 'public', '待机', '白毛女', 1, '标准呼吸 眼神清冷', '视频成品/标准呼吸 眼神清冷.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (195, '白毛女-待机-007', 'video', 'public', '待机', '白毛女', 1, '标准呼吸 眼神温柔', '视频成品/标准呼吸 眼神温柔.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (196, '白毛女-害羞-013', 'video', 'public', '害羞', '白毛女', 1, '模拟拥抱 害羞张臂', '视频成品/模拟拥抱 害羞张臂.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (197, '白毛女-拥抱-002', 'video', 'public', '拥抱', '白毛女', 1, '模拟拥抱 霸道环抱定格', '视频成品/模拟拥抱 霸道环抱定格.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (198, '白毛女-开心-031', 'video', 'public', '开心', '白毛女', 1, '欢呼雀跃', '视频成品/欢呼雀跃.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (199, '白毛女-摸头杀-003', 'video', 'public', '摸头杀', '白毛女', 1, '歪头杀', '视频成品/歪头杀.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (200, '白毛女-专注-018', 'video', 'public', '专注', '白毛女', 1, '歪头看弹幕-羞涩甜妹', '视频成品/歪头看弹幕-羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (201, '白毛女-感谢-006', 'video', 'public', '感谢', '白毛女', 1, '比冲手势古典优雅', '视频成品/比冲手势古典优雅.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (202, '白毛女-感谢-007', 'video', 'public', '感谢', '白毛女', 1, '比冲手势抽奖古典优雅', '视频成品/比冲手势抽奖古典优雅.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (203, '白毛女-感谢-008', 'video', 'public', '感谢', '白毛女', 1, '比冲手势抽奖清冷御姐', '视频成品/比冲手势抽奖清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (204, '白毛女-感谢-009', 'video', 'public', '感谢', '白毛女', 1, '比冲手势抽奖疯批美人', '视频成品/比冲手势抽奖疯批美人.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (205, '白毛女-感谢-010', 'video', 'public', '感谢', '白毛女', 1, '比冲手势抽奖羞涩甜妹', '视频成品/比冲手势抽奖羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (206, '白毛女-感谢-011', 'video', 'public', '感谢', '白毛女', 1, '比冲手势清冷御姐', '视频成品/比冲手势清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (207, '白毛女-感谢-012', 'video', 'public', '感谢', '白毛女', 1, '比冲手势疯批美人', '视频成品/比冲手势疯批美人.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (208, '白毛女-感谢-013', 'video', 'public', '感谢', '白毛女', 1, '比冲手势羞涩甜妹', '视频成品/比冲手势羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (209, '白毛女-比心-008', 'video', 'public', '比心', '白毛女', 1, '比心', '视频成品/比心.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (210, '白毛女-比心-009', 'video', 'public', '比心', '白毛女', 1, '比心连击 —— 反复多次比心古典优雅', '视频成品/比心连击 —— 反复多次比心古典优雅.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (211, '白毛女-比心-010', 'video', 'public', '比心', '白毛女', 1, '比心连击 —— 反复多次比心清冷御姐', '视频成品/比心连击 —— 反复多次比心清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (212, '白毛女-比心-011', 'video', 'public', '比心', '白毛女', 1, '比心连击 —— 反复多次比心疯批美人', '视频成品/比心连击 —— 反复多次比心疯批美人.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (213, '白毛女-比心-012', 'video', 'public', '比心', '白毛女', 1, '比心连击一反复多次比心：羞涩甜妹', '视频成品/比心连击一反复多次比心：羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (214, '白毛女-比心-013', 'video', 'public', '比心', '白毛女', 1, '比晚安手势古典优雅', '视频成品/比晚安手势古典优雅.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (215, '白毛女-比心-014', 'video', 'public', '比心', '白毛女', 1, '比晚安手势清冷御姐', '视频成品/比晚安手势清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (216, '白毛女-比心-015', 'video', 'public', '比心', '白毛女', 1, '比晚安手势疯批美人', '视频成品/比晚安手势疯批美人.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (217, '白毛女-比心-016', 'video', 'public', '比心', '白毛女', 1, '比晚安手势羞涩甜妹', '视频成品/比晚安手势羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (218, '白毛女-待机-008', 'video', 'public', '待机', '白毛女', 1, '沉重的呼吸', '视频成品/沉重的呼吸.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (219, '白毛女-待机-009', 'video', 'public', '待机', '白毛女', 1, '注视镜头', '视频成品/注视镜头.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (220, '白毛女-变脸-009', 'video', 'public', '变脸', '白毛女', 1, '活力辣妹1极度惊恐瞳孔地震、无声颤抖、眼眶红', '视频成品/活力辣妹1极度惊恐瞳孔地震、无声颤抖、眼眶红.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (221, '白毛女-专注-019', 'video', 'public', '专注', '白毛女', 1, '活力辣妹专注状态完手机', '视频成品/活力辣妹专注状态完手机.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (222, '白毛女-专注-020', 'video', 'public', '专注', '白毛女', 1, '活力辣妹专注状态看书', '视频成品/活力辣妹专注状态看书.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (223, '白毛女-侧颜-005', 'video', 'public', '侧颜', '白毛女', 1, '活力辣妹侧颜45度', '视频成品/活力辣妹侧颜45度.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (224, '白毛女-傲娇-025', 'video', 'public', '傲娇', '白毛女', 1, '活力辣妹傲娇鼓腮帮子襒头', '视频成品/活力辣妹傲娇鼓腮帮子襒头.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (225, '白毛女-变脸-010', 'video', 'public', '变脸', '白毛女', 1, '活力辣妹冷脸转笑', '视频成品/活力辣妹冷脸转笑.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (226, '白毛女-变脸-011', 'video', 'public', '变脸', '白毛女', 1, '活力辣妹惊喜瞬间 捂嘴蹦跳双眼放光', '视频成品/活力辣妹惊喜瞬间 捂嘴蹦跳双眼放光.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (227, '白毛女-变脸-012', 'video', 'public', '变脸', '白毛女', 1, '活力辣妹极度惊恐瞳孔地震、无声颤抖、眼眶红', '视频成品/活力辣妹极度惊恐瞳孔地震、无声颤抖、眼眶红.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (228, '白毛女-待机-010', 'video', 'public', '待机', '白毛女', 1, '活力辣妹标准呼吸眼神清冷', '视频成品/活力辣妹标准呼吸眼神清冷.mp4', 0, '', 1, '2026-07-27 18:12:30');
INSERT INTO `lp_media_asset` VALUES (229, '白毛女-待机-011', 'video', 'public', '待机', '白毛女', 1, '活力辣妹标注呼吸眼神温柔', '视频成品/活力辣妹标注呼吸眼神温柔.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (230, '白毛女-话术-012', 'video', 'public', '话术', '白毛女', 1, '活力辣妹核心话术 引导关注', '视频成品/活力辣妹核心话术 引导关注.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (231, '白毛女-话术-013', 'video', 'public', '话术', '白毛女', 1, '活力辣妹核心话术引导打赏', '视频成品/活力辣妹核心话术引导打赏.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (232, '白毛女-话术-014', 'video', 'public', '话术', '白毛女', 1, '活力辣妹核心话术情感告白', '视频成品/活力辣妹核心话术情感告白.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (233, '白毛女-害羞-014', 'video', 'public', '害羞', '白毛女', 1, '活力辣妹模拟拥抱害羞张臂', '视频成品/活力辣妹模拟拥抱害羞张臂.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (234, '白毛女-拥抱-003', 'video', 'public', '拥抱', '白毛女', 1, '活力辣妹模拟拥抱霸道环抱', '视频成品/活力辣妹模拟拥抱霸道环抱.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (235, '白毛女-日常-016', 'video', 'public', '日常', '白毛女', 1, '活力辣妹深夜困倦', '视频成品/活力辣妹深夜困倦.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (236, '白毛女-安慰-004', 'video', 'public', '安慰', '白毛女', 1, '活力辣妹物理安慰手伸向镜头抹泪', '视频成品/活力辣妹物理安慰手伸向镜头抹泪.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (237, '白毛女-安慰-005', 'video', 'public', '安慰', '白毛女', 1, '活力辣妹物理安慰手掌贴镜', '视频成品/活力辣妹物理安慰手掌贴镜.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (238, '白毛女-投喂-006', 'video', 'public', '投喂', '白毛女', 1, '活力辣妹物理投喂勺子', '视频成品/活力辣妹物理投喂勺子.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (239, '白毛女-投喂-007', 'video', 'public', '投喂', '白毛女', 1, '活力辣妹物理投喂零食', '视频成品/活力辣妹物理投喂零食.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (240, '白毛女-拉扯-009', 'video', 'public', '拉扯', '白毛女', 1, '活力辣妹物理拉扯双手合十', '视频成品/活力辣妹物理拉扯双手合十.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (241, '白毛女-拉扯-010', 'video', 'public', '拉扯', '白毛女', 1, '活力辣妹物理拉扯衣角', '视频成品/活力辣妹物理拉扯衣角.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (242, '白毛女-出入场-013', 'video', 'public', '出入场', '白毛女', 1, '活力辣妹离场', '视频成品/活力辣妹离场.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (243, '白毛女-变脸-013', 'video', 'public', '变脸', '白毛女', 1, '活力辣妹笑转冷脸', '视频成品/活力辣妹笑转冷脸.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (244, '白毛女-傲娇-026', 'video', 'public', '傲娇', '白毛女', 1, '活力辣妹蔑视翻白眼', '视频成品/活力辣妹蔑视翻白眼.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (245, '白毛女-出入场-014', 'video', 'public', '出入场', '白毛女', 1, '活力辣妹进场', '视频成品/活力辣妹进场.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (246, '白毛女-害羞-015', 'video', 'public', '害羞', '白毛女', 1, '活力辣妹遮挡偷看单手遮眼', '视频成品/活力辣妹遮挡偷看单手遮眼.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (247, '白毛女-害羞-016', 'video', 'public', '害羞', '白毛女', 1, '活力辣妹遮挡偷看双手捂脸', '视频成品/活力辣妹遮挡偷看双手捂脸.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (248, '白毛女-撩发-011', 'video', 'public', '撩发', '白毛女', 1, '活力运动系整理头发', '视频成品/活力运动系整理头发.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (249, '白毛女-日常-017', 'video', 'public', '日常', '白毛女', 1, '活力运动系，喝水', '视频成品/活力运动系，喝水.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (250, '白毛女-开心-032', 'video', 'public', '开心', '白毛女', 1, '活力运动系，对着镜头打气', '视频成品/活力运动系，对着镜头打气.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (251, '白毛女-开心-033', 'video', 'public', '开心', '白毛女', 1, '活力运动系，开怀大笑', '视频成品/活力运动系，开怀大笑.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (252, '白毛女-撩发-012', 'video', 'public', '撩发', '白毛女', 1, '活力运动系，擦汗', '视频成品/活力运动系，擦汗.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (253, '白毛女-感谢-014', 'video', 'public', '感谢', '白毛女', 1, '浅浅鞠躬表示感谢                      羞涩甜妹', '视频成品/浅浅鞠躬表示感谢                      羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (254, '白毛女-感谢-015', 'video', 'public', '感谢', '白毛女', 1, '浅浅鞠躬表示感谢古典优雅', '视频成品/浅浅鞠躬表示感谢古典优雅.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (255, '白毛女-感谢-016', 'video', 'public', '感谢', '白毛女', 1, '浅浅鞠躬表示感谢清冷御姐', '视频成品/浅浅鞠躬表示感谢清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (256, '白毛女-感谢-017', 'video', 'public', '感谢', '白毛女', 1, '浅浅鞠躬表示感谢疯批美人', '视频成品/浅浅鞠躬表示感谢疯批美人.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (257, '白毛女-待机-012', 'video', 'public', '待机', '白毛女', 1, '深夜困倦 托腮，眼皮微重，浅呼吸-需要剪辑', '视频成品/深夜困倦 托腮，眼皮微重，浅呼吸-需要剪辑.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (258, '白毛女-傲娇-027', 'video', 'public', '傲娇', '白毛女', 1, '清冷 惩罚蔑视', '视频成品/清冷 惩罚蔑视.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (259, '白毛女-投喂-008', 'video', 'public', '投喂', '白毛女', 1, '清冷奖励投喂', '视频成品/清冷奖励投喂.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (260, '白毛女-诱惑-009', 'video', 'public', '诱惑', '白毛女', 1, '清冷指尖引诱勾手', '视频成品/清冷指尖引诱勾手.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (261, '白毛女-诱惑-010', 'video', 'public', '诱惑', '白毛女', 1, '清冷束发散发转换', '视频成品/清冷束发散发转换.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (262, '白毛女-出入场-015', 'video', 'public', '出入场', '白毛女', 1, '清冷禁忌暗示（嘘）', '视频成品/清冷禁忌暗示（嘘）.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (263, '白毛女-出入场-016', 'video', 'public', '出入场', '白毛女', 1, '清冷镜头雾化哈气', '视频成品/清冷镜头雾化哈气.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (264, '白毛女-拉扯-011', 'video', 'public', '拉扯', '白毛女', 1, '清冷颈部与锁骨拉扯', '视频成品/清冷颈部与锁骨拉扯.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (265, '白毛女-摸头杀-004', 'video', 'public', '摸头杀', '白毛女', 1, '温柔疗愈系，歪头杀，双手捧脸，对着镜头哈气写字，轻声耳语口型 (2)', '视频成品/温柔疗愈系，歪头杀，双手捧脸，对着镜头哈气写字，轻声耳语口型 (2).mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (266, '白毛女-出入场-017', 'video', 'public', '出入场', '白毛女', 1, '点头', '视频成品/点头.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (267, '白毛女-出入场-018', 'video', 'public', '出入场', '白毛女', 1, '点头回应弹幕 —— 边看边点头古典优雅', '视频成品/点头回应弹幕 —— 边看边点头古典优雅.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (268, '白毛女-出入场-019', 'video', 'public', '出入场', '白毛女', 1, '点头回应弹幕 —— 边看边点头清冷御姐', '视频成品/点头回应弹幕 —— 边看边点头清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (269, '白毛女-出入场-020', 'video', 'public', '出入场', '白毛女', 1, '点头回应弹幕 —— 边看边点头疯批美人', '视频成品/点头回应弹幕 —— 边看边点头疯批美人.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (270, '白毛女-出入场-021', 'video', 'public', '出入场', '白毛女', 1, '点头回应弹幕 —— 边看边点头羞涩甜妹', '视频成品/点头回应弹幕 —— 边看边点头羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (271, '白毛女-开心-034', 'video', 'public', '开心', '白毛女', 1, '点头致谢 —— 看屏幕，开心抿嘴，频繁眨眼', '视频成品/点头致谢 —— 看屏幕，开心抿嘴，频繁眨眼.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (272, '白毛女-开心-035', 'video', 'public', '开心', '白毛女', 1, '点头致谢 —— 看屏幕，开心抿嘴，频繁眨眼点头古典优雅', '视频成品/点头致谢 —— 看屏幕，开心抿嘴，频繁眨眼点头古典优雅.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (273, '白毛女-开心-036', 'video', 'public', '开心', '白毛女', 1, '点头致谢 —— 看屏幕，开心抿嘴，频繁眨眼点头清冷御姐', '视频成品/点头致谢 —— 看屏幕，开心抿嘴，频繁眨眼点头清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (274, '白毛女-开心-037', 'video', 'public', '开心', '白毛女', 1, '点头致谢 —— 看屏幕，开心抿嘴，频繁眨眼点头疯批美人', '视频成品/点头致谢 —— 看屏幕，开心抿嘴，频繁眨眼点头疯批美人.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (275, '白毛女-拉扯-012', 'video', 'public', '拉扯', '白毛女', 1, '物理压迫突然凑近镜头挡住所有光线', '视频成品/物理压迫突然凑近镜头挡住所有光线.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (276, '白毛女-安慰-006', 'video', 'public', '安慰', '白毛女', 1, '物理安慰 手伸向镜头抹泪  手掌贴镜。 ', '视频成品/物理安慰 手伸向镜头抹泪  手掌贴镜。 .mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (277, '白毛女-投喂-009', 'video', 'public', '投喂', '白毛女', 1, '物理投喂 手持勺子零食伸向镜头。', '视频成品/物理投喂 手持勺子零食伸向镜头。.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (278, '白毛女-拉扯-013', 'video', 'public', '拉扯', '白毛女', 1, '物理拉扯 双手合十摇晃', '视频成品/物理拉扯 双手合十摇晃.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (279, '白毛女-拉扯-014', 'video', 'public', '拉扯', '白毛女', 1, '物理拉扯 向下抓衣角', '视频成品/物理拉扯 向下抓衣角.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (280, '白毛女-诱惑-011', 'video', 'public', '诱惑', '白毛女', 1, '由远及近对焦', '视频成品/由远及近对焦.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (281, '白毛女-话术-015', 'video', 'public', '话术', '白毛女', 1, '疯批美人 引导打赏', '视频成品/疯批美人 引导打赏.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (282, '白毛女-待机-013', 'video', 'public', '待机', '白毛女', 1, '疯批美人 死盯着看，30秒不眨眼，瞳孔突然放大 屏息急促爆发 禁止温柔的微笑', '视频成品/疯批美人 死盯着看，30秒不眨眼，瞳孔突然放大 屏息急促爆发 禁止温柔的微笑.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (283, '白毛女-害羞-017', 'video', 'public', '害羞', '白毛女', 1, '疯批美人 遮挡偷看 单手遮眼', '视频成品/疯批美人 遮挡偷看 单手遮眼.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (284, '白毛女-变脸-014', 'video', 'public', '变脸', '白毛女', 1, '疯批美人1极度惊恐瞳孔地震、无声颤抖、眼眶红', '视频成品/疯批美人1极度惊恐瞳孔地震、无声颤抖、眼眶红.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (285, '白毛女-专注-021', 'video', 'public', '专注', '白毛女', 1, '疯批美人专注状态我手机', '视频成品/疯批美人专注状态我手机.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (286, '白毛女-专注-022', 'video', 'public', '专注', '白毛女', 1, '疯批美人专注状态看书', '视频成品/疯批美人专注状态看书.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (287, '白毛女-感谢-018', 'video', 'public', '感谢', '白毛女', 1, '疯批美人伸手 3、2、1 倒计时', '视频成品/疯批美人伸手 3、2、1 倒计时.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (288, '白毛女-侧颜-006', 'video', 'public', '侧颜', '白毛女', 1, '疯批美人侧颜45度', '视频成品/疯批美人侧颜45度.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (289, '白毛女-傲娇-028', 'video', 'public', '傲娇', '白毛女', 1, '疯批美人傲娇鼓腮帮子撇头', '视频成品/疯批美人傲娇鼓腮帮子撇头.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (290, '白毛女-话术-016', 'video', 'public', '话术', '白毛女', 1, '疯批美人情感告白', '视频成品/疯批美人情感告白.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (291, '白毛女-变脸-015', 'video', 'public', '变脸', '白毛女', 1, '疯批美人情绪崩坏冷脸转笑', '视频成品/疯批美人情绪崩坏冷脸转笑.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (292, '白毛女-变脸-016', 'video', 'public', '变脸', '白毛女', 1, '疯批美人情绪崩坏笑转冷脸', '视频成品/疯批美人情绪崩坏笑转冷脸.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (293, '白毛女-变脸-017', 'video', 'public', '变脸', '白毛女', 1, '疯批美人惊喜瞬间 捂嘴蹦跳眼神放光', '视频成品/疯批美人惊喜瞬间 捂嘴蹦跳眼神放光.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (294, '白毛女-变脸-018', 'video', 'public', '变脸', '白毛女', 1, '疯批美人极度惊恐瞳孔地震、无声颤抖、眼眶红', '视频成品/疯批美人极度惊恐瞳孔地震、无声颤抖、眼眶红.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (295, '白毛女-待机-014', 'video', 'public', '待机', '白毛女', 1, '疯批美人标准呼吸眼神清冷', '视频成品/疯批美人标准呼吸眼神清冷.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (296, '白毛女-待机-015', 'video', 'public', '待机', '白毛女', 1, '疯批美人标准呼吸眼神温柔', '视频成品/疯批美人标准呼吸眼神温柔.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (297, '白毛女-话术-017', 'video', 'public', '话术', '白毛女', 1, '疯批美人核心话术 引导关注', '视频成品/疯批美人核心话术 引导关注.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (298, '白毛女-日常-018', 'video', 'public', '日常', '白毛女', 1, '疯批美人深夜困倦', '视频成品/疯批美人深夜困倦.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (299, '白毛女-感谢-019', 'video', 'public', '感谢', '白毛女', 1, '疯批美人真诚表情双手抱拳感谢', '视频成品/疯批美人真诚表情双手抱拳感谢.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (300, '白毛女-出入场-022', 'video', 'public', '出入场', '白毛女', 1, '疯批美人离场', '视频成品/疯批美人离场.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (301, '白毛女-傲娇-029', 'video', 'public', '傲娇', '白毛女', 1, '疯批美人蔑视冷哼翻白眼', '视频成品/疯批美人蔑视冷哼翻白眼.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (302, '白毛女-出入场-023', 'video', 'public', '出入场', '白毛女', 1, '疯批美人进场', '视频成品/疯批美人进场.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (303, '白毛女-投喂-010', 'video', 'public', '投喂', '白毛女', 1, '疯狂奖励投喂', '视频成品/疯狂奖励投喂.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (304, '白毛女-傲娇-030', 'video', 'public', '傲娇', '白毛女', 1, '疯狂惩罚蔑视', '视频成品/疯狂惩罚蔑视.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (305, '白毛女-诱惑-012', 'video', 'public', '诱惑', '白毛女', 1, '疯狂束发散发转换', '视频成品/疯狂束发散发转换.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (306, '白毛女-出入场-024', 'video', 'public', '出入场', '白毛女', 1, '疯狂禁忌暗示（嘘）', '视频成品/疯狂禁忌暗示（嘘）.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (307, '白毛女-出入场-025', 'video', 'public', '出入场', '白毛女', 1, '疯狂镜头雾化哈气', '视频成品/疯狂镜头雾化哈气.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (308, '白毛女-拉扯-015', 'video', 'public', '拉扯', '白毛女', 1, '疯狂颈部与锁骨拉扯', '视频成品/疯狂颈部与锁骨拉扯.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (309, '白毛女-话术-018', 'video', 'public', '话术', '白毛女', 1, '真实出声录制 情感告白', '视频成品/真实出声录制 情感告白.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (310, '白毛女-话术-019', 'video', 'public', '话术', '白毛女', 1, '真是出声录制 引导关注', '视频成品/真是出声录制 引导关注.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (311, '白毛女-感谢-020', 'video', 'public', '感谢', '白毛女', 1, '真诚表情，双手抱拳感谢。        清冷御姐', '视频成品/真诚表情，双手抱拳感谢。        清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (312, '白毛女-感谢-021', 'video', 'public', '感谢', '白毛女', 1, '真诚表情，双手抱拳感谢。       古典优雅', '视频成品/真诚表情，双手抱拳感谢。       古典优雅.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (313, '白毛女-感谢-022', 'video', 'public', '感谢', '白毛女', 1, '真诚表情，双手抱拳感谢。       疯批美人', '视频成品/真诚表情，双手抱拳感谢。       疯批美人.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (314, '白毛女-感谢-023', 'video', 'public', '感谢', '白毛女', 1, '真诚表情，双手抱拳感谢羞涩甜妹', '视频成品/真诚表情，双手抱拳感谢羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (315, '白毛女-害羞-018', 'video', 'public', '害羞', '白毛女', 1, '眼神互动 (10段)：注视镜头、害羞躲闪、调皮', '视频成品/眼神互动 (10段)：注视镜头、害羞躲闪、调皮.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (316, '白毛女-日常-019', 'video', 'public', '日常', '白毛女', 1, '睡眼惺忪', '视频成品/睡眼惺忪.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (317, '白毛女-变脸-019', 'video', 'public', '变脸', '白毛女', 1, '神经质颤抖，嘴角突然抽搐一下盯着镜头', '视频成品/神经质颤抖，嘴角突然抽搐一下盯着镜头.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (318, '白毛女-出入场-026', 'video', 'public', '出入场', '白毛女', 1, '离场 起身回头挥手', '视频成品/离场 起身回头挥手.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (319, '白毛女-傲娇-031', 'video', 'public', '傲娇', '白毛女', 1, '职场冷艳 傲娇鼓腮子帮襒头', '视频成品/职场冷艳 傲娇鼓腮子帮襒头.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (320, '白毛女-待机-016', 'video', 'public', '待机', '白毛女', 1, '职场冷艳 审视俯视，嘴角微提，带有一种不屑感，均匀、缓慢、深呼吸', '视频成品/职场冷艳 审视俯视，嘴角微提，带有一种不屑感，均匀、缓慢、深呼吸.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (321, '白毛女-变脸-020', 'video', 'public', '变脸', '白毛女', 1, '职场冷艳1极度惊恐瞳孔地震、无声颤抖、眼眶红', '视频成品/职场冷艳1极度惊恐瞳孔地震、无声颤抖、眼眶红.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (322, '白毛女-专注-023', 'video', 'public', '专注', '白毛女', 1, '职场冷艳专注状态玩手机', '视频成品/职场冷艳专注状态玩手机.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (323, '白毛女-专注-024', 'video', 'public', '专注', '白毛女', 1, '职场冷艳专注状态看书', '视频成品/职场冷艳专注状态看书.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (324, '白毛女-侧颜-007', 'video', 'public', '侧颜', '白毛女', 1, '职场冷艳侧颜45度', '视频成品/职场冷艳侧颜45度.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (325, '白毛女-变脸-021', 'video', 'public', '变脸', '白毛女', 1, '职场冷艳情绪崩坏 冷脸转笑', '视频成品/职场冷艳情绪崩坏 冷脸转笑.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (326, '白毛女-变脸-022', 'video', 'public', '变脸', '白毛女', 1, '职场冷艳情绪崩坏笑转冷脸', '视频成品/职场冷艳情绪崩坏笑转冷脸.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (327, '白毛女-变脸-023', 'video', 'public', '变脸', '白毛女', 1, '职场冷艳惊喜瞬间捂嘴蹦跳眼神放光', '视频成品/职场冷艳惊喜瞬间捂嘴蹦跳眼神放光.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (328, '白毛女-变脸-024', 'video', 'public', '变脸', '白毛女', 1, '职场冷艳极度惊恐 瞳孔地震、无声颤抖、眼眶红', '视频成品/职场冷艳极度惊恐 瞳孔地震、无声颤抖、眼眶红.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (329, '白毛女-待机-017', 'video', 'public', '待机', '白毛女', 1, '职场冷艳标准呼吸眼神清冷', '视频成品/职场冷艳标准呼吸眼神清冷.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (330, '白毛女-待机-018', 'video', 'public', '待机', '白毛女', 1, '职场冷艳标准呼吸眼神温柔', '视频成品/职场冷艳标准呼吸眼神温柔.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (331, '白毛女-话术-020', 'video', 'public', '话术', '白毛女', 1, '职场冷艳核心话术 引导关注', '视频成品/职场冷艳核心话术 引导关注.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (332, '白毛女-话术-021', 'video', 'public', '话术', '白毛女', 1, '职场冷艳核心话术 情感告白', '视频成品/职场冷艳核心话术 情感告白.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (333, '白毛女-话术-022', 'video', 'public', '话术', '白毛女', 1, '职场冷艳核心话术引导打赏', '视频成品/职场冷艳核心话术引导打赏.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (334, '白毛女-拥抱-004', 'video', 'public', '拥抱', '白毛女', 1, '职场冷艳模拟拥抱霸道环抱定格', '视频成品/职场冷艳模拟拥抱霸道环抱定格.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (335, '白毛女-日常-020', 'video', 'public', '日常', '白毛女', 1, '职场冷艳深夜困倦', '视频成品/职场冷艳深夜困倦.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (336, '白毛女-出入场-027', 'video', 'public', '出入场', '白毛女', 1, '职场冷艳离场', '视频成品/职场冷艳离场.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (337, '白毛女-傲娇-032', 'video', 'public', '傲娇', '白毛女', 1, '职场冷艳蔑视翻白眼', '视频成品/职场冷艳蔑视翻白眼.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (338, '白毛女-出入场-028', 'video', 'public', '出入场', '白毛女', 1, '职场冷艳进场', '视频成品/职场冷艳进场.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (339, '白毛女-害羞-019', 'video', 'public', '害羞', '白毛女', 1, '职场冷艳遮挡偷看单手遮眼', '视频成品/职场冷艳遮挡偷看单手遮眼.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (340, '白毛女-侧颜-008', 'video', 'public', '侧颜', '白毛女', 1, '背身回头', '视频成品/背身回头.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (341, '白毛女-傲娇-033', 'video', 'public', '傲娇', '白毛女', 1, '蔑视 冷哼翻白眼', '视频成品/蔑视 冷哼翻白眼.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (342, '白毛女-傲娇-034', 'video', 'public', '傲娇', '白毛女', 1, '被激怒时快速跺脚', '视频成品/被激怒时快速跺脚.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (343, '白毛女-开心-038', 'video', 'public', '开心', '白毛女', 1, '调皮眨眼Wink', '视频成品/调皮眨眼Wink.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (344, '白毛女-待机-019', 'video', 'public', '待机', '白毛女', 1, '轻快的呼吸', '视频成品/轻快的呼吸.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (345, '白毛女-出入场-029', 'video', 'public', '出入场', '白毛女', 1, '轻抿咖啡后，指尖轻敲桌面有节奏的职业性点头', '视频成品/轻抿咖啡后，指尖轻敲桌面有节奏的职业性点头.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (346, '白毛女-诱惑-013', 'video', 'public', '诱惑', '白毛女', 1, '轻抿嘴唇', '视频成品/轻抿嘴唇.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (347, '白毛女-出入场-030', 'video', 'public', '出入场', '白毛女', 1, '进场 走近坐下', '视频成品/进场 走近坐下.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (348, '白毛女-害羞-020', 'video', 'public', '害羞', '白毛女', 1, '遮挡偷看 单手遮眼', '视频成品/遮挡偷看 单手遮眼.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (349, '白毛女-害羞-021', 'video', 'public', '害羞', '白毛女', 1, '遮挡偷看 双手捂脸从指缝看', '视频成品/遮挡偷看 双手捂脸从指缝看.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (350, '白毛女-拉扯-016', 'video', 'public', '拉扯', '白毛女', 1, '遮挡抢夺伸手试图捂住镜头，或者做出从镜头前拿走“手机”动作', '视频成品/遮挡抢夺伸手试图捂住镜头，或者做出从镜头前拿走“手机”动作.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (351, '白毛女-拉扯-017', 'video', 'public', '拉扯', '白毛女', 1, '遮挡抢夺：伸手试图捂住镜头，或者做出从镜头前拿走“手机”动作', '视频成品/遮挡抢夺：伸手试图捂住镜头，或者做出从镜头前拿走“手机”动作.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (352, '白毛女-话术-023', 'video', 'public', '话术', '白毛女', 1, '那个如果你不嫌弃，明…明天还能来吗？', '视频成品/那个如果你不嫌弃，明…明天还能来吗？.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (353, '白毛女-话术-024', 'video', 'public', '话术', '白毛女', 1, '那，那个对不起我，我不是故意不说话的', '视频成品/那，那个对不起我，我不是故意不说话的.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (354, '白毛女-专注-025', 'video', 'public', '专注', '白毛女', 1, '邻家小妹专注状态低头玩手机', '视频成品/邻家小妹专注状态低头玩手机.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (355, '白毛女-专注-026', 'video', 'public', '专注', '白毛女', 1, '邻家小妹专注状态低头看书', '视频成品/邻家小妹专注状态低头看书.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (356, '白毛女-侧颜-009', 'video', 'public', '侧颜', '白毛女', 1, '邻家小妹侧颜45度', '视频成品/邻家小妹侧颜45度.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (357, '白毛女-待机-020', 'video', 'public', '待机', '白毛女', 1, '邻家小妹标准呼吸眼神清冷', '视频成品/邻家小妹标准呼吸眼神清冷.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (358, '白毛女-待机-021', 'video', 'public', '待机', '白毛女', 1, '邻家小妹标准呼吸眼神温柔', '视频成品/邻家小妹标准呼吸眼神温柔.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (359, '白毛女-日常-021', 'video', 'public', '日常', '白毛女', 1, '邻家小妹深夜困倦', '视频成品/norm/邻家小妹深夜困倦.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (360, '白毛女-出入场-031', 'video', 'public', '出入场', '白毛女', 1, '邻家小妹离场', '视频成品/norm/邻家小妹离场.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (361, '白毛女-出入场-032', 'video', 'public', '出入场', '白毛女', 1, '邻家小妹进场', '视频成品/norm/邻家小妹进场.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (362, '白毛女-变脸-025', 'video', 'public', '变脸', '白毛女', 1, '邻家纯欲1极度惊恐瞳孔地震、无声颤抖、眼眶红', '视频成品/norm/邻家纯欲1极度惊恐瞳孔地震、无声颤抖、眼眶红.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (363, '白毛女-傲娇-035', 'video', 'public', '傲娇', '白毛女', 1, '邻家纯欲傲娇鼓腮帮子襒头', '视频成品/norm/邻家纯欲傲娇鼓腮帮子襒头.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (364, '白毛女-变脸-026', 'video', 'public', '变脸', '白毛女', 1, '邻家纯欲冷脸转笑', '视频成品/norm/邻家纯欲冷脸转笑.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (365, '白毛女-变脸-027', 'video', 'public', '变脸', '白毛女', 1, '邻家纯欲惊喜瞬间捂嘴蹦跳', '视频成品/norm/邻家纯欲惊喜瞬间捂嘴蹦跳.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (366, '白毛女-变脸-028', 'video', 'public', '变脸', '白毛女', 1, '邻家纯欲惊喜瞬间捂嘴蹦跳眼神放光', '视频成品/norm/邻家纯欲惊喜瞬间捂嘴蹦跳眼神放光.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (367, '白毛女-变脸-029', 'video', 'public', '变脸', '白毛女', 1, '邻家纯欲极度惊恐瞳孔地震、无声颤抖、眼眶红', '视频成品/邻家纯欲极度惊恐瞳孔地震、无声颤抖、眼眶红.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (368, '白毛女-话术-025', 'video', 'public', '话术', '白毛女', 1, '邻家纯欲核心话术引导打赏', '视频成品/邻家纯欲核心话术引导打赏.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (369, '白毛女-话术-026', 'video', 'public', '话术', '白毛女', 1, '邻家纯欲核心话术情感告白', '视频成品/norm/邻家纯欲核心话术情感告白.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (370, '白毛女-安慰-007', 'video', 'public', '安慰', '白毛女', 1, '邻家纯欲物理安慰手伸向镜头抹泪', '视频成品/norm/邻家纯欲物理安慰手伸向镜头抹泪.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (371, '白毛女-安慰-008', 'video', 'public', '安慰', '白毛女', 1, '邻家纯欲物理安慰手掌贴镜', '视频成品/norm/邻家纯欲物理安慰手掌贴镜.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (372, '白毛女-投喂-011', 'video', 'public', '投喂', '白毛女', 1, '邻家纯欲物理投喂勺子', '视频成品/norm/邻家纯欲物理投喂勺子.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (373, '白毛女-投喂-012', 'video', 'public', '投喂', '白毛女', 1, '邻家纯欲物理投喂零食', '视频成品/norm/邻家纯欲物理投喂零食.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (374, '白毛女-拉扯-018', 'video', 'public', '拉扯', '白毛女', 1, '邻家纯欲物理拉扯衣角', '视频成品/norm/邻家纯欲物理拉扯衣角.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (375, '白毛女-变脸-030', 'video', 'public', '变脸', '白毛女', 1, '邻家纯欲笑转冷脸', '视频成品/norm/邻家纯欲笑转冷脸.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (376, '白毛女-傲娇-036', 'video', 'public', '傲娇', '白毛女', 1, '邻家纯欲蔑视翻白眼', '视频成品/norm/邻家纯欲蔑视翻白眼.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (377, '白毛女-话术-027', 'video', 'public', '话术', '白毛女', 1, '除了我，你还在看谁把那个女人的名字告诉我', '视频成品/除了我，你还在看谁把那个女人的名字告诉我.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (378, '白毛女-感谢-024', 'video', 'public', '感谢', '白毛女', 1, '鞠躬谢幕古典优雅', '视频成品/鞠躬谢幕古典优雅.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (379, '白毛女-感谢-025', 'video', 'public', '感谢', '白毛女', 1, '鞠躬谢幕清冷御姐', '视频成品/norm/鞠躬谢幕清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (380, '白毛女-感谢-026', 'video', 'public', '感谢', '白毛女', 1, '鞠躬谢幕疯批美人', '视频成品/norm/鞠躬谢幕疯批美人.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (381, '白毛女-感谢-027', 'video', 'public', '感谢', '白毛女', 1, '鞠躬谢幕羞涩甜妹', '视频成品/norm/鞠躬谢幕羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (382, '白毛女-飞吻-002', 'video', 'public', '飞吻', '白毛女', 1, '飞吻', '视频成品/norm/飞吻.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (383, '白毛女-飞吻-003', 'video', 'public', '飞吻', '白毛女', 1, '飞吻再见清冷御姐', '视频成品/norm/飞吻再见清冷御姐.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (384, '白毛女-飞吻-004', 'video', 'public', '飞吻', '白毛女', 1, '飞吻再见疯批美人', '视频成品/norm/飞吻再见疯批美人.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (385, '白毛女-飞吻-005', 'video', 'public', '飞吻', '白毛女', 1, '飞吻再见羞涩甜妹', '视频成品/norm/飞吻再见羞涩甜妹.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (386, '白毛女-撩发-013', 'video', 'public', '撩发', '白毛女', 1, '高冷型，缓慢戴摘眼镜需要剪辑', '视频成品/norm/高冷型，缓慢戴摘眼镜需要剪辑.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (387, '白毛女-撩发-014', 'video', 'public', '撩发', '白毛女', 1, '高冷型：优雅整理袖口', '视频成品/norm/高冷型：优雅整理袖口.mp4', 0, '', 1, '2026-07-27 18:12:31');
INSERT INTO `lp_media_asset` VALUES (388, '白毛女-傲娇-037', 'video', 'public', '傲娇', '白毛女', 1, '鼓起脸颊跺脚', '视频成品/norm/鼓起脸颊跺脚.mp4', 0, '', 1, '2026-07-27 18:12:31');

-- ----------------------------
-- Table structure for lp_merchant_certification
-- ----------------------------
DROP TABLE IF EXISTS `lp_merchant_certification`;
CREATE TABLE `lp_merchant_certification`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '??',
  `user_id` bigint(20) UNSIGNED NOT NULL COMMENT '??ID',
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '????',
  `real_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '真实姓名',
  `id_card_no` varchar(18) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '身份证号',
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '手机号',
  `shop_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '店铺名称',
  `shop_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '经营类目',
  `shop_description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '店铺简介',
  `id_card_front` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '?????',
  `id_card_back` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '?????',
  `business_license` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '营业执照',
  `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '????:0??? 1??? 2???',
  `reject_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '????',
  `created_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '????',
  `updated_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '????',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_user_id`(`user_id`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '??????' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_merchant_certification
-- ----------------------------
INSERT INTO `lp_merchant_certification` VALUES (2, 2, '1031177424@qq.com', '', '', '', '', '', '', 'http://127.0.0.1:8000/storage/certification/20260725/145112282aad7d13417c22b0e5130fc969a1086f4b7c407.png', 'http://127.0.0.1:8000/storage/certification/20260725/1615454c8cc10a37a2e0df72d7121259b7cc8d6920874b1.png', '', 1, '', '2026-07-25 14:09:43', '2026-07-25 15:08:49');

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
  `created_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_gateway_payload_hash`(`gateway`, `payload_hash`) USING BTREE,
  INDEX `idx_order_no`(`order_no`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '支付回调日志' ROW_FORMAT = Dynamic;

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
  `created_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_persona_code`(`code`) USING BTREE,
  INDEX `idx_user_id`(`user_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 11 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '数字人人设' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_persona
-- ----------------------------
INSERT INTO `lp_persona` VALUES (1, 11, 'persona_demo', '夜聊陪伴', '温柔,陪伴,夜间', '{\"age\": \"1\", \"eye\": \"0\", \"hair\": \"2\", \"type\": \"1\", \"hobby\": \"1,5\", \"profession\": \"1\", \"personality\": \"3\"}', 'https://cdn.example.com/persona/night.jpg', 2, '2026-05-17 09:41:46', '2026-07-04 17:39:09');
INSERT INTO `lp_persona` VALUES (2, 11, 'persona_night_radio', '夜聊陪伴', '温柔,陪伴,夜间', '{\"age\": \"1\", \"eye\": \"0\", \"hair\": \"2\", \"type\": \"1\", \"hobby\": \"1,5\", \"profession\": \"1\", \"personality\": \"3\"}', 'https://picsum.photos/seed/persona-night/320/320', 2, '2026-05-18 09:49:16', '2026-07-04 17:39:07');
INSERT INTO `lp_persona` VALUES (3, 11, 'persona_light_music', '轻音陪伴', '轻音乐,放松,陪伴', '{\"age\": \"1\", \"eye\": \"0\", \"hair\": \"2\", \"type\": \"1\", \"hobby\": \"1,5\", \"profession\": \"1\", \"personality\": \"3\"}', 'https://picsum.photos/seed/persona-music/320/320', 2, '2026-05-18 09:49:16', '2026-07-04 17:39:06');
INSERT INTO `lp_persona` VALUES (4, 11, 'persona_focus_study', '专注搭子', '学习,专注,清晨', '{\"age\": \"1\", \"eye\": \"0\", \"hair\": \"2\", \"type\": \"1\", \"hobby\": \"1,5\", \"profession\": \"1\", \"personality\": \"3\"}', 'https://picsum.photos/seed/persona-study/320/320', 2, '2026-05-18 09:49:16', '2026-07-28 16:51:44');
INSERT INTO `lp_persona` VALUES (5, 8, 'P202607020129318550', 'Test AI Girl', '', '{\"age\": \"1\", \"eye\": \"0\", \"hair\": \"2\", \"type\": \"1\", \"hobby\": \"1,5\", \"profession\": \"1\", \"personality\": \"3\"}', 'https://example.com/avatar.jpg', 1, '2026-07-02 01:29:31', '2026-07-02 01:29:31');
INSERT INTO `lp_persona` VALUES (6, 9, 'P202607020158464287', 'eva', '', '{\"age\": \"1\", \"eye\": \"3\", \"hip\": \"3\", \"body\": \"1\", \"hair\": \"1\", \"race\": \"2\", \"type\": \"1\", \"hobby\": \"6,7\", \"breast\": \"5\", \"clothing\": \"5\", \"relation\": \"6\", \"hairstyle\": \"7\", \"profession\": \"26\", \"personality\": \"1\"}', '/storage/ai/20260702/aiimageedit-1774431bc69c712377d1cb9d5b9d78f263e19afc039.png', 1, '2026-07-02 01:58:46', '2026-07-04 17:34:42');
INSERT INTO `lp_persona` VALUES (7, 11, 'P202607041643171411', 'eeemmmm', '', '{\"age\": \"1\", \"eye\": \"2\", \"hip\": \"3\", \"body\": \"2\", \"hair\": \"4\", \"race\": \"1\", \"type\": \"1\", \"hobby\": \"10\", \"breast\": \"2\", \"clothing\": \"19\", \"relation\": \"2\", \"hairstyle\": \"3\", \"profession\": \"1\", \"personality\": \"1\"}', 'http://127.0.0.1:8000/storage/ai/20260704/idlefish-msg-177e42ed9da5222e1b3dabbdf033acdbc19495c749.jpg', 2, '2026-07-04 16:43:17', '2026-07-04 17:34:42');
INSERT INTO `lp_persona` VALUES (8, 2, 'P202607062055323098', 'ala', '', '{\"age\": \"2\", \"eye\": \"2\", \"hip\": \"3\", \"body\": \"4\", \"hair\": \"5\", \"race\": \"1\", \"type\": \"1\", \"hobby\": \"1,2,3\", \"breast\": \"5\", \"clothing\": \"66\", \"relation\": \"5\", \"hairstyle\": \"1\", \"profession\": \"1\", \"personality\": \"5\"}', 'http://127.0.0.1:8000/storage/ai/20260706/abg3eee9a61cd0efe5b435b3cb05c93f02be77fd3ad.png', 2, '2026-07-06 20:55:32', '2026-07-06 20:55:32');
INSERT INTO `lp_persona` VALUES (9, 2, 'P202607062104056203', 'ala', '', '{\"age\": \"3\", \"eye\": \"3\", \"hip\": \"4\", \"body\": \"3\", \"hair\": \"5\", \"race\": \"2\", \"type\": \"1\", \"hobby\": \"6\", \"breast\": \"3\", \"clothing\": \"57\", \"relation\": \"8\", \"hairstyle\": \"3\", \"profession\": \"38\", \"personality\": \"2\"}', 'http://127.0.0.1:8000/storage/ai/20260706/abg3eee9a61cd0efe5b435b3cb05c93f02be77fd3ad.png', 2, '2026-07-06 21:04:05', '2026-07-06 21:04:05');
INSERT INTO `lp_persona` VALUES (10, 2, 'P202607281443368250', '111', '', '{\"age\": \"1\", \"eye\": \"1\", \"hip\": \"1\", \"body\": \"1\", \"hair\": \"4\", \"race\": \"1\", \"type\": \"1\", \"hobby\": \"6\", \"breast\": \"1\", \"clothing\": \"41\", \"relation\": \"12\", \"hairstyle\": \"6\", \"profession\": \"39\", \"personality\": \"11\"}', 'http://127.0.0.1:8000/storage/ai/20260728/170212328aeddda760d71ac05e15158904817cc316339ab.png', 2, '2026-07-28 14:43:36', '2026-07-28 14:43:36');

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
  `created_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_playlist_template_code`(`template_code`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 10 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '播单模板' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_playlist_template
-- ----------------------------
INSERT INTO `lp_playlist_template` VALUES (1, 'playlist_demo', '默认播单', 'public', 1, '2026-05-17 09:41:46');
INSERT INTO `lp_playlist_template` VALUES (2, 'playlist_public_demo_room_1', '深夜情感电台2播单', 'public', 1, '2026-05-18 09:56:39');
INSERT INTO `lp_playlist_template` VALUES (3, 'playlist_public_demo_room_2', '午后轻音乐直播间3播单', 'public', 1, '2026-05-18 09:56:39');
INSERT INTO `lp_playlist_template` VALUES (4, 'playlist_public_demo_room_3', '清晨自习直播间4播单', 'public', 1, '2026-05-18 09:56:39');
INSERT INTO `lp_playlist_template` VALUES (5, 'room_playlist_4', 'E2E联调测试房间播单', 'public', 1, '2026-05-18 17:29:54');
INSERT INTO `lp_playlist_template` VALUES (6, 'room_playlist_8', '111播单', 'public', 1, '2026-07-28 16:51:44');
INSERT INTO `lp_playlist_template` VALUES (7, 'room_playlist_5', 'ai直播5播单', 'public', 1, '2026-07-28 16:52:11');
INSERT INTO `lp_playlist_template` VALUES (8, 'room_playlist_7', 'ai直播7播单', 'public', 1, '2026-07-30 16:56:15');
INSERT INTO `lp_playlist_template` VALUES (9, 'room_playlist_6', 'ai直播6播单', 'public', 1, '2026-07-30 16:56:40');

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
  INDEX `idx_template_seq`(`template_id`, `seq`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 359 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '播单模板项' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_playlist_template_item
-- ----------------------------
INSERT INTO `lp_playlist_template_item` VALUES (1, 1, 1, 1, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (10, 2, 2, 1, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (11, 3, 3, 1, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (13, 5, 5, 1, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (16, 4, 4, 1, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (37, 6, 388, 1, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (38, 6, 384, 2, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (39, 6, 386, 3, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (40, 6, 387, 4, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (41, 6, 385, 5, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (42, 6, 383, 6, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (43, 6, 382, 7, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (44, 6, 381, 8, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (45, 6, 380, 9, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (46, 6, 379, 10, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (307, 8, 388, 1, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (308, 8, 387, 2, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (309, 8, 386, 3, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (310, 8, 385, 4, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (311, 8, 384, 5, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (312, 8, 383, 6, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (313, 8, 382, 7, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (314, 8, 381, 8, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (315, 8, 380, 9, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (316, 8, 379, 10, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (317, 8, 376, 11, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (318, 8, 375, 12, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (319, 8, 374, 13, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (320, 8, 373, 14, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (321, 8, 372, 15, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (322, 8, 371, 16, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (323, 8, 370, 17, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (324, 8, 369, 18, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (325, 8, 366, 19, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (326, 8, 365, 20, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (327, 8, 364, 21, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (328, 8, 363, 22, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (329, 8, 362, 23, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (330, 8, 361, 24, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (331, 8, 360, 25, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (332, 8, 359, 26, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (347, 7, 388, 1, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (348, 7, 387, 2, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (349, 7, 386, 3, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (350, 7, 385, 4, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (351, 7, 384, 5, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (352, 7, 383, 6, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (353, 7, 382, 7, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (354, 7, 381, 8, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (355, 7, 380, 9, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (356, 7, 379, 10, 1, 1, 0);
INSERT INTO `lp_playlist_template_item` VALUES (358, 9, 388, 1, 1, 1, 0);

-- ----------------------------
-- Table structure for lp_recharge_channel
-- ----------------------------
DROP TABLE IF EXISTS `lp_recharge_channel`;
CREATE TABLE `lp_recharge_channel`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'usdt_trc20',
  `qr_code_url` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
  `diamond_rate` decimal(18, 2) NOT NULL DEFAULT 100.00,
  `min_amount` decimal(18, 2) NOT NULL DEFAULT 10.00,
  `sort` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP(0),
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_status_sort`(`status`, `sort`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci COMMENT = '充值渠道配置' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_recharge_channel
-- ----------------------------
INSERT INTO `lp_recharge_channel` VALUES (1, 'USDT-TRC20', 'usdt_trc20', '/storage/recharge_qr/20260807/7ad64959b834bf5bf89da3c2211a4c7b25930c67.jpg', 'TXNfC3v8bqPzKnDPKxEzMxEMMzRzUvSRKx', 100.00, 10.00, 1, 1, '2026-08-07 12:00:45', '2026-08-07 12:42:38');
INSERT INTO `lp_recharge_channel` VALUES (2, '555', 'other', '/storage/recharge_qr/20260807/qr_4d3991ba4a42ffa7e34553a283420232ed7370f7.jpg', '123', 100.00, 10.00, 0, 1, '2026-08-07 12:27:20', '2026-08-07 13:17:06');

-- ----------------------------
-- Table structure for lp_recharge_order
-- ----------------------------
DROP TABLE IF EXISTS `lp_recharge_order`;
CREATE TABLE `lp_recharge_order`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `order_no` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '充值订单号',
  `user_id` bigint(20) UNSIGNED NOT NULL COMMENT '用户ID',
  `pay_channel` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '支付渠道:usdt_trc20',
  `channel_id` bigint(20) UNSIGNED NULL DEFAULT NULL,
  `chain_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TRC20' COMMENT '链类型',
  `pay_amount` decimal(18, 8) NOT NULL COMMENT '支付金额',
  `diamond_amount` decimal(18, 2) NOT NULL COMMENT '到账钻石数',
  `proof_image` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `admin_remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '状态:0待支付 1已支付 2已过期 3关闭',
  `expire_at` datetime(0) NULL DEFAULT NULL COMMENT '过期时间',
  `paid_at` datetime(0) NULL DEFAULT NULL COMMENT '支付时间',
  `reviewed_at` datetime(0) NULL DEFAULT NULL,
  `created_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_recharge_order_no`(`order_no`) USING BTREE,
  INDEX `idx_user_status`(`user_id`, `status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 10 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '充值订单' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_recharge_order
-- ----------------------------
INSERT INTO `lp_recharge_order` VALUES (1, 'RC202608071231168385', 2, 'other', 2, 'TRC20', 100.00000000, 10000.00, '', '', 1, NULL, '2026-08-07 12:31:24', '2026-08-07 12:31:24', '2026-08-07 12:31:16', '2026-08-07 12:31:24');
INSERT INTO `lp_recharge_order` VALUES (2, 'RC202608071231502707', 2, 'other', 2, 'TRC20', 100.00000000, 10000.00, 'http://127.0.0.1:8001/storage/recharge/20260807/proof_80997c616a2a42d27990abaf961040e27f0fdd17.jpg', '', 1, NULL, '2026-08-07 12:33:05', '2026-08-07 12:33:05', '2026-08-07 12:31:50', '2026-08-07 13:00:55');
INSERT INTO `lp_recharge_order` VALUES (3, 'RC202608071232572888', 2, 'other', 2, 'TRC20', 100.00000000, 10000.00, 'http://127.0.0.1:8001/storage/recharge/20260807/proof_80997c616a2a42d27990abaf961040e27f0fdd17.jpg', '', 1, NULL, '2026-08-07 12:53:13', '2026-08-07 12:53:13', '2026-08-07 12:32:57', '2026-08-07 13:00:55');
INSERT INTO `lp_recharge_order` VALUES (4, 'RC202608071300130126', 2, 'other', 2, 'TRC20', 100.00000000, 10000.00, '', '', 1, NULL, '2026-08-07 13:00:39', '2026-08-07 13:00:39', '2026-08-07 13:00:13', '2026-08-07 13:00:39');
INSERT INTO `lp_recharge_order` VALUES (5, 'RC202608071302499401', 2, 'other', 2, 'TRC20', 100.00000000, 10000.00, '', '1', 2, NULL, NULL, '2026-08-07 13:02:58', '2026-08-07 13:02:49', '2026-08-07 13:02:58');
INSERT INTO `lp_recharge_order` VALUES (6, 'RC202608071303332546', 2, 'other', 2, 'TRC20', 100.00000000, 10000.00, '', '1', 1, NULL, '2026-08-07 13:03:38', '2026-08-07 13:03:38', '2026-08-07 13:03:33', '2026-08-07 13:03:38');
INSERT INTO `lp_recharge_order` VALUES (7, 'RC-TEST-001', 2, 'usdt_trc20', 2, 'TRC20', 10.00000000, 1000.00, '', 'test', 1, NULL, '2026-08-07 13:08:29', '2026-08-07 13:08:29', '2026-08-07 13:05:15', '2026-08-07 13:08:29');
INSERT INTO `lp_recharge_order` VALUES (8, 'RC202608071311232950', 2, 'usdt_trc20', 1, 'TRC20', 100.00000000, 10000.00, '', '', 1, NULL, '2026-08-07 13:11:27', '2026-08-07 13:11:27', '2026-08-07 13:11:23', '2026-08-07 13:11:27');
INSERT INTO `lp_recharge_order` VALUES (9, 'RC202608071422509675', 1, 'other', 2, 'TRC20', 98.00000000, 9800.00, '', '', 1, NULL, '2026-08-07 14:22:54', '2026-08-07 14:22:54', '2026-08-07 14:22:50', '2026-08-07 14:22:54');

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
  `created_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_persona_id`(`persona_id`) USING BTREE,
  INDEX `idx_room_id`(`room_id`) USING BTREE,
  INDEX `idx_live_date`(`live_date`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '历史切片' ROW_FORMAT = Dynamic;

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
  `created_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_room_no`(`room_no`) USING BTREE,
  INDEX `idx_persona_id`(`persona_id`) USING BTREE,
  INDEX `idx_status_sort`(`status`, `sort`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 9 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '直播房间' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_room
-- ----------------------------
INSERT INTO `lp_room` VALUES (1, 'R1001', '深夜情感电台2', '陪你聊天到天亮', 2, 'live', 1, 'https://picsum.photos/seed/live-room-1/720/1280', 120, '2026-05-17 09:41:46', '2026-05-19 17:30:04');
INSERT INTO `lp_room` VALUES (2, 'R1002', '午后轻音乐直播间3', '循环播放舒缓歌单和聊天互动', 3, 'live', 1, 'https://picsum.photos/seed/live-room-2/720/1280', 110, '2026-05-18 09:49:16', '2026-05-19 17:30:09');
INSERT INTO `lp_room` VALUES (3, 'R1003', '清晨自习直播间4', '适合切后台挂机的专注陪伴流', 7, 'live', 1, 'https://picsum.photos/seed/live-room-3/720/1280', 100, '2026-05-18 09:49:16', '2026-07-04 17:34:42');
INSERT INTO `lp_room` VALUES (4, 'E2E1001', 'E2E联调测试房间', '后台上传素材后的真实联调房间', 1, 'live', 2, '', 50, '2026-05-18 17:29:54', '2026-07-04 16:48:23');
INSERT INTO `lp_room` VALUES (5, 'R202607062055320610', 'ai直播5', '5', 8, 'live', 1, 'http://127.0.0.1:8000/storage/ai/20260706/abg3eee9a61cd0efe5b435b3cb05c93f02be77fd3ad.png', 0, '2026-07-06 20:55:32', '2026-07-30 17:35:47');
INSERT INTO `lp_room` VALUES (6, 'R202607062104058298', 'ai直播6', '6', 9, 'live', 1, 'http://127.0.0.1:8000/storage/ai/20260706/abg3eee9a61cd0efe5b435b3cb05c93f02be77fd3ad.png', 0, '2026-07-06 21:04:05', '2026-07-30 17:35:42');
INSERT INTO `lp_room` VALUES (7, 'R202607281443360064', 'ai直播7', '7', 10, 'live', 1, 'http://127.0.0.1:8000/storage/ai/20260728/170212328aeddda760d71ac05e15158904817cc316339ab.png', 0, '2026-07-28 14:43:36', '2026-07-30 17:35:35');
INSERT INTO `lp_room` VALUES (8, '111', 'ai直播8', '8', 4, 'live', 1, '/storage/default/20260728/170212328aeddda760d71ac05e15158904817cc316339ab.png', 0, '2026-07-28 16:51:44', '2026-07-30 17:35:30');

-- ----------------------------
-- Table structure for lp_room_binding
-- ----------------------------
DROP TABLE IF EXISTS `lp_room_binding`;
CREATE TABLE `lp_room_binding`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `room_id` bigint(20) UNSIGNED NOT NULL COMMENT '房间ID',
  `room_group_id` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '房间分组ID',
  `persona` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '绑定人设',
  `stream_template_id` bigint(20) UNSIGNED NOT NULL COMMENT '流模板ID',
  `playlist_template_id` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '公共播单模板ID（关键词模式下可为NULL）',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_room_id`(`room_id`) USING BTREE,
  INDEX `idx_room_group_id`(`room_group_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 9 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '房间绑定配置' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_room_binding
-- ----------------------------
INSERT INTO `lp_room_binding` VALUES (1, 1, 1, '', 1, 2);
INSERT INTO `lp_room_binding` VALUES (2, 2, 1, '', 1, 3);
INSERT INTO `lp_room_binding` VALUES (3, 3, 1, '', 1, 4);
INSERT INTO `lp_room_binding` VALUES (4, 4, NULL, '', 1, 5);
INSERT INTO `lp_room_binding` VALUES (5, 8, NULL, '专注搭子', 1, 9);
INSERT INTO `lp_room_binding` VALUES (6, 5, NULL, 'ala', 1, 7);
INSERT INTO `lp_room_binding` VALUES (7, 7, NULL, '111', 1, 8);
INSERT INTO `lp_room_binding` VALUES (8, 6, NULL, 'ala', 1, 9);

-- ----------------------------
-- Table structure for lp_room_event_log
-- ----------------------------
DROP TABLE IF EXISTS `lp_room_event_log`;
CREATE TABLE `lp_room_event_log`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `room_id` bigint(20) UNSIGNED NOT NULL COMMENT '房间ID',
  `event_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '事件类型',
  `payload_json` json NULL COMMENT '事件数据',
  `created_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_room_created`(`room_id`, `created_at`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '房间事件日志' ROW_FORMAT = Dynamic;

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
  UNIQUE INDEX `uk_source_group_code`(`source_group_code`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '共享源房间分组' ROW_FORMAT = Dynamic;

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
  `minute_at` datetime(0) NOT NULL COMMENT '分钟时间',
  `online_count` int(11) NOT NULL DEFAULT 0 COMMENT '在线人数',
  `like_count` int(11) NOT NULL DEFAULT 0 COMMENT '点赞数',
  `gift_amount` decimal(18, 2) NOT NULL DEFAULT 0.00 COMMENT '礼物金额',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_room_minute`(`room_id`, `minute_at`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 5540 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '房间分钟聚合数据' ROW_FORMAT = Dynamic;

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
INSERT INTO `lp_room_online_minute` VALUES (1238, 1, '2026-07-06 15:53:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1239, 1, '2026-07-06 15:54:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1240, 1, '2026-07-06 15:55:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1241, 1, '2026-07-06 15:56:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1242, 1, '2026-07-06 15:57:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1243, 1, '2026-07-06 15:58:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1244, 1, '2026-07-06 15:59:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1245, 1, '2026-07-06 16:00:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1246, 1, '2026-07-06 16:01:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1247, 1, '2026-07-06 16:02:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1248, 1, '2026-07-06 16:03:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1249, 1, '2026-07-06 16:04:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1250, 1, '2026-07-06 16:05:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1251, 1, '2026-07-06 16:06:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1252, 1, '2026-07-06 16:07:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1253, 1, '2026-07-06 16:08:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1254, 1, '2026-07-06 16:09:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1255, 1, '2026-07-06 16:10:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1256, 1, '2026-07-06 16:11:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1257, 1, '2026-07-06 16:12:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1258, 1, '2026-07-06 16:13:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1259, 1, '2026-07-06 16:14:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1260, 1, '2026-07-06 16:15:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1261, 1, '2026-07-06 16:16:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1262, 1, '2026-07-06 16:17:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1263, 1, '2026-07-06 16:18:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1264, 1, '2026-07-06 16:19:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1265, 1, '2026-07-06 16:20:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1266, 1, '2026-07-06 16:21:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1267, 1, '2026-07-06 16:22:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1268, 1, '2026-07-06 16:23:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1269, 1, '2026-07-06 16:24:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1270, 1, '2026-07-06 16:25:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1271, 1, '2026-07-06 16:26:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1272, 1, '2026-07-06 16:27:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1273, 1, '2026-07-06 16:28:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1274, 1, '2026-07-06 16:29:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1275, 1, '2026-07-06 16:30:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1276, 1, '2026-07-06 16:31:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1277, 1, '2026-07-06 16:32:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1278, 1, '2026-07-06 16:33:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1279, 1, '2026-07-06 16:34:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1280, 1, '2026-07-06 16:35:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1281, 1, '2026-07-06 16:36:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1282, 1, '2026-07-06 16:37:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1283, 1, '2026-07-06 16:38:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1284, 1, '2026-07-06 16:39:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1285, 1, '2026-07-06 16:40:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1286, 1, '2026-07-06 16:41:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1287, 1, '2026-07-06 16:42:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1288, 1, '2026-07-06 16:43:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1289, 1, '2026-07-06 16:44:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1290, 1, '2026-07-06 16:45:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1291, 1, '2026-07-06 16:46:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1292, 1, '2026-07-06 16:47:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1293, 1, '2026-07-06 16:48:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1294, 1, '2026-07-06 16:49:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1295, 1, '2026-07-06 16:50:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1296, 1, '2026-07-06 16:51:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1297, 1, '2026-07-06 16:52:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1298, 1, '2026-07-06 16:53:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1299, 1, '2026-07-06 16:54:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1300, 1, '2026-07-06 16:55:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1301, 1, '2026-07-28 15:15:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1302, 3, '2026-07-28 15:51:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1303, 3, '2026-07-28 15:52:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1304, 3, '2026-07-28 15:53:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1305, 3, '2026-07-28 15:54:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1306, 3, '2026-07-28 15:55:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1307, 3, '2026-07-28 15:56:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1308, 3, '2026-07-28 15:57:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1309, 3, '2026-07-28 15:58:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1310, 3, '2026-07-28 15:59:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1311, 3, '2026-07-28 16:00:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1312, 3, '2026-07-28 16:01:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1313, 3, '2026-07-28 16:02:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1314, 3, '2026-07-28 16:03:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1315, 3, '2026-07-28 16:04:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1316, 3, '2026-07-28 16:05:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1317, 3, '2026-07-28 16:06:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1318, 3, '2026-07-28 16:07:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1319, 1, '2026-07-28 16:37:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1320, 3, '2026-07-28 16:37:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1321, 1, '2026-07-28 16:38:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1322, 1, '2026-07-28 16:39:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1323, 1, '2026-07-28 16:40:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1324, 1, '2026-07-28 16:41:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1325, 1, '2026-07-28 16:42:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1326, 1, '2026-07-28 16:43:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1327, 1, '2026-07-28 16:44:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1328, 1, '2026-07-28 16:45:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1329, 1, '2026-07-28 16:46:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1330, 1, '2026-07-28 16:47:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1331, 1, '2026-07-28 16:48:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1332, 1, '2026-07-28 16:49:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1333, 1, '2026-07-28 16:50:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1334, 1, '2026-07-28 16:51:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1335, 1, '2026-07-28 16:52:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1336, 1, '2026-07-28 16:53:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1337, 8, '2026-07-28 16:53:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1338, 1, '2026-07-28 16:54:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1339, 8, '2026-07-28 16:54:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1340, 1, '2026-07-28 16:55:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1341, 8, '2026-07-28 16:55:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1342, 1, '2026-07-28 16:56:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1343, 1, '2026-07-28 16:57:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1344, 1, '2026-07-28 16:58:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1345, 1, '2026-07-28 16:59:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1346, 1, '2026-07-28 17:00:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1347, 2, '2026-07-28 17:00:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1348, 8, '2026-07-28 17:00:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1349, 1, '2026-07-28 17:01:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1350, 2, '2026-07-28 17:01:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1351, 8, '2026-07-28 17:01:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1352, 1, '2026-07-28 17:02:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1353, 3, '2026-07-28 17:02:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1354, 8, '2026-07-28 17:02:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1355, 1, '2026-07-28 17:03:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1356, 8, '2026-07-28 17:03:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1357, 1, '2026-07-28 17:04:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1358, 8, '2026-07-28 17:04:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1359, 1, '2026-07-28 17:05:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1360, 8, '2026-07-28 17:05:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1361, 1, '2026-07-28 17:06:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1362, 8, '2026-07-28 17:06:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1363, 1, '2026-07-28 17:07:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1364, 8, '2026-07-28 17:07:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1365, 1, '2026-07-28 17:08:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1366, 8, '2026-07-28 17:08:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1367, 1, '2026-07-28 17:09:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1368, 8, '2026-07-28 17:09:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1369, 1, '2026-07-28 17:10:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1370, 8, '2026-07-28 17:10:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1371, 1, '2026-07-28 17:11:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1372, 8, '2026-07-28 17:11:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1373, 1, '2026-07-28 17:12:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1374, 8, '2026-07-28 17:12:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1375, 1, '2026-07-28 17:13:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1376, 8, '2026-07-28 17:13:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1377, 1, '2026-07-28 17:14:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1378, 8, '2026-07-28 17:14:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1379, 1, '2026-07-28 17:15:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1380, 8, '2026-07-28 17:15:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1381, 1, '2026-07-28 17:16:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1382, 8, '2026-07-28 17:16:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1383, 1, '2026-07-28 17:17:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1384, 8, '2026-07-28 17:17:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1385, 1, '2026-07-28 17:18:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1386, 8, '2026-07-28 17:18:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1387, 1, '2026-07-28 17:19:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1388, 8, '2026-07-28 17:19:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1389, 1, '2026-07-28 17:20:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1390, 1, '2026-07-28 17:21:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1391, 1, '2026-07-28 17:22:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1392, 1, '2026-07-28 17:23:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1393, 1, '2026-07-28 17:24:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1394, 1, '2026-07-28 17:25:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1395, 1, '2026-07-28 17:26:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1396, 1, '2026-07-28 17:27:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1397, 1, '2026-07-28 17:28:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1398, 1, '2026-07-28 17:29:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1399, 1, '2026-07-28 17:30:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1400, 1, '2026-07-28 17:31:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1401, 1, '2026-07-28 17:32:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1402, 1, '2026-07-28 17:33:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1403, 1, '2026-07-28 17:34:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1404, 1, '2026-07-28 17:35:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1405, 1, '2026-07-28 17:36:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1406, 1, '2026-07-28 17:37:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1407, 1, '2026-07-28 17:38:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1408, 1, '2026-07-28 17:39:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1409, 1, '2026-07-28 17:40:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1410, 1, '2026-07-28 17:41:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1411, 1, '2026-07-28 17:42:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1412, 1, '2026-07-28 17:43:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1413, 1, '2026-07-28 17:44:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1414, 1, '2026-07-28 17:45:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1415, 1, '2026-07-28 17:46:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1416, 1, '2026-07-28 17:47:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1417, 8, '2026-07-28 17:47:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1418, 1, '2026-07-28 17:48:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1419, 1, '2026-07-28 17:49:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1420, 1, '2026-07-28 17:50:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1421, 8, '2026-07-29 14:54:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1422, 1, '2026-07-29 15:12:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1423, 2, '2026-07-29 15:12:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1424, 8, '2026-07-29 15:12:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1425, 8, '2026-07-29 15:13:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1426, 8, '2026-07-29 15:14:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1427, 8, '2026-07-29 15:15:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1428, 8, '2026-07-29 15:16:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1429, 8, '2026-07-29 15:17:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1430, 8, '2026-07-29 15:18:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1431, 8, '2026-07-29 15:19:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1432, 8, '2026-07-29 15:20:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1434, 8, '2026-07-29 15:21:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1435, 8, '2026-07-29 15:22:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1436, 8, '2026-07-29 15:23:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1437, 8, '2026-07-29 15:24:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1438, 8, '2026-07-29 15:25:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1439, 8, '2026-07-29 15:26:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1440, 8, '2026-07-29 15:27:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1441, 8, '2026-07-29 15:28:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1442, 8, '2026-07-29 15:29:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1443, 8, '2026-07-29 15:30:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1444, 8, '2026-07-29 15:31:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1445, 8, '2026-07-29 15:32:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1446, 8, '2026-07-29 15:33:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1447, 8, '2026-07-29 15:34:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1448, 8, '2026-07-29 15:35:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1449, 8, '2026-07-29 15:36:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1450, 8, '2026-07-29 15:37:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1451, 8, '2026-07-29 15:38:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1452, 8, '2026-07-29 15:39:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1453, 8, '2026-07-29 15:40:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1454, 8, '2026-07-29 15:41:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1455, 8, '2026-07-29 15:42:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1456, 8, '2026-07-29 15:43:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1457, 8, '2026-07-29 15:44:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1458, 8, '2026-07-29 15:45:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1459, 8, '2026-07-29 15:46:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1460, 8, '2026-07-29 15:47:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1461, 8, '2026-07-29 15:48:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1462, 8, '2026-07-29 15:49:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1463, 8, '2026-07-29 15:50:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1464, 8, '2026-07-29 15:51:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1465, 8, '2026-07-29 15:52:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1466, 8, '2026-07-29 15:53:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1467, 8, '2026-07-29 15:54:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1468, 8, '2026-07-29 15:55:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1469, 8, '2026-07-29 15:56:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1470, 8, '2026-07-29 15:57:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1471, 8, '2026-07-29 15:58:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1472, 8, '2026-07-29 15:59:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1473, 8, '2026-07-29 16:00:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1474, 8, '2026-07-29 16:01:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1475, 8, '2026-07-29 16:02:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1476, 8, '2026-07-29 16:03:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1477, 8, '2026-07-29 16:04:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1478, 8, '2026-07-29 16:05:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1479, 8, '2026-07-29 16:06:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1480, 8, '2026-07-29 16:07:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1481, 8, '2026-07-29 16:08:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1482, 8, '2026-07-29 16:09:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1483, 8, '2026-07-29 16:10:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1484, 8, '2026-07-29 16:11:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1485, 8, '2026-07-29 16:12:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1486, 8, '2026-07-29 16:13:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1487, 8, '2026-07-29 16:14:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1488, 8, '2026-07-29 16:15:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1489, 8, '2026-07-29 16:16:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1490, 8, '2026-07-29 16:17:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1491, 8, '2026-07-29 16:18:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1492, 8, '2026-07-29 16:19:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1493, 8, '2026-07-29 16:20:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1494, 8, '2026-07-29 16:21:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1495, 8, '2026-07-29 16:22:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1496, 8, '2026-07-29 16:23:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1497, 8, '2026-07-29 16:24:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1498, 8, '2026-07-29 16:25:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1499, 8, '2026-07-29 16:26:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1500, 8, '2026-07-29 16:27:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1501, 8, '2026-07-29 16:28:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1502, 8, '2026-07-29 16:29:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1503, 8, '2026-07-29 16:30:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1504, 8, '2026-07-29 16:31:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1505, 8, '2026-07-29 16:32:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1506, 8, '2026-07-29 16:33:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1507, 8, '2026-07-29 16:34:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1508, 8, '2026-07-29 16:35:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1509, 8, '2026-07-29 16:36:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1510, 8, '2026-07-29 16:37:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1511, 8, '2026-07-29 16:38:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1512, 8, '2026-07-29 16:39:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1513, 8, '2026-07-29 16:42:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1514, 8, '2026-07-29 16:43:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1515, 8, '2026-07-29 16:44:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1516, 8, '2026-07-29 16:45:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1517, 8, '2026-07-29 16:46:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1518, 8, '2026-07-29 16:47:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1519, 8, '2026-07-29 16:48:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1520, 8, '2026-07-29 16:49:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1521, 8, '2026-07-29 16:50:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1522, 8, '2026-07-29 16:51:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1523, 8, '2026-07-29 16:52:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1524, 8, '2026-07-29 16:53:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1525, 8, '2026-07-29 16:54:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1526, 8, '2026-07-29 16:55:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1527, 8, '2026-07-29 16:56:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1528, 8, '2026-07-29 16:57:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1529, 8, '2026-07-29 16:58:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1530, 8, '2026-07-29 16:59:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1531, 8, '2026-07-29 17:00:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1532, 8, '2026-07-29 17:01:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1533, 8, '2026-07-29 17:02:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1534, 8, '2026-07-29 17:03:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1535, 8, '2026-07-29 17:04:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1536, 8, '2026-07-29 17:05:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1537, 8, '2026-07-29 17:06:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1538, 8, '2026-07-29 17:07:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1539, 8, '2026-07-29 18:22:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1540, 8, '2026-07-29 18:23:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1541, 8, '2026-07-29 18:24:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1542, 8, '2026-07-29 18:25:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1543, 8, '2026-07-29 18:26:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1544, 8, '2026-07-29 18:27:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1545, 8, '2026-07-29 18:28:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1546, 8, '2026-07-29 18:29:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1547, 8, '2026-07-29 18:30:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1548, 8, '2026-07-29 18:31:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1549, 8, '2026-07-29 18:32:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1550, 8, '2026-07-29 20:00:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1551, 8, '2026-07-29 20:01:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1553, 8, '2026-07-29 20:02:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1555, 8, '2026-07-29 20:03:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1557, 8, '2026-07-29 20:04:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1559, 8, '2026-07-29 20:05:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1561, 8, '2026-07-29 20:06:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1563, 8, '2026-07-29 20:07:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1565, 8, '2026-07-29 20:08:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1567, 8, '2026-07-29 20:09:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1569, 8, '2026-07-29 20:10:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1570, 8, '2026-07-29 20:11:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1571, 8, '2026-07-29 20:12:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1572, 8, '2026-07-29 20:13:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1573, 8, '2026-07-29 20:14:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1574, 8, '2026-07-29 20:57:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1575, 8, '2026-07-29 20:58:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1577, 8, '2026-07-29 20:59:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1579, 8, '2026-07-29 21:00:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1581, 8, '2026-07-29 21:01:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1583, 8, '2026-07-29 21:02:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1585, 8, '2026-07-29 21:03:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1587, 8, '2026-07-29 21:04:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1589, 8, '2026-07-29 21:05:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1591, 8, '2026-07-29 21:06:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1593, 8, '2026-07-29 21:07:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1595, 8, '2026-07-29 21:08:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1597, 8, '2026-07-29 21:09:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1599, 8, '2026-07-29 21:10:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1601, 8, '2026-07-29 21:11:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1603, 8, '2026-07-29 21:12:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1605, 8, '2026-07-29 21:13:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1607, 8, '2026-07-29 21:14:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1609, 8, '2026-07-29 21:15:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1611, 8, '2026-07-29 21:16:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1613, 8, '2026-07-29 21:17:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1615, 8, '2026-07-29 21:18:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1617, 8, '2026-07-29 21:19:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1619, 8, '2026-07-29 21:20:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1621, 8, '2026-07-29 21:21:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1623, 8, '2026-07-29 21:22:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1625, 8, '2026-07-29 21:23:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1627, 8, '2026-07-29 21:24:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1629, 8, '2026-07-29 21:25:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1631, 8, '2026-07-29 21:26:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1633, 8, '2026-07-29 21:27:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1635, 8, '2026-07-29 21:28:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1637, 8, '2026-07-29 21:29:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1639, 8, '2026-07-29 21:30:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1641, 8, '2026-07-29 21:31:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1643, 8, '2026-07-29 21:32:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1645, 8, '2026-07-29 21:33:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1647, 8, '2026-07-29 21:34:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1649, 8, '2026-07-29 21:35:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1651, 8, '2026-07-29 21:36:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1653, 8, '2026-07-29 21:37:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1655, 8, '2026-07-29 21:38:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1656, 8, '2026-07-30 15:30:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1657, 8, '2026-07-30 15:31:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1658, 8, '2026-07-30 15:32:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1659, 1, '2026-07-30 15:33:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1660, 8, '2026-07-30 15:33:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1661, 1, '2026-07-30 15:34:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1662, 8, '2026-07-30 15:34:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1663, 8, '2026-07-30 15:35:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1664, 8, '2026-07-30 15:36:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1665, 8, '2026-07-30 15:37:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1666, 8, '2026-07-30 15:38:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1667, 8, '2026-07-30 15:39:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1668, 8, '2026-07-30 15:40:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1669, 8, '2026-07-30 15:41:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1670, 8, '2026-07-30 15:43:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1671, 8, '2026-07-30 15:44:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1672, 8, '2026-07-30 15:45:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1673, 8, '2026-07-30 15:46:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1674, 8, '2026-07-30 15:47:00', 5, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1675, 8, '2026-07-30 15:48:00', 5, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1676, 8, '2026-07-30 15:49:00', 5, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1677, 8, '2026-07-30 15:50:00', 5, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1678, 8, '2026-07-30 15:51:00', 5, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1679, 8, '2026-07-30 15:52:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1680, 8, '2026-07-30 15:53:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1681, 8, '2026-07-30 15:54:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1682, 8, '2026-07-30 15:55:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1683, 8, '2026-07-30 15:56:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1684, 8, '2026-07-30 15:57:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1685, 8, '2026-07-30 15:58:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1686, 8, '2026-07-30 15:59:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1687, 8, '2026-07-30 16:00:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1688, 8, '2026-07-30 16:01:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1689, 8, '2026-07-30 16:02:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1690, 8, '2026-07-30 16:03:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1691, 8, '2026-07-30 16:04:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1692, 8, '2026-07-30 16:06:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1693, 8, '2026-07-30 16:07:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1694, 8, '2026-07-30 16:08:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1695, 8, '2026-07-30 16:09:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1696, 8, '2026-07-30 16:10:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1697, 8, '2026-07-30 16:11:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1698, 8, '2026-07-30 16:12:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1699, 8, '2026-07-30 16:13:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1700, 8, '2026-07-30 16:14:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1701, 8, '2026-07-30 16:16:00', 8, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1702, 8, '2026-07-30 16:17:00', 8, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1703, 8, '2026-07-30 16:18:00', 8, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1704, 8, '2026-07-30 16:19:00', 8, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1705, 8, '2026-07-30 16:20:00', 8, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1706, 8, '2026-07-30 16:21:00', 8, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1707, 8, '2026-07-30 16:23:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1708, 8, '2026-07-30 16:24:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1709, 8, '2026-07-30 16:25:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1710, 8, '2026-07-30 16:26:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1711, 8, '2026-07-30 16:27:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1712, 8, '2026-07-30 16:28:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1713, 8, '2026-07-30 16:29:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1714, 8, '2026-07-30 16:30:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1715, 8, '2026-07-30 16:32:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1716, 8, '2026-07-30 16:33:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1717, 8, '2026-07-30 16:34:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1718, 8, '2026-07-30 16:35:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1719, 8, '2026-07-30 16:36:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1720, 8, '2026-07-30 16:37:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1721, 8, '2026-07-30 16:38:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1722, 8, '2026-07-30 16:39:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1723, 8, '2026-07-30 16:40:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1724, 8, '2026-07-30 16:41:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1725, 8, '2026-07-30 16:42:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1726, 8, '2026-07-30 16:43:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1727, 8, '2026-07-30 16:44:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1728, 8, '2026-07-30 16:45:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1729, 8, '2026-07-30 16:46:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1730, 8, '2026-07-30 16:47:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1731, 8, '2026-07-30 16:48:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1732, 8, '2026-07-30 16:49:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1733, 8, '2026-07-30 16:50:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1734, 8, '2026-07-30 16:51:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1735, 8, '2026-07-30 16:52:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1736, 8, '2026-07-30 16:53:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1737, 8, '2026-07-30 16:54:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1738, 5, '2026-07-30 16:55:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1739, 8, '2026-07-30 16:55:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1740, 5, '2026-07-30 16:56:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1741, 8, '2026-07-30 16:56:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1742, 1, '2026-07-30 16:57:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1743, 5, '2026-07-30 16:57:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1744, 8, '2026-07-30 16:57:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1745, 1, '2026-07-30 16:58:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1746, 6, '2026-07-30 16:58:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1747, 7, '2026-07-30 16:58:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1748, 8, '2026-07-30 16:58:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1749, 5, '2026-07-30 16:59:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1750, 6, '2026-07-30 16:59:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1751, 7, '2026-07-30 16:59:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1752, 8, '2026-07-30 16:59:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1753, 1, '2026-07-30 17:00:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1754, 8, '2026-07-30 17:00:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1755, 1, '2026-07-30 17:01:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1756, 8, '2026-07-30 17:01:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1757, 1, '2026-07-30 17:05:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1758, 5, '2026-07-30 17:05:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1759, 8, '2026-07-30 17:05:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1760, 1, '2026-07-30 17:06:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1761, 7, '2026-07-30 17:06:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1762, 8, '2026-07-30 17:06:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1763, 1, '2026-07-30 17:07:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1764, 7, '2026-07-30 17:07:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1765, 8, '2026-07-30 17:07:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1766, 1, '2026-07-30 17:08:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1767, 8, '2026-07-30 17:08:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1768, 1, '2026-07-30 17:09:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1769, 8, '2026-07-30 17:09:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1770, 1, '2026-07-30 17:10:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1771, 8, '2026-07-30 17:10:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1772, 1, '2026-07-30 17:11:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1773, 8, '2026-07-30 17:11:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1774, 1, '2026-07-30 17:12:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1775, 8, '2026-07-30 17:12:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1776, 1, '2026-07-30 17:13:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1777, 8, '2026-07-30 17:13:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1778, 1, '2026-07-30 17:14:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1779, 8, '2026-07-30 17:14:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1780, 1, '2026-07-30 17:15:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1781, 8, '2026-07-30 17:15:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1782, 1, '2026-07-30 17:16:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1783, 8, '2026-07-30 17:16:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1784, 1, '2026-07-30 17:17:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1785, 8, '2026-07-30 17:17:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1786, 1, '2026-07-30 17:18:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1787, 8, '2026-07-30 17:18:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1788, 1, '2026-07-30 17:19:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1789, 8, '2026-07-30 17:19:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1790, 1, '2026-07-30 17:20:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1791, 8, '2026-07-30 17:20:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1792, 1, '2026-07-30 17:21:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1793, 8, '2026-07-30 17:21:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1794, 1, '2026-07-30 17:22:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1795, 8, '2026-07-30 17:22:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1796, 1, '2026-07-30 17:24:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1797, 8, '2026-07-30 17:24:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1798, 1, '2026-07-30 17:25:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1799, 8, '2026-07-30 17:25:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1800, 1, '2026-07-30 17:26:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1801, 8, '2026-07-30 17:26:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1802, 1, '2026-07-30 17:27:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1803, 8, '2026-07-30 17:27:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1804, 1, '2026-07-30 17:28:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1805, 8, '2026-07-30 17:28:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1806, 1, '2026-07-30 17:29:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1807, 8, '2026-07-30 17:29:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1808, 1, '2026-07-30 17:30:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1809, 8, '2026-07-30 17:30:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1810, 1, '2026-07-30 17:31:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1811, 8, '2026-07-30 17:31:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1812, 1, '2026-07-30 17:33:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1813, 8, '2026-07-30 17:33:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1814, 1, '2026-07-30 17:34:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1815, 8, '2026-07-30 17:34:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1816, 1, '2026-07-30 17:35:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1817, 8, '2026-07-30 17:35:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1818, 1, '2026-07-30 17:36:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1819, 8, '2026-07-30 17:36:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1820, 1, '2026-07-30 17:37:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1821, 7, '2026-07-30 17:37:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1822, 8, '2026-07-30 17:37:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1823, 1, '2026-07-30 17:38:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1824, 7, '2026-07-30 17:38:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1825, 8, '2026-07-30 17:38:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1826, 1, '2026-07-30 17:39:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1827, 7, '2026-07-30 17:39:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1828, 8, '2026-07-30 17:39:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1829, 1, '2026-07-30 17:40:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1830, 7, '2026-07-30 17:40:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1831, 8, '2026-07-30 17:40:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1832, 1, '2026-07-30 17:41:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1833, 7, '2026-07-30 17:41:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1834, 8, '2026-07-30 17:41:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1835, 1, '2026-07-30 17:42:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1836, 7, '2026-07-30 17:42:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1837, 8, '2026-07-30 17:42:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1838, 1, '2026-07-30 17:44:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1839, 7, '2026-07-30 17:44:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1840, 8, '2026-07-30 17:44:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1841, 1, '2026-07-30 17:45:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1842, 5, '2026-07-30 17:45:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1843, 6, '2026-07-30 17:45:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1844, 7, '2026-07-30 17:45:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1845, 8, '2026-07-30 17:45:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1846, 1, '2026-07-30 17:46:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1847, 6, '2026-07-30 17:46:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1848, 7, '2026-07-30 17:46:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1849, 8, '2026-07-30 17:46:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1850, 1, '2026-07-30 17:47:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1851, 7, '2026-07-30 17:47:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1852, 8, '2026-07-30 17:47:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1853, 1, '2026-07-30 17:48:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1854, 7, '2026-07-30 17:48:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1855, 8, '2026-07-30 17:48:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1856, 1, '2026-07-30 17:49:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1857, 7, '2026-07-30 17:49:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1858, 8, '2026-07-30 17:49:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1859, 1, '2026-07-30 17:50:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1860, 7, '2026-07-30 17:50:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1861, 8, '2026-07-30 17:50:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1862, 1, '2026-07-30 17:51:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1863, 7, '2026-07-30 17:51:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1864, 8, '2026-07-30 17:51:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1865, 1, '2026-07-30 17:53:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1866, 6, '2026-07-30 17:53:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1867, 7, '2026-07-30 17:53:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1868, 8, '2026-07-30 17:53:00', 12, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1869, 1, '2026-07-30 17:54:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1870, 6, '2026-07-30 17:54:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1871, 7, '2026-07-30 17:54:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1872, 8, '2026-07-30 17:54:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1873, 1, '2026-07-30 17:55:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1874, 5, '2026-07-30 17:55:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1875, 6, '2026-07-30 17:55:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1876, 7, '2026-07-30 17:55:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1877, 8, '2026-07-30 17:55:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1878, 1, '2026-07-30 17:56:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1879, 5, '2026-07-30 17:56:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1880, 7, '2026-07-30 17:56:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1881, 8, '2026-07-30 17:56:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1882, 1, '2026-07-30 17:58:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1883, 7, '2026-07-30 17:58:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1884, 8, '2026-07-30 17:58:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1885, 1, '2026-07-30 17:59:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1886, 7, '2026-07-30 17:59:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1887, 8, '2026-07-30 17:59:00', 12, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1888, 1, '2026-07-30 18:00:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1889, 7, '2026-07-30 18:00:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1890, 8, '2026-07-30 18:00:00', 12, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1891, 1, '2026-07-30 18:01:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1892, 7, '2026-07-30 18:01:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1893, 8, '2026-07-30 18:01:00', 12, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1894, 1, '2026-07-30 18:02:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1895, 7, '2026-07-30 18:02:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1896, 8, '2026-07-30 18:02:00', 12, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1897, 1, '2026-07-30 18:03:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1898, 7, '2026-07-30 18:03:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1899, 8, '2026-07-30 18:03:00', 12, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1900, 1, '2026-07-30 18:04:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1901, 7, '2026-07-30 18:04:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1902, 8, '2026-07-30 18:04:00', 12, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1903, 1, '2026-07-30 18:05:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1904, 7, '2026-07-30 18:05:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1905, 8, '2026-07-30 18:05:00', 12, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1906, 1, '2026-07-30 18:06:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1907, 7, '2026-07-30 18:06:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1908, 8, '2026-07-30 18:06:00', 12, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1909, 1, '2026-07-30 18:07:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1910, 7, '2026-07-30 18:07:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1911, 8, '2026-07-30 18:07:00', 12, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1912, 1, '2026-07-30 18:08:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1913, 7, '2026-07-30 18:08:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1914, 8, '2026-07-30 18:08:00', 12, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1915, 1, '2026-07-30 18:09:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1916, 7, '2026-07-30 18:09:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1917, 8, '2026-07-30 18:09:00', 12, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1918, 1, '2026-07-30 18:10:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1919, 7, '2026-07-30 18:10:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1920, 8, '2026-07-30 18:10:00', 12, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1921, 1, '2026-07-30 18:11:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1922, 7, '2026-07-30 18:11:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1923, 8, '2026-07-30 18:11:00', 12, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1924, 1, '2026-07-30 18:12:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1925, 7, '2026-07-30 18:12:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1926, 8, '2026-07-30 18:12:00', 12, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1927, 1, '2026-07-30 18:13:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1928, 7, '2026-07-30 18:13:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1929, 8, '2026-07-30 18:13:00', 12, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1930, 1, '2026-07-30 18:14:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1931, 7, '2026-07-30 18:14:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1932, 8, '2026-07-30 18:14:00', 12, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1933, 1, '2026-07-30 18:15:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1934, 7, '2026-07-30 18:15:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1935, 8, '2026-07-30 18:15:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1936, 1, '2026-07-30 18:16:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1937, 7, '2026-07-30 18:16:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1938, 8, '2026-07-30 18:16:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1939, 1, '2026-07-30 18:17:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1940, 7, '2026-07-30 18:17:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1941, 8, '2026-07-30 18:17:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1942, 1, '2026-07-30 18:18:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1943, 7, '2026-07-30 18:18:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1944, 8, '2026-07-30 18:18:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1945, 1, '2026-07-30 18:19:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1946, 7, '2026-07-30 18:19:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1947, 8, '2026-07-30 18:19:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1948, 1, '2026-07-30 18:20:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1949, 7, '2026-07-30 18:20:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1950, 8, '2026-07-30 18:20:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1951, 1, '2026-07-30 18:21:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1952, 7, '2026-07-30 18:21:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1953, 8, '2026-07-30 18:21:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1954, 1, '2026-07-30 18:22:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1955, 7, '2026-07-30 18:22:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1956, 8, '2026-07-30 18:22:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1957, 1, '2026-07-30 18:23:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1958, 7, '2026-07-30 18:23:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1959, 8, '2026-07-30 18:23:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1960, 1, '2026-07-30 18:24:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1961, 7, '2026-07-30 18:24:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1962, 8, '2026-07-30 18:24:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1963, 1, '2026-07-30 18:25:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1964, 7, '2026-07-30 18:25:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1965, 8, '2026-07-30 18:25:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1966, 1, '2026-07-30 18:26:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1967, 7, '2026-07-30 18:26:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1968, 8, '2026-07-30 18:26:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1969, 1, '2026-07-30 18:27:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1970, 7, '2026-07-30 18:27:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1971, 8, '2026-07-30 18:27:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1972, 1, '2026-07-30 18:28:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1973, 7, '2026-07-30 18:28:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1974, 8, '2026-07-30 18:28:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1975, 1, '2026-07-30 18:29:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1976, 7, '2026-07-30 18:29:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1977, 8, '2026-07-30 18:29:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1978, 1, '2026-07-30 18:30:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1979, 7, '2026-07-30 18:30:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1980, 8, '2026-07-30 18:30:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1981, 1, '2026-07-30 18:31:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1982, 7, '2026-07-30 18:31:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1983, 8, '2026-07-30 18:31:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1984, 1, '2026-07-30 18:32:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1985, 7, '2026-07-30 18:32:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1986, 8, '2026-07-30 18:32:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1987, 1, '2026-07-30 18:33:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1988, 7, '2026-07-30 18:33:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1989, 8, '2026-07-30 18:33:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1990, 1, '2026-07-30 18:34:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1991, 7, '2026-07-30 18:34:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1992, 8, '2026-07-30 18:34:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1993, 1, '2026-07-30 18:35:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1994, 7, '2026-07-30 18:35:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1995, 8, '2026-07-30 18:35:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1996, 1, '2026-07-30 18:36:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1997, 7, '2026-07-30 18:36:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1998, 8, '2026-07-30 18:36:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (1999, 1, '2026-07-30 18:37:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2000, 7, '2026-07-30 18:37:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2001, 8, '2026-07-30 18:37:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2002, 1, '2026-07-30 18:38:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2003, 7, '2026-07-30 18:38:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2004, 8, '2026-07-30 18:38:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2005, 1, '2026-07-30 18:39:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2006, 7, '2026-07-30 18:39:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2007, 8, '2026-07-30 18:39:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2008, 1, '2026-07-30 18:40:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2009, 7, '2026-07-30 18:40:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2010, 8, '2026-07-30 18:40:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2011, 1, '2026-07-30 18:41:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2012, 7, '2026-07-30 18:41:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2013, 8, '2026-07-30 18:41:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2014, 1, '2026-07-30 18:42:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2015, 7, '2026-07-30 18:42:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2016, 8, '2026-07-30 18:42:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2017, 1, '2026-07-30 18:43:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2018, 7, '2026-07-30 18:43:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2019, 8, '2026-07-30 18:43:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2020, 1, '2026-07-30 18:44:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2021, 7, '2026-07-30 18:44:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2022, 8, '2026-07-30 18:44:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2023, 1, '2026-07-30 18:45:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2024, 7, '2026-07-30 18:45:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2025, 8, '2026-07-30 18:45:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2026, 1, '2026-07-30 18:46:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2027, 7, '2026-07-30 18:46:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2028, 8, '2026-07-30 18:46:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2029, 1, '2026-07-30 18:47:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2030, 7, '2026-07-30 18:47:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2031, 8, '2026-07-30 18:47:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2032, 1, '2026-07-30 18:48:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2033, 7, '2026-07-30 18:48:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2034, 8, '2026-07-30 18:48:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2035, 1, '2026-07-30 18:49:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2036, 7, '2026-07-30 18:49:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2037, 8, '2026-07-30 18:49:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2038, 1, '2026-07-30 18:50:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2039, 7, '2026-07-30 18:50:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2040, 8, '2026-07-30 18:50:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2041, 1, '2026-07-30 18:51:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2042, 7, '2026-07-30 18:51:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2043, 8, '2026-07-30 18:51:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2044, 1, '2026-07-30 18:52:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2045, 7, '2026-07-30 18:52:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2046, 8, '2026-07-30 18:52:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2047, 1, '2026-07-30 18:53:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2048, 7, '2026-07-30 18:53:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2049, 8, '2026-07-30 18:53:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2050, 1, '2026-07-30 18:54:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2051, 7, '2026-07-30 18:54:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2052, 8, '2026-07-30 18:54:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2053, 1, '2026-07-30 18:55:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2054, 7, '2026-07-30 18:55:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2055, 8, '2026-07-30 18:55:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2056, 1, '2026-07-30 18:56:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2057, 7, '2026-07-30 18:56:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2058, 8, '2026-07-30 18:56:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2059, 1, '2026-07-30 18:57:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2060, 7, '2026-07-30 18:57:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2061, 8, '2026-07-30 18:57:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2062, 1, '2026-07-30 18:58:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2063, 7, '2026-07-30 18:58:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2064, 8, '2026-07-30 18:58:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2065, 1, '2026-07-30 18:59:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2066, 7, '2026-07-30 18:59:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2067, 8, '2026-07-30 18:59:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2068, 1, '2026-07-30 19:00:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2069, 7, '2026-07-30 19:00:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2070, 8, '2026-07-30 19:00:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2071, 1, '2026-07-30 19:01:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2072, 7, '2026-07-30 19:01:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2073, 8, '2026-07-30 19:01:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2074, 1, '2026-07-30 19:02:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2075, 7, '2026-07-30 19:02:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2076, 8, '2026-07-30 19:02:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2077, 1, '2026-07-30 19:03:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2078, 7, '2026-07-30 19:03:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2079, 8, '2026-07-30 19:03:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2080, 1, '2026-07-30 19:04:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2081, 7, '2026-07-30 19:04:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2082, 8, '2026-07-30 19:04:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2083, 1, '2026-07-30 19:05:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2084, 7, '2026-07-30 19:05:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2085, 8, '2026-07-30 19:05:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2086, 1, '2026-07-30 19:06:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2087, 7, '2026-07-30 19:06:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2088, 8, '2026-07-30 19:06:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2089, 1, '2026-07-30 19:07:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2090, 7, '2026-07-30 19:07:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2091, 8, '2026-07-30 19:07:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2092, 1, '2026-07-30 19:08:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2093, 7, '2026-07-30 19:08:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2094, 8, '2026-07-30 19:08:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2095, 1, '2026-07-30 19:10:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2096, 7, '2026-07-30 19:10:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2097, 8, '2026-07-30 19:10:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2098, 1, '2026-07-30 19:11:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2099, 7, '2026-07-30 19:11:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2100, 8, '2026-07-30 19:11:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2101, 1, '2026-07-30 19:12:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2102, 6, '2026-07-30 19:12:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2103, 7, '2026-07-30 19:12:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2104, 8, '2026-07-30 19:12:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2105, 1, '2026-07-30 19:13:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2106, 7, '2026-07-30 19:13:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2107, 8, '2026-07-30 19:13:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2108, 1, '2026-07-30 19:14:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2109, 7, '2026-07-30 19:14:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2110, 8, '2026-07-30 19:14:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2111, 1, '2026-07-30 19:15:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2112, 7, '2026-07-30 19:15:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2113, 8, '2026-07-30 19:15:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2114, 1, '2026-07-30 19:16:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2115, 7, '2026-07-30 19:16:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2116, 8, '2026-07-30 19:16:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2117, 1, '2026-07-30 19:17:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2118, 7, '2026-07-30 19:17:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2119, 8, '2026-07-30 19:17:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2120, 1, '2026-07-30 19:18:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2121, 7, '2026-07-30 19:18:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2122, 8, '2026-07-30 19:18:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2123, 1, '2026-07-30 19:19:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2124, 7, '2026-07-30 19:19:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2125, 8, '2026-07-30 19:19:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2126, 1, '2026-07-30 19:20:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2127, 7, '2026-07-30 19:20:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2128, 8, '2026-07-30 19:20:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2129, 1, '2026-07-30 19:21:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2130, 7, '2026-07-30 19:21:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2131, 8, '2026-07-30 19:21:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2132, 1, '2026-07-30 19:22:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2133, 7, '2026-07-30 19:22:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2134, 8, '2026-07-30 19:22:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2135, 1, '2026-07-30 19:23:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2136, 7, '2026-07-30 19:23:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2137, 8, '2026-07-30 19:23:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2138, 1, '2026-07-30 19:24:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2139, 7, '2026-07-30 19:24:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2140, 8, '2026-07-30 19:24:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2141, 1, '2026-07-30 19:25:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2142, 7, '2026-07-30 19:25:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2143, 8, '2026-07-30 19:25:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2144, 1, '2026-07-30 19:26:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2145, 7, '2026-07-30 19:26:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2146, 8, '2026-07-30 19:26:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2147, 1, '2026-07-30 19:27:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2148, 6, '2026-07-30 19:27:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2149, 7, '2026-07-30 19:27:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2150, 8, '2026-07-30 19:27:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2151, 1, '2026-07-30 19:28:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2152, 6, '2026-07-30 19:28:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2153, 7, '2026-07-30 19:28:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2154, 8, '2026-07-30 19:28:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2155, 1, '2026-07-30 19:29:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2156, 7, '2026-07-30 19:29:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2157, 8, '2026-07-30 19:29:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2158, 1, '2026-07-30 19:30:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2159, 7, '2026-07-30 19:30:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2160, 8, '2026-07-30 19:30:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2161, 1, '2026-07-30 19:31:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2162, 7, '2026-07-30 19:31:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2163, 8, '2026-07-30 19:31:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2164, 1, '2026-07-30 19:32:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2165, 7, '2026-07-30 19:32:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2166, 8, '2026-07-30 19:32:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2167, 1, '2026-07-30 19:33:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2168, 7, '2026-07-30 19:33:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2169, 8, '2026-07-30 19:33:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2170, 1, '2026-07-30 19:34:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2171, 7, '2026-07-30 19:34:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2172, 8, '2026-07-30 19:34:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2173, 1, '2026-07-30 19:35:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2174, 7, '2026-07-30 19:35:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2175, 8, '2026-07-30 19:35:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2176, 1, '2026-07-30 19:36:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2177, 7, '2026-07-30 19:36:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2178, 8, '2026-07-30 19:36:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2179, 1, '2026-07-30 19:37:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2180, 7, '2026-07-30 19:37:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2181, 8, '2026-07-30 19:37:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2182, 1, '2026-07-30 19:38:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2183, 7, '2026-07-30 19:38:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2184, 8, '2026-07-30 19:38:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2185, 1, '2026-07-30 19:39:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2186, 7, '2026-07-30 19:39:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2187, 8, '2026-07-30 19:39:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2188, 1, '2026-07-30 19:40:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2189, 7, '2026-07-30 19:40:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2190, 8, '2026-07-30 19:40:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2191, 1, '2026-07-30 19:41:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2192, 7, '2026-07-30 19:41:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2193, 8, '2026-07-30 19:41:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2194, 1, '2026-07-30 19:42:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2195, 6, '2026-07-30 19:42:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2196, 7, '2026-07-30 19:42:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2197, 8, '2026-07-30 19:42:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2198, 1, '2026-07-30 19:43:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2199, 7, '2026-07-30 19:43:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2200, 8, '2026-07-30 19:43:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2201, 1, '2026-07-30 19:44:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2202, 6, '2026-07-30 19:44:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2203, 7, '2026-07-30 19:44:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2204, 8, '2026-07-30 19:44:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2205, 1, '2026-07-30 19:45:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2206, 6, '2026-07-30 19:45:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2207, 7, '2026-07-30 19:45:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2208, 8, '2026-07-30 19:45:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2209, 1, '2026-07-30 19:46:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2210, 6, '2026-07-30 19:46:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2211, 7, '2026-07-30 19:46:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2212, 8, '2026-07-30 19:46:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2213, 1, '2026-07-30 19:47:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2214, 6, '2026-07-30 19:47:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2215, 7, '2026-07-30 19:47:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2216, 8, '2026-07-30 19:47:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2217, 1, '2026-07-30 19:48:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2218, 6, '2026-07-30 19:48:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2219, 7, '2026-07-30 19:48:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2220, 8, '2026-07-30 19:48:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2221, 1, '2026-07-30 19:49:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2222, 6, '2026-07-30 19:49:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2223, 7, '2026-07-30 19:49:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2224, 8, '2026-07-30 19:49:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2225, 1, '2026-07-30 19:50:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2226, 6, '2026-07-30 19:50:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2227, 7, '2026-07-30 19:50:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2228, 8, '2026-07-30 19:50:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2229, 1, '2026-07-30 19:51:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2230, 6, '2026-07-30 19:51:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2231, 7, '2026-07-30 19:51:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2232, 8, '2026-07-30 19:51:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2233, 1, '2026-07-30 19:52:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2234, 6, '2026-07-30 19:52:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2235, 7, '2026-07-30 19:52:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2236, 8, '2026-07-30 19:52:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2237, 1, '2026-07-30 19:53:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2238, 6, '2026-07-30 19:53:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2239, 7, '2026-07-30 19:53:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2240, 8, '2026-07-30 19:53:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2241, 1, '2026-07-30 19:54:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2242, 6, '2026-07-30 19:54:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2243, 7, '2026-07-30 19:54:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2244, 8, '2026-07-30 19:54:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2245, 1, '2026-07-30 19:55:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2246, 6, '2026-07-30 19:55:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2247, 7, '2026-07-30 19:55:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2248, 8, '2026-07-30 19:55:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2249, 1, '2026-07-30 19:56:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2250, 6, '2026-07-30 19:56:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2251, 7, '2026-07-30 19:56:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2252, 8, '2026-07-30 19:56:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2253, 1, '2026-07-30 19:57:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2254, 6, '2026-07-30 19:57:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2255, 7, '2026-07-30 19:57:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2256, 8, '2026-07-30 19:57:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2257, 1, '2026-07-30 19:58:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2258, 6, '2026-07-30 19:58:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2259, 7, '2026-07-30 19:58:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2260, 8, '2026-07-30 19:58:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2261, 1, '2026-07-30 19:59:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2262, 6, '2026-07-30 19:59:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2263, 7, '2026-07-30 19:59:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2264, 8, '2026-07-30 19:59:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2265, 1, '2026-07-30 20:00:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2266, 7, '2026-07-30 20:00:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2267, 8, '2026-07-30 20:00:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2268, 1, '2026-07-30 20:01:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2269, 7, '2026-07-30 20:01:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2270, 8, '2026-07-30 20:01:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2271, 1, '2026-07-30 20:02:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2272, 7, '2026-07-30 20:02:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2273, 8, '2026-07-30 20:02:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2274, 1, '2026-07-30 20:03:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2275, 7, '2026-07-30 20:03:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2276, 8, '2026-07-30 20:03:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2277, 1, '2026-07-30 20:04:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2278, 7, '2026-07-30 20:04:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2279, 8, '2026-07-30 20:04:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2280, 1, '2026-07-30 20:05:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2281, 7, '2026-07-30 20:05:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2282, 8, '2026-07-30 20:05:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2283, 1, '2026-07-30 20:06:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2284, 7, '2026-07-30 20:06:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2285, 8, '2026-07-30 20:06:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2286, 1, '2026-07-30 20:07:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2287, 7, '2026-07-30 20:07:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2288, 8, '2026-07-30 20:07:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2289, 1, '2026-07-30 20:08:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2290, 7, '2026-07-30 20:08:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2291, 8, '2026-07-30 20:08:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2292, 1, '2026-07-30 20:09:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2293, 7, '2026-07-30 20:09:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2294, 8, '2026-07-30 20:09:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2295, 1, '2026-07-30 20:10:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2296, 7, '2026-07-30 20:10:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2297, 8, '2026-07-30 20:10:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2298, 1, '2026-07-30 20:11:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2299, 7, '2026-07-30 20:11:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2300, 8, '2026-07-30 20:11:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2301, 1, '2026-07-30 20:12:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2302, 7, '2026-07-30 20:12:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2303, 8, '2026-07-30 20:12:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2304, 1, '2026-07-30 20:13:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2305, 7, '2026-07-30 20:13:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2306, 8, '2026-07-30 20:13:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2307, 1, '2026-07-30 20:14:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2308, 7, '2026-07-30 20:14:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2309, 8, '2026-07-30 20:14:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2310, 1, '2026-07-30 20:15:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2311, 7, '2026-07-30 20:15:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2312, 8, '2026-07-30 20:15:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2313, 1, '2026-07-30 20:16:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2314, 7, '2026-07-30 20:16:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2315, 8, '2026-07-30 20:16:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2316, 1, '2026-07-30 20:17:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2317, 7, '2026-07-30 20:17:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2318, 8, '2026-07-30 20:17:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2319, 1, '2026-07-30 20:18:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2320, 7, '2026-07-30 20:18:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2321, 8, '2026-07-30 20:18:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2322, 1, '2026-07-30 20:19:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2323, 7, '2026-07-30 20:19:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2324, 8, '2026-07-30 20:19:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2325, 1, '2026-07-30 20:20:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2326, 7, '2026-07-30 20:20:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2327, 8, '2026-07-30 20:20:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2328, 1, '2026-07-30 20:21:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2329, 7, '2026-07-30 20:21:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2330, 8, '2026-07-30 20:21:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2331, 1, '2026-07-30 20:22:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2332, 7, '2026-07-30 20:22:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2333, 8, '2026-07-30 20:22:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2334, 1, '2026-07-30 20:23:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2335, 7, '2026-07-30 20:23:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2336, 8, '2026-07-30 20:23:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2337, 1, '2026-07-30 20:24:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2338, 7, '2026-07-30 20:24:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2339, 8, '2026-07-30 20:24:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2340, 1, '2026-07-30 20:25:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2341, 7, '2026-07-30 20:25:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2342, 8, '2026-07-30 20:25:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2343, 1, '2026-07-30 20:26:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2344, 7, '2026-07-30 20:26:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2345, 8, '2026-07-30 20:26:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2346, 1, '2026-07-30 20:27:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2347, 7, '2026-07-30 20:27:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2348, 8, '2026-07-30 20:27:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2349, 1, '2026-07-30 20:28:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2350, 7, '2026-07-30 20:28:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2351, 8, '2026-07-30 20:28:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2352, 1, '2026-07-30 20:29:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2353, 7, '2026-07-30 20:29:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2354, 8, '2026-07-30 20:29:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2355, 1, '2026-07-30 20:30:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2356, 7, '2026-07-30 20:30:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2357, 8, '2026-07-30 20:30:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2358, 1, '2026-07-30 20:31:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2359, 7, '2026-07-30 20:31:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2360, 8, '2026-07-30 20:31:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2361, 1, '2026-07-30 20:32:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2362, 7, '2026-07-30 20:32:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2363, 8, '2026-07-30 20:32:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2364, 1, '2026-07-30 20:33:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2365, 7, '2026-07-30 20:33:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2366, 8, '2026-07-30 20:33:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2367, 1, '2026-07-30 20:34:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2368, 7, '2026-07-30 20:34:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2369, 8, '2026-07-30 20:34:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2370, 1, '2026-07-30 20:35:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2371, 7, '2026-07-30 20:35:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2372, 8, '2026-07-30 20:35:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2373, 1, '2026-07-30 20:36:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2374, 7, '2026-07-30 20:36:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2375, 8, '2026-07-30 20:36:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2376, 1, '2026-07-30 20:37:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2377, 7, '2026-07-30 20:37:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2378, 8, '2026-07-30 20:37:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2379, 1, '2026-07-30 20:38:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2380, 7, '2026-07-30 20:38:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2381, 8, '2026-07-30 20:38:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2382, 1, '2026-07-30 20:39:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2383, 7, '2026-07-30 20:39:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2384, 8, '2026-07-30 20:39:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2385, 1, '2026-07-30 20:40:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2386, 7, '2026-07-30 20:40:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2387, 8, '2026-07-30 20:40:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2388, 1, '2026-07-30 20:41:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2389, 7, '2026-07-30 20:41:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2390, 8, '2026-07-30 20:41:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2391, 1, '2026-07-30 20:42:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2392, 7, '2026-07-30 20:42:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2393, 8, '2026-07-30 20:42:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2394, 1, '2026-07-30 20:43:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2395, 7, '2026-07-30 20:43:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2396, 8, '2026-07-30 20:43:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2397, 1, '2026-07-30 20:44:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2398, 7, '2026-07-30 20:44:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2399, 8, '2026-07-30 20:44:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2400, 1, '2026-07-30 20:45:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2401, 7, '2026-07-30 20:45:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2402, 8, '2026-07-30 20:45:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2403, 1, '2026-07-30 20:46:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2404, 7, '2026-07-30 20:46:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2405, 8, '2026-07-30 20:46:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2406, 1, '2026-07-30 20:47:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2407, 7, '2026-07-30 20:47:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2408, 8, '2026-07-30 20:47:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2409, 1, '2026-07-30 20:48:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2410, 7, '2026-07-30 20:48:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2411, 8, '2026-07-30 20:48:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2412, 1, '2026-07-30 20:49:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2413, 7, '2026-07-30 20:49:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2414, 8, '2026-07-30 20:49:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2415, 1, '2026-07-30 20:50:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2416, 7, '2026-07-30 20:50:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2417, 8, '2026-07-30 20:50:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2418, 1, '2026-07-30 20:51:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2419, 7, '2026-07-30 20:51:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2420, 8, '2026-07-30 20:51:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2421, 1, '2026-07-30 20:52:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2422, 7, '2026-07-30 20:52:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2423, 8, '2026-07-30 20:52:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2424, 1, '2026-07-30 20:53:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2425, 7, '2026-07-30 20:53:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2426, 8, '2026-07-30 20:53:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2427, 1, '2026-07-30 20:54:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2428, 7, '2026-07-30 20:54:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2429, 8, '2026-07-30 20:54:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2430, 1, '2026-07-30 20:55:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2431, 7, '2026-07-30 20:55:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2432, 8, '2026-07-30 20:55:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2433, 1, '2026-07-30 20:57:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2434, 7, '2026-07-30 20:57:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2435, 8, '2026-07-30 20:57:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2436, 1, '2026-07-30 20:58:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2437, 6, '2026-07-30 20:58:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2438, 7, '2026-07-30 20:58:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2439, 8, '2026-07-30 20:58:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2440, 1, '2026-07-30 20:59:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2441, 5, '2026-07-30 20:59:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2442, 6, '2026-07-30 20:59:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2443, 7, '2026-07-30 20:59:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2444, 8, '2026-07-30 20:59:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2445, 1, '2026-07-30 21:00:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2446, 5, '2026-07-30 21:00:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2447, 7, '2026-07-30 21:00:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2448, 8, '2026-07-30 21:00:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2449, 1, '2026-07-30 21:01:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2450, 5, '2026-07-30 21:01:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2451, 7, '2026-07-30 21:01:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2452, 8, '2026-07-30 21:01:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2453, 1, '2026-07-30 21:02:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2454, 5, '2026-07-30 21:02:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2455, 7, '2026-07-30 21:02:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2456, 8, '2026-07-30 21:02:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2457, 1, '2026-07-30 21:03:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2458, 5, '2026-07-30 21:03:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2459, 7, '2026-07-30 21:03:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2460, 8, '2026-07-30 21:03:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2461, 1, '2026-07-30 21:04:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2462, 7, '2026-07-30 21:04:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2463, 8, '2026-07-30 21:04:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2464, 1, '2026-07-30 21:05:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2465, 7, '2026-07-30 21:05:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2466, 8, '2026-07-30 21:05:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2467, 1, '2026-07-30 21:06:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2468, 7, '2026-07-30 21:06:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2469, 8, '2026-07-30 21:06:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2470, 1, '2026-07-30 21:07:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2471, 7, '2026-07-30 21:07:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2472, 8, '2026-07-30 21:07:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2473, 1, '2026-07-30 21:08:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2474, 7, '2026-07-30 21:08:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2475, 8, '2026-07-30 21:08:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2479, 1, '2026-07-30 21:09:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2480, 7, '2026-07-30 21:09:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2481, 8, '2026-07-30 21:09:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2485, 1, '2026-07-30 21:10:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2486, 7, '2026-07-30 21:10:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2487, 8, '2026-07-30 21:10:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2491, 1, '2026-07-30 21:11:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2492, 7, '2026-07-30 21:11:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2493, 8, '2026-07-30 21:11:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2497, 1, '2026-07-30 21:12:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2498, 7, '2026-07-30 21:12:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2499, 8, '2026-07-30 21:12:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2503, 1, '2026-07-30 21:13:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2504, 7, '2026-07-30 21:13:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2505, 8, '2026-07-30 21:13:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2509, 1, '2026-07-30 21:14:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2510, 7, '2026-07-30 21:14:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2511, 8, '2026-07-30 21:14:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2515, 1, '2026-07-30 21:16:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2516, 7, '2026-07-30 21:16:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2517, 8, '2026-07-30 21:16:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2518, 1, '2026-07-30 21:17:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2519, 7, '2026-07-30 21:17:00', 5, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2520, 8, '2026-07-30 21:17:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2521, 1, '2026-07-30 21:18:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2522, 7, '2026-07-30 21:18:00', 5, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2523, 8, '2026-07-30 21:18:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2524, 1, '2026-07-30 21:19:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2525, 7, '2026-07-30 21:19:00', 5, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2526, 8, '2026-07-30 21:19:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2527, 1, '2026-07-30 21:20:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2528, 7, '2026-07-30 21:20:00', 5, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2529, 8, '2026-07-30 21:20:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2530, 1, '2026-07-30 21:23:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2531, 7, '2026-07-30 21:23:00', 5, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2532, 8, '2026-07-30 21:23:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2533, 1, '2026-07-30 21:24:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2534, 7, '2026-07-30 21:24:00', 5, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2535, 8, '2026-07-30 21:24:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2536, 1, '2026-07-30 21:25:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2537, 7, '2026-07-30 21:25:00', 5, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2538, 8, '2026-07-30 21:25:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2539, 1, '2026-07-30 21:26:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2540, 7, '2026-07-30 21:26:00', 5, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2541, 8, '2026-07-30 21:26:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2542, 1, '2026-07-30 21:27:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2543, 7, '2026-07-30 21:27:00', 5, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2544, 8, '2026-07-30 21:27:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2545, 1, '2026-07-30 21:28:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2546, 7, '2026-07-30 21:28:00', 5, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2547, 8, '2026-07-30 21:28:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2548, 1, '2026-07-30 21:29:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2549, 7, '2026-07-30 21:29:00', 5, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2550, 8, '2026-07-30 21:29:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2551, 1, '2026-07-30 21:30:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2552, 7, '2026-07-30 21:30:00', 5, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2553, 8, '2026-07-30 21:30:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2554, 1, '2026-07-30 21:31:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2555, 7, '2026-07-30 21:31:00', 5, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2556, 8, '2026-07-30 21:31:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2557, 1, '2026-07-30 21:32:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2558, 7, '2026-07-30 21:32:00', 5, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2559, 8, '2026-07-30 21:32:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2560, 1, '2026-07-30 21:33:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2561, 7, '2026-07-30 21:33:00', 5, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2562, 8, '2026-07-30 21:33:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2563, 1, '2026-07-30 21:34:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2564, 7, '2026-07-30 21:34:00', 5, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2565, 8, '2026-07-30 21:34:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2566, 1, '2026-07-30 21:35:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2567, 7, '2026-07-30 21:35:00', 5, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2568, 8, '2026-07-30 21:35:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2569, 1, '2026-07-30 21:36:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2570, 7, '2026-07-30 21:36:00', 5, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2571, 8, '2026-07-30 21:36:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2572, 1, '2026-07-30 21:37:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2573, 7, '2026-07-30 21:37:00', 5, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2574, 8, '2026-07-30 21:37:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2575, 1, '2026-07-30 21:39:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2576, 7, '2026-07-30 21:39:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2577, 8, '2026-07-30 21:39:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2578, 1, '2026-07-30 21:40:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2579, 7, '2026-07-30 21:40:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2580, 8, '2026-07-30 21:40:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2581, 1, '2026-07-30 21:41:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2582, 7, '2026-07-30 21:41:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2583, 8, '2026-07-30 21:41:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2584, 1, '2026-07-30 21:55:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2585, 7, '2026-07-30 21:55:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2586, 8, '2026-07-30 21:55:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2587, 1, '2026-07-30 21:56:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2588, 7, '2026-07-30 21:56:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2589, 8, '2026-07-30 21:56:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2590, 1, '2026-07-30 21:57:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2591, 7, '2026-07-30 21:57:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2592, 8, '2026-07-30 21:57:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2593, 1, '2026-07-30 21:58:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2594, 7, '2026-07-30 21:58:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2595, 8, '2026-07-30 21:58:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2596, 1, '2026-07-30 21:59:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2597, 7, '2026-07-30 21:59:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2598, 8, '2026-07-30 21:59:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2599, 1, '2026-07-30 22:00:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2600, 7, '2026-07-30 22:00:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2601, 8, '2026-07-30 22:00:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2602, 1, '2026-07-30 22:01:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2603, 7, '2026-07-30 22:01:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2604, 8, '2026-07-30 22:01:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2605, 1, '2026-07-30 22:02:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2606, 7, '2026-07-30 22:02:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2607, 8, '2026-07-30 22:02:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2608, 1, '2026-07-30 22:03:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2609, 7, '2026-07-30 22:03:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2610, 8, '2026-07-30 22:03:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2611, 1, '2026-07-30 22:04:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2612, 7, '2026-07-30 22:04:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2613, 8, '2026-07-30 22:04:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2614, 1, '2026-07-30 22:05:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2615, 7, '2026-07-30 22:05:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2616, 8, '2026-07-30 22:05:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2617, 1, '2026-07-30 22:06:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2618, 7, '2026-07-30 22:06:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2619, 8, '2026-07-30 22:06:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2620, 1, '2026-07-30 22:07:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2621, 7, '2026-07-30 22:07:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2622, 8, '2026-07-30 22:07:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2623, 1, '2026-07-30 22:08:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2624, 7, '2026-07-30 22:08:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2625, 8, '2026-07-30 22:08:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2626, 1, '2026-07-30 22:09:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2627, 7, '2026-07-30 22:09:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2628, 8, '2026-07-30 22:09:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2629, 1, '2026-07-30 22:10:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2630, 7, '2026-07-30 22:10:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2631, 8, '2026-07-30 22:10:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2632, 1, '2026-07-30 22:11:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2633, 7, '2026-07-30 22:11:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2634, 8, '2026-07-30 22:11:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2635, 1, '2026-07-30 22:12:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2636, 7, '2026-07-30 22:12:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2637, 8, '2026-07-30 22:12:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2638, 1, '2026-07-30 22:13:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2639, 7, '2026-07-30 22:13:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2640, 8, '2026-07-30 22:13:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2641, 1, '2026-07-30 22:14:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2642, 7, '2026-07-30 22:14:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2643, 8, '2026-07-30 22:14:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2644, 1, '2026-07-30 22:15:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2645, 7, '2026-07-30 22:15:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2646, 8, '2026-07-30 22:15:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2647, 1, '2026-07-30 22:17:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2648, 7, '2026-07-30 22:17:00', 8, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2649, 8, '2026-07-30 22:17:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2650, 1, '2026-07-30 22:18:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2651, 7, '2026-07-30 22:18:00', 8, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2652, 8, '2026-07-30 22:18:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2653, 1, '2026-07-30 22:19:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2654, 7, '2026-07-30 22:19:00', 8, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2655, 8, '2026-07-30 22:19:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2656, 1, '2026-07-30 22:20:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2657, 7, '2026-07-30 22:20:00', 8, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2658, 8, '2026-07-30 22:20:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2659, 1, '2026-07-30 22:21:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2660, 7, '2026-07-30 22:21:00', 8, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2661, 8, '2026-07-30 22:21:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2662, 1, '2026-07-30 22:22:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2663, 7, '2026-07-30 22:22:00', 8, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2664, 8, '2026-07-30 22:22:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2665, 1, '2026-07-30 22:23:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2666, 7, '2026-07-30 22:23:00', 8, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2667, 8, '2026-07-30 22:23:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2668, 1, '2026-07-30 22:24:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2669, 7, '2026-07-30 22:24:00', 8, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2670, 8, '2026-07-30 22:24:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2671, 1, '2026-07-30 22:25:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2672, 7, '2026-07-30 22:25:00', 8, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2673, 8, '2026-07-30 22:25:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2674, 1, '2026-07-30 22:26:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2675, 7, '2026-07-30 22:26:00', 8, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2676, 8, '2026-07-30 22:26:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2677, 1, '2026-07-30 22:27:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2678, 7, '2026-07-30 22:27:00', 8, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2679, 8, '2026-07-30 22:27:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2680, 1, '2026-07-30 22:28:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2681, 7, '2026-07-30 22:28:00', 8, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2682, 8, '2026-07-30 22:28:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2683, 1, '2026-07-30 22:29:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2684, 7, '2026-07-30 22:29:00', 8, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2685, 8, '2026-07-30 22:29:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2686, 1, '2026-07-30 22:30:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2687, 7, '2026-07-30 22:30:00', 8, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2688, 8, '2026-07-30 22:30:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2689, 1, '2026-07-30 22:31:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2690, 7, '2026-07-30 22:31:00', 8, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2691, 8, '2026-07-30 22:31:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2692, 1, '2026-07-30 22:32:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2693, 7, '2026-07-30 22:32:00', 8, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2694, 8, '2026-07-30 22:32:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2695, 1, '2026-07-30 22:33:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2696, 7, '2026-07-30 22:33:00', 8, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2697, 8, '2026-07-30 22:33:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2698, 1, '2026-07-30 22:34:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2699, 7, '2026-07-30 22:34:00', 8, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2700, 8, '2026-07-30 22:34:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2701, 1, '2026-07-30 22:35:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2702, 7, '2026-07-30 22:35:00', 8, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2703, 8, '2026-07-30 22:35:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2704, 1, '2026-07-30 22:36:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2705, 7, '2026-07-30 22:36:00', 8, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2706, 8, '2026-07-30 22:36:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2707, 1, '2026-07-30 22:37:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2708, 7, '2026-07-30 22:37:00', 8, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2709, 8, '2026-07-30 22:37:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2710, 1, '2026-07-30 22:39:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2711, 7, '2026-07-30 22:39:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2712, 8, '2026-07-30 22:39:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2713, 1, '2026-07-30 22:40:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2714, 7, '2026-07-30 22:40:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2715, 8, '2026-07-30 22:40:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2716, 1, '2026-07-30 22:41:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2717, 7, '2026-07-30 22:41:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2718, 8, '2026-07-30 22:41:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2719, 1, '2026-07-30 22:42:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2720, 7, '2026-07-30 22:42:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2721, 8, '2026-07-30 22:42:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2722, 1, '2026-07-30 22:43:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2723, 7, '2026-07-30 22:43:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2724, 8, '2026-07-30 22:43:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2725, 1, '2026-07-30 22:44:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2726, 7, '2026-07-30 22:44:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2727, 8, '2026-07-30 22:44:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2728, 1, '2026-07-30 22:45:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2729, 7, '2026-07-30 22:45:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2730, 8, '2026-07-30 22:45:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2731, 1, '2026-07-30 22:46:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2732, 7, '2026-07-30 22:46:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2733, 8, '2026-07-30 22:46:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2734, 1, '2026-07-30 22:47:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2735, 7, '2026-07-30 22:47:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2736, 8, '2026-07-30 22:47:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2737, 1, '2026-07-30 22:48:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2738, 7, '2026-07-30 22:48:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2739, 8, '2026-07-30 22:48:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2740, 1, '2026-07-30 22:49:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2741, 7, '2026-07-30 22:49:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2742, 8, '2026-07-30 22:49:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2743, 1, '2026-07-30 22:50:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2744, 7, '2026-07-30 22:50:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2745, 8, '2026-07-30 22:50:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2746, 1, '2026-07-30 22:51:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2747, 7, '2026-07-30 22:51:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2748, 8, '2026-07-30 22:51:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2749, 1, '2026-07-30 22:52:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2750, 7, '2026-07-30 22:52:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2751, 8, '2026-07-30 22:52:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2752, 1, '2026-07-30 22:53:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2753, 7, '2026-07-30 22:53:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2754, 8, '2026-07-30 22:53:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2755, 1, '2026-07-30 22:55:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2756, 7, '2026-07-30 22:55:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2757, 8, '2026-07-30 22:55:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2758, 1, '2026-07-30 22:56:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2759, 7, '2026-07-30 22:56:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2760, 8, '2026-07-30 22:56:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2761, 1, '2026-07-30 22:57:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2762, 7, '2026-07-30 22:57:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2763, 8, '2026-07-30 22:57:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2764, 1, '2026-07-30 22:58:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2765, 7, '2026-07-30 22:58:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2766, 8, '2026-07-30 22:58:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2767, 1, '2026-07-30 22:59:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2768, 7, '2026-07-30 22:59:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2769, 8, '2026-07-30 22:59:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2770, 1, '2026-07-30 23:00:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2771, 7, '2026-07-30 23:00:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2772, 8, '2026-07-30 23:00:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2773, 1, '2026-07-30 23:01:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2774, 7, '2026-07-30 23:01:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2775, 8, '2026-07-30 23:01:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2776, 1, '2026-07-30 23:02:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2777, 7, '2026-07-30 23:02:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2778, 8, '2026-07-30 23:02:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2779, 1, '2026-07-30 23:03:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2780, 7, '2026-07-30 23:03:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2781, 8, '2026-07-30 23:03:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2782, 1, '2026-07-30 23:04:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2783, 7, '2026-07-30 23:04:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2784, 8, '2026-07-30 23:04:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2785, 1, '2026-07-30 23:05:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2786, 7, '2026-07-30 23:05:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2787, 8, '2026-07-30 23:05:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2788, 1, '2026-07-30 23:06:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2789, 7, '2026-07-30 23:06:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2790, 8, '2026-07-30 23:06:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2791, 1, '2026-07-30 23:07:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2792, 7, '2026-07-30 23:07:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2793, 8, '2026-07-30 23:07:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2794, 1, '2026-07-30 23:08:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2795, 7, '2026-07-30 23:08:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2796, 8, '2026-07-30 23:08:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2797, 1, '2026-07-30 23:09:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2798, 7, '2026-07-30 23:09:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2799, 8, '2026-07-30 23:09:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2800, 1, '2026-07-30 23:10:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2801, 7, '2026-07-30 23:10:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2802, 8, '2026-07-30 23:10:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2803, 1, '2026-07-30 23:12:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2804, 7, '2026-07-30 23:12:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2805, 8, '2026-07-30 23:12:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2806, 1, '2026-07-30 23:13:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2807, 7, '2026-07-30 23:13:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2808, 8, '2026-07-30 23:13:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2809, 1, '2026-07-30 23:14:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2810, 7, '2026-07-30 23:14:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2811, 8, '2026-07-30 23:14:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2812, 1, '2026-07-30 23:15:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2813, 7, '2026-07-30 23:15:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2814, 8, '2026-07-30 23:15:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2815, 1, '2026-07-30 23:16:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2816, 7, '2026-07-30 23:16:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2817, 8, '2026-07-30 23:16:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2818, 1, '2026-07-30 23:17:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2819, 7, '2026-07-30 23:17:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2820, 8, '2026-07-30 23:17:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2821, 1, '2026-07-30 23:18:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2822, 7, '2026-07-30 23:18:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2823, 8, '2026-07-30 23:18:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2824, 1, '2026-07-30 23:20:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2825, 7, '2026-07-30 23:20:00', 20, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2826, 8, '2026-07-30 23:20:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2827, 1, '2026-07-30 23:21:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2828, 7, '2026-07-30 23:21:00', 20, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2829, 8, '2026-07-30 23:21:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2830, 1, '2026-07-30 23:24:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2831, 5, '2026-07-30 23:24:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2832, 7, '2026-07-30 23:24:00', 19, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2833, 8, '2026-07-30 23:24:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2834, 1, '2026-07-30 23:25:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2835, 5, '2026-07-30 23:25:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2836, 7, '2026-07-30 23:25:00', 22, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2837, 8, '2026-07-30 23:25:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2838, 1, '2026-07-30 23:26:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2839, 5, '2026-07-30 23:26:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2840, 7, '2026-07-30 23:26:00', 22, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2841, 8, '2026-07-30 23:26:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2842, 1, '2026-07-30 23:27:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2843, 5, '2026-07-30 23:27:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2844, 7, '2026-07-30 23:27:00', 22, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2845, 8, '2026-07-30 23:27:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2846, 1, '2026-07-30 23:28:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2847, 5, '2026-07-30 23:28:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2848, 7, '2026-07-30 23:28:00', 22, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2849, 8, '2026-07-30 23:28:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2850, 1, '2026-07-30 23:29:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2851, 5, '2026-07-30 23:29:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2852, 7, '2026-07-30 23:29:00', 22, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2853, 8, '2026-07-30 23:29:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2854, 1, '2026-07-30 23:30:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2855, 5, '2026-07-30 23:30:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2856, 7, '2026-07-30 23:30:00', 22, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2857, 8, '2026-07-30 23:30:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2858, 1, '2026-07-30 23:31:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2859, 5, '2026-07-30 23:31:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2860, 7, '2026-07-30 23:31:00', 22, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2861, 8, '2026-07-30 23:31:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2862, 1, '2026-07-30 23:32:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2863, 5, '2026-07-30 23:32:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2864, 7, '2026-07-30 23:32:00', 22, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2865, 8, '2026-07-30 23:32:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2866, 1, '2026-07-30 23:33:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2867, 5, '2026-07-30 23:33:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2868, 7, '2026-07-30 23:33:00', 22, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2869, 8, '2026-07-30 23:33:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2870, 1, '2026-07-30 23:34:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2871, 5, '2026-07-30 23:34:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2872, 7, '2026-07-30 23:34:00', 22, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2873, 8, '2026-07-30 23:34:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2874, 1, '2026-07-30 23:35:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2875, 5, '2026-07-30 23:35:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2876, 7, '2026-07-30 23:35:00', 22, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2877, 8, '2026-07-30 23:35:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2878, 1, '2026-07-30 23:36:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2879, 5, '2026-07-30 23:36:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2880, 7, '2026-07-30 23:36:00', 22, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2881, 8, '2026-07-30 23:36:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2882, 1, '2026-07-30 23:37:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2883, 5, '2026-07-30 23:37:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2884, 7, '2026-07-30 23:37:00', 22, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2885, 8, '2026-07-30 23:37:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2886, 1, '2026-07-30 23:38:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2887, 5, '2026-07-30 23:38:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2888, 7, '2026-07-30 23:38:00', 20, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2889, 8, '2026-07-30 23:38:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2890, 1, '2026-07-30 23:39:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2891, 5, '2026-07-30 23:39:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2892, 7, '2026-07-30 23:39:00', 20, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2893, 8, '2026-07-30 23:39:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2894, 1, '2026-07-30 23:40:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2895, 5, '2026-07-30 23:40:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2896, 7, '2026-07-30 23:40:00', 20, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2897, 8, '2026-07-30 23:40:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2898, 1, '2026-07-30 23:41:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2899, 5, '2026-07-30 23:41:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2900, 7, '2026-07-30 23:41:00', 20, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2901, 8, '2026-07-30 23:41:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2902, 1, '2026-07-30 23:42:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2903, 5, '2026-07-30 23:42:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2904, 7, '2026-07-30 23:42:00', 20, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2905, 8, '2026-07-30 23:42:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2906, 1, '2026-07-30 23:43:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2907, 5, '2026-07-30 23:43:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2908, 7, '2026-07-30 23:43:00', 20, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2909, 8, '2026-07-30 23:43:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2910, 1, '2026-07-30 23:44:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2911, 5, '2026-07-30 23:44:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2912, 7, '2026-07-30 23:44:00', 20, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2913, 8, '2026-07-30 23:44:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2914, 1, '2026-07-30 23:47:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2915, 5, '2026-07-30 23:47:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2916, 7, '2026-07-30 23:47:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2917, 8, '2026-07-30 23:47:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2918, 1, '2026-07-30 23:48:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2919, 5, '2026-07-30 23:48:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2920, 7, '2026-07-30 23:48:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2921, 8, '2026-07-30 23:48:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2922, 1, '2026-07-30 23:49:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2923, 5, '2026-07-30 23:49:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2924, 7, '2026-07-30 23:49:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2925, 8, '2026-07-30 23:49:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2926, 1, '2026-07-30 23:50:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2927, 5, '2026-07-30 23:50:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2928, 7, '2026-07-30 23:50:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2929, 8, '2026-07-30 23:50:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2930, 1, '2026-07-30 23:51:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2931, 5, '2026-07-30 23:51:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2932, 7, '2026-07-30 23:51:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2933, 8, '2026-07-30 23:51:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2934, 1, '2026-07-30 23:52:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2935, 5, '2026-07-30 23:52:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2936, 7, '2026-07-30 23:52:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2937, 8, '2026-07-30 23:52:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2938, 1, '2026-07-30 23:53:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2939, 5, '2026-07-30 23:53:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2940, 7, '2026-07-30 23:53:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2941, 8, '2026-07-30 23:53:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2942, 1, '2026-07-30 23:54:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2943, 5, '2026-07-30 23:54:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2944, 7, '2026-07-30 23:54:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2945, 8, '2026-07-30 23:54:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2946, 1, '2026-07-30 23:55:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2947, 5, '2026-07-30 23:55:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2948, 7, '2026-07-30 23:55:00', 25, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2949, 8, '2026-07-30 23:55:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2950, 1, '2026-07-30 23:56:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2951, 5, '2026-07-30 23:56:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2952, 7, '2026-07-30 23:56:00', 25, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2953, 8, '2026-07-30 23:56:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2954, 1, '2026-07-30 23:57:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2955, 5, '2026-07-30 23:57:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2956, 7, '2026-07-30 23:57:00', 25, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2957, 8, '2026-07-30 23:57:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2958, 1, '2026-07-30 23:59:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2959, 5, '2026-07-30 23:59:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2960, 7, '2026-07-30 23:59:00', 27, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2961, 8, '2026-07-30 23:59:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2962, 1, '2026-07-31 00:00:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2963, 5, '2026-07-31 00:00:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2964, 7, '2026-07-31 00:00:00', 27, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2965, 8, '2026-07-31 00:00:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2966, 1, '2026-07-31 00:01:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2967, 5, '2026-07-31 00:01:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2968, 7, '2026-07-31 00:01:00', 27, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2969, 8, '2026-07-31 00:01:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2970, 1, '2026-07-31 00:02:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2971, 5, '2026-07-31 00:02:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2972, 7, '2026-07-31 00:02:00', 27, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2973, 8, '2026-07-31 00:02:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2974, 1, '2026-07-31 00:03:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2975, 5, '2026-07-31 00:03:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2976, 7, '2026-07-31 00:03:00', 27, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2977, 8, '2026-07-31 00:03:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2978, 1, '2026-07-31 00:04:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2979, 5, '2026-07-31 00:04:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2980, 7, '2026-07-31 00:04:00', 27, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2981, 8, '2026-07-31 00:04:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2982, 1, '2026-07-31 00:05:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2983, 5, '2026-07-31 00:05:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2984, 7, '2026-07-31 00:05:00', 27, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2985, 8, '2026-07-31 00:05:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2986, 1, '2026-07-31 00:06:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2987, 5, '2026-07-31 00:06:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2988, 7, '2026-07-31 00:06:00', 27, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2989, 8, '2026-07-31 00:06:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2990, 1, '2026-07-31 00:07:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2991, 5, '2026-07-31 00:07:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2992, 7, '2026-07-31 00:07:00', 27, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2993, 8, '2026-07-31 00:07:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2994, 1, '2026-07-31 00:08:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2995, 5, '2026-07-31 00:08:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2996, 7, '2026-07-31 00:08:00', 27, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2997, 8, '2026-07-31 00:08:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2998, 1, '2026-07-31 00:09:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (2999, 5, '2026-07-31 00:09:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3000, 7, '2026-07-31 00:09:00', 27, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3001, 8, '2026-07-31 00:09:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3002, 1, '2026-07-31 00:10:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3003, 5, '2026-07-31 00:10:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3004, 7, '2026-07-31 00:10:00', 27, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3005, 8, '2026-07-31 00:10:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3006, 1, '2026-07-31 00:11:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3007, 5, '2026-07-31 00:11:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3008, 7, '2026-07-31 00:11:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3009, 8, '2026-07-31 00:11:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3010, 1, '2026-07-31 00:12:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3011, 5, '2026-07-31 00:12:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3012, 7, '2026-07-31 00:12:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3013, 8, '2026-07-31 00:12:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3014, 1, '2026-07-31 00:13:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3015, 5, '2026-07-31 00:13:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3016, 7, '2026-07-31 00:13:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3017, 8, '2026-07-31 00:13:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3018, 1, '2026-07-31 00:14:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3019, 5, '2026-07-31 00:14:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3020, 7, '2026-07-31 00:14:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3021, 8, '2026-07-31 00:14:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3022, 1, '2026-07-31 00:15:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3023, 5, '2026-07-31 00:15:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3024, 7, '2026-07-31 00:15:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3025, 8, '2026-07-31 00:15:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3026, 1, '2026-07-31 00:16:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3027, 5, '2026-07-31 00:16:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3028, 7, '2026-07-31 00:16:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3029, 8, '2026-07-31 00:16:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3030, 1, '2026-07-31 00:17:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3031, 5, '2026-07-31 00:17:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3032, 7, '2026-07-31 00:17:00', 29, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3033, 8, '2026-07-31 00:17:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3034, 1, '2026-07-31 00:19:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3035, 5, '2026-07-31 00:19:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3036, 7, '2026-07-31 00:19:00', 31, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3037, 8, '2026-07-31 00:19:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3038, 1, '2026-07-31 00:20:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3039, 5, '2026-07-31 00:20:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3040, 7, '2026-07-31 00:20:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3041, 8, '2026-07-31 00:20:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3042, 1, '2026-07-31 00:21:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3043, 5, '2026-07-31 00:21:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3044, 7, '2026-07-31 00:21:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3045, 8, '2026-07-31 00:21:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3046, 1, '2026-07-31 00:22:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3047, 5, '2026-07-31 00:22:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3048, 7, '2026-07-31 00:22:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3049, 8, '2026-07-31 00:22:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3050, 1, '2026-07-31 00:23:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3051, 5, '2026-07-31 00:23:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3052, 7, '2026-07-31 00:23:00', 35, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3053, 8, '2026-07-31 00:23:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3054, 1, '2026-07-31 00:24:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3055, 5, '2026-07-31 00:24:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3056, 7, '2026-07-31 00:24:00', 35, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3057, 8, '2026-07-31 00:24:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3058, 1, '2026-07-31 00:25:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3059, 5, '2026-07-31 00:25:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3060, 7, '2026-07-31 00:25:00', 35, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3061, 8, '2026-07-31 00:25:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3062, 1, '2026-07-31 00:26:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3063, 5, '2026-07-31 00:26:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3064, 7, '2026-07-31 00:26:00', 35, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3065, 8, '2026-07-31 00:26:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3066, 1, '2026-07-31 00:28:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3067, 5, '2026-07-31 00:28:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3068, 7, '2026-07-31 00:28:00', 36, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3069, 8, '2026-07-31 00:28:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3070, 1, '2026-07-31 00:29:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3071, 5, '2026-07-31 00:29:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3072, 7, '2026-07-31 00:29:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3073, 8, '2026-07-31 00:29:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3074, 1, '2026-07-31 00:30:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3075, 5, '2026-07-31 00:30:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3076, 7, '2026-07-31 00:30:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3077, 8, '2026-07-31 00:30:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3078, 1, '2026-07-31 00:32:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3079, 5, '2026-07-31 00:32:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3080, 7, '2026-07-31 00:32:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3081, 8, '2026-07-31 00:32:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3082, 1, '2026-07-31 00:33:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3083, 5, '2026-07-31 00:33:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3084, 7, '2026-07-31 00:33:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3085, 8, '2026-07-31 00:33:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3086, 1, '2026-07-31 00:34:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3087, 5, '2026-07-31 00:34:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3088, 7, '2026-07-31 00:34:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3089, 8, '2026-07-31 00:34:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3090, 1, '2026-07-31 00:35:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3091, 5, '2026-07-31 00:35:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3092, 6, '2026-07-31 00:35:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3093, 7, '2026-07-31 00:35:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3094, 8, '2026-07-31 00:35:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3095, 1, '2026-07-31 00:36:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3096, 5, '2026-07-31 00:36:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3097, 6, '2026-07-31 00:36:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3098, 7, '2026-07-31 00:36:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3099, 8, '2026-07-31 00:36:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3100, 1, '2026-07-31 00:37:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3101, 5, '2026-07-31 00:37:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3102, 6, '2026-07-31 00:37:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3103, 7, '2026-07-31 00:37:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3104, 8, '2026-07-31 00:37:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3105, 1, '2026-07-31 00:38:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3106, 5, '2026-07-31 00:38:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3107, 6, '2026-07-31 00:38:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3108, 7, '2026-07-31 00:38:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3109, 8, '2026-07-31 00:38:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3110, 1, '2026-07-31 00:39:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3111, 5, '2026-07-31 00:39:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3112, 7, '2026-07-31 00:39:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3113, 8, '2026-07-31 00:39:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3114, 1, '2026-07-31 00:40:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3115, 5, '2026-07-31 00:40:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3116, 7, '2026-07-31 00:40:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3117, 8, '2026-07-31 00:40:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3118, 1, '2026-07-31 00:41:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3119, 5, '2026-07-31 00:41:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3120, 7, '2026-07-31 00:41:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3121, 8, '2026-07-31 00:41:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3122, 1, '2026-07-31 00:42:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3123, 5, '2026-07-31 00:42:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3124, 7, '2026-07-31 00:42:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3125, 8, '2026-07-31 00:42:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3126, 1, '2026-07-31 00:43:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3127, 5, '2026-07-31 00:43:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3128, 7, '2026-07-31 00:43:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3129, 8, '2026-07-31 00:43:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3130, 1, '2026-07-31 00:44:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3131, 5, '2026-07-31 00:44:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3132, 7, '2026-07-31 00:44:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3133, 8, '2026-07-31 00:44:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3134, 1, '2026-07-31 00:45:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3135, 5, '2026-07-31 00:45:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3136, 7, '2026-07-31 00:45:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3137, 8, '2026-07-31 00:45:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3138, 1, '2026-07-31 00:46:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3139, 5, '2026-07-31 00:46:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3140, 7, '2026-07-31 00:46:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3141, 8, '2026-07-31 00:46:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3142, 1, '2026-07-31 00:47:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3143, 5, '2026-07-31 00:47:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3144, 7, '2026-07-31 00:47:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3145, 8, '2026-07-31 00:47:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3146, 1, '2026-07-31 00:48:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3147, 5, '2026-07-31 00:48:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3148, 6, '2026-07-31 00:48:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3149, 7, '2026-07-31 00:48:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3150, 8, '2026-07-31 00:48:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3151, 1, '2026-07-31 00:49:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3152, 5, '2026-07-31 00:49:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3153, 6, '2026-07-31 00:49:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3154, 7, '2026-07-31 00:49:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3155, 8, '2026-07-31 00:49:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3156, 1, '2026-07-31 00:51:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3157, 5, '2026-07-31 00:51:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3158, 6, '2026-07-31 00:51:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3159, 7, '2026-07-31 00:51:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3160, 8, '2026-07-31 00:51:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3161, 1, '2026-07-31 00:52:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3162, 5, '2026-07-31 00:52:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3163, 6, '2026-07-31 00:52:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3164, 7, '2026-07-31 00:52:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3165, 8, '2026-07-31 00:52:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3166, 1, '2026-07-31 00:53:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3167, 5, '2026-07-31 00:53:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3168, 6, '2026-07-31 00:53:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3169, 7, '2026-07-31 00:53:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3170, 8, '2026-07-31 00:53:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3171, 1, '2026-07-31 00:54:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3172, 5, '2026-07-31 00:54:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3173, 6, '2026-07-31 00:54:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3174, 7, '2026-07-31 00:54:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3175, 8, '2026-07-31 00:54:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3176, 1, '2026-07-31 00:55:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3177, 5, '2026-07-31 00:55:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3178, 6, '2026-07-31 00:55:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3179, 7, '2026-07-31 00:55:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3180, 8, '2026-07-31 00:55:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3181, 1, '2026-07-31 00:56:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3182, 5, '2026-07-31 00:56:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3183, 6, '2026-07-31 00:56:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3184, 7, '2026-07-31 00:56:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3185, 8, '2026-07-31 00:56:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3186, 1, '2026-07-31 00:57:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3187, 5, '2026-07-31 00:57:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3188, 6, '2026-07-31 00:57:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3189, 7, '2026-07-31 00:57:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3190, 8, '2026-07-31 00:57:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3191, 1, '2026-07-31 00:58:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3192, 5, '2026-07-31 00:58:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3193, 6, '2026-07-31 00:58:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3194, 7, '2026-07-31 00:58:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3195, 8, '2026-07-31 00:58:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3196, 1, '2026-07-31 00:59:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3197, 5, '2026-07-31 00:59:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3198, 6, '2026-07-31 00:59:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3199, 7, '2026-07-31 00:59:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3200, 8, '2026-07-31 00:59:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3201, 1, '2026-07-31 01:00:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3202, 5, '2026-07-31 01:00:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3203, 6, '2026-07-31 01:00:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3204, 7, '2026-07-31 01:00:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3205, 8, '2026-07-31 01:00:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3206, 1, '2026-07-31 01:01:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3207, 5, '2026-07-31 01:01:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3208, 6, '2026-07-31 01:01:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3209, 7, '2026-07-31 01:01:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3210, 8, '2026-07-31 01:01:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3211, 1, '2026-07-31 01:02:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3212, 5, '2026-07-31 01:02:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3213, 6, '2026-07-31 01:02:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3214, 7, '2026-07-31 01:02:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3215, 8, '2026-07-31 01:02:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3216, 1, '2026-07-31 01:04:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3217, 5, '2026-07-31 01:04:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3218, 6, '2026-07-31 01:04:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3219, 7, '2026-07-31 01:04:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3220, 8, '2026-07-31 01:04:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3221, 1, '2026-07-31 01:05:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3222, 5, '2026-07-31 01:05:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3223, 6, '2026-07-31 01:05:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3224, 7, '2026-07-31 01:05:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3225, 8, '2026-07-31 01:05:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3226, 1, '2026-07-31 01:06:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3227, 5, '2026-07-31 01:06:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3228, 6, '2026-07-31 01:06:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3229, 7, '2026-07-31 01:06:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3230, 8, '2026-07-31 01:06:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3231, 1, '2026-07-31 01:07:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3232, 5, '2026-07-31 01:07:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3233, 6, '2026-07-31 01:07:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3234, 7, '2026-07-31 01:07:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3235, 8, '2026-07-31 01:07:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3236, 1, '2026-07-31 01:08:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3237, 5, '2026-07-31 01:08:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3238, 6, '2026-07-31 01:08:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3239, 7, '2026-07-31 01:08:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3240, 8, '2026-07-31 01:08:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3241, 1, '2026-07-31 01:09:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3242, 5, '2026-07-31 01:09:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3243, 6, '2026-07-31 01:09:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3244, 7, '2026-07-31 01:09:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3245, 8, '2026-07-31 01:09:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3246, 1, '2026-07-31 01:10:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3247, 5, '2026-07-31 01:10:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3248, 6, '2026-07-31 01:10:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3249, 7, '2026-07-31 01:10:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3250, 8, '2026-07-31 01:10:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3251, 1, '2026-07-31 01:11:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3252, 5, '2026-07-31 01:11:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3253, 6, '2026-07-31 01:11:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3254, 7, '2026-07-31 01:11:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3255, 8, '2026-07-31 01:11:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3256, 1, '2026-07-31 01:12:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3257, 5, '2026-07-31 01:12:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3258, 6, '2026-07-31 01:12:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3259, 7, '2026-07-31 01:12:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3260, 8, '2026-07-31 01:12:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3261, 1, '2026-07-31 01:13:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3262, 5, '2026-07-31 01:13:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3263, 6, '2026-07-31 01:13:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3264, 7, '2026-07-31 01:13:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3265, 8, '2026-07-31 01:13:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3266, 1, '2026-07-31 01:14:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3267, 5, '2026-07-31 01:14:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3268, 6, '2026-07-31 01:14:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3269, 7, '2026-07-31 01:14:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3270, 8, '2026-07-31 01:14:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3271, 1, '2026-07-31 01:15:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3272, 5, '2026-07-31 01:15:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3273, 6, '2026-07-31 01:15:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3274, 7, '2026-07-31 01:15:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3275, 8, '2026-07-31 01:15:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3276, 1, '2026-07-31 01:16:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3277, 5, '2026-07-31 01:16:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3278, 6, '2026-07-31 01:16:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3279, 7, '2026-07-31 01:16:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3280, 8, '2026-07-31 01:16:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3286, 1, '2026-07-31 01:17:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3287, 5, '2026-07-31 01:17:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3288, 6, '2026-07-31 01:17:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3289, 7, '2026-07-31 01:17:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3290, 8, '2026-07-31 01:17:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3296, 1, '2026-07-31 01:18:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3297, 5, '2026-07-31 01:18:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3298, 6, '2026-07-31 01:18:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3299, 7, '2026-07-31 01:18:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3300, 8, '2026-07-31 01:18:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3306, 1, '2026-07-31 01:19:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3307, 5, '2026-07-31 01:19:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3308, 6, '2026-07-31 01:19:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3309, 7, '2026-07-31 01:19:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3310, 8, '2026-07-31 01:19:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3316, 1, '2026-07-31 01:20:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3317, 5, '2026-07-31 01:20:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3318, 6, '2026-07-31 01:20:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3319, 7, '2026-07-31 01:20:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3320, 8, '2026-07-31 01:20:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3326, 1, '2026-07-31 01:21:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3327, 5, '2026-07-31 01:21:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3328, 6, '2026-07-31 01:21:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3329, 7, '2026-07-31 01:21:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3330, 8, '2026-07-31 01:21:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3336, 1, '2026-07-31 01:22:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3337, 5, '2026-07-31 01:22:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3338, 6, '2026-07-31 01:22:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3339, 7, '2026-07-31 01:22:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3340, 8, '2026-07-31 01:22:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3346, 1, '2026-07-31 01:23:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3347, 5, '2026-07-31 01:23:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3348, 6, '2026-07-31 01:23:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3349, 7, '2026-07-31 01:23:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3350, 8, '2026-07-31 01:23:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3356, 1, '2026-07-31 01:24:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3357, 5, '2026-07-31 01:24:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3358, 6, '2026-07-31 01:24:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3359, 7, '2026-07-31 01:24:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3360, 8, '2026-07-31 01:24:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3366, 1, '2026-07-31 01:25:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3367, 5, '2026-07-31 01:25:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3368, 6, '2026-07-31 01:25:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3369, 7, '2026-07-31 01:25:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3370, 8, '2026-07-31 01:25:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3376, 1, '2026-07-31 01:26:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3377, 5, '2026-07-31 01:26:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3378, 6, '2026-07-31 01:26:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3379, 7, '2026-07-31 01:26:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3380, 8, '2026-07-31 01:26:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3386, 1, '2026-07-31 01:27:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3387, 5, '2026-07-31 01:27:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3388, 6, '2026-07-31 01:27:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3389, 7, '2026-07-31 01:27:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3390, 8, '2026-07-31 01:27:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3396, 1, '2026-07-31 01:28:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3397, 5, '2026-07-31 01:28:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3398, 6, '2026-07-31 01:28:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3399, 7, '2026-07-31 01:28:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3400, 8, '2026-07-31 01:28:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3406, 1, '2026-07-31 01:29:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3407, 5, '2026-07-31 01:29:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3408, 6, '2026-07-31 01:29:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3409, 7, '2026-07-31 01:29:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3410, 8, '2026-07-31 01:29:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3416, 1, '2026-07-31 01:30:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3417, 5, '2026-07-31 01:30:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3418, 6, '2026-07-31 01:30:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3419, 7, '2026-07-31 01:30:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3420, 8, '2026-07-31 01:30:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3426, 1, '2026-07-31 01:31:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3427, 5, '2026-07-31 01:31:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3428, 6, '2026-07-31 01:31:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3429, 7, '2026-07-31 01:31:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3430, 8, '2026-07-31 01:31:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3436, 1, '2026-07-31 01:32:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3437, 5, '2026-07-31 01:32:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3438, 6, '2026-07-31 01:32:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3439, 7, '2026-07-31 01:32:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3440, 8, '2026-07-31 01:32:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3446, 1, '2026-07-31 01:33:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3447, 5, '2026-07-31 01:33:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3448, 6, '2026-07-31 01:33:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3449, 7, '2026-07-31 01:33:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3450, 8, '2026-07-31 01:33:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3456, 1, '2026-07-31 01:34:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3457, 5, '2026-07-31 01:34:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3458, 6, '2026-07-31 01:34:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3459, 7, '2026-07-31 01:34:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3460, 8, '2026-07-31 01:34:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3466, 1, '2026-07-31 01:36:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3467, 5, '2026-07-31 01:36:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3468, 6, '2026-07-31 01:36:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3469, 7, '2026-07-31 01:36:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3470, 8, '2026-07-31 01:36:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3471, 1, '2026-07-31 01:37:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3472, 5, '2026-07-31 01:37:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3473, 6, '2026-07-31 01:37:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3474, 7, '2026-07-31 01:37:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3475, 8, '2026-07-31 01:37:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3476, 1, '2026-07-31 01:38:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3477, 5, '2026-07-31 01:38:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3478, 6, '2026-07-31 01:38:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3479, 7, '2026-07-31 01:38:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3480, 8, '2026-07-31 01:38:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3481, 1, '2026-07-31 01:39:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3482, 5, '2026-07-31 01:39:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3483, 6, '2026-07-31 01:39:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3484, 7, '2026-07-31 01:39:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3485, 8, '2026-07-31 01:39:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3486, 1, '2026-07-31 01:40:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3487, 5, '2026-07-31 01:40:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3488, 6, '2026-07-31 01:40:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3489, 7, '2026-07-31 01:40:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3490, 8, '2026-07-31 01:40:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3491, 1, '2026-07-31 01:41:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3492, 5, '2026-07-31 01:41:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3493, 6, '2026-07-31 01:41:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3494, 7, '2026-07-31 01:41:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3495, 8, '2026-07-31 01:41:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3496, 1, '2026-07-31 01:42:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3497, 5, '2026-07-31 01:42:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3498, 6, '2026-07-31 01:42:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3499, 7, '2026-07-31 01:42:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3500, 8, '2026-07-31 01:42:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3501, 1, '2026-07-31 01:43:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3502, 5, '2026-07-31 01:43:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3503, 6, '2026-07-31 01:43:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3504, 7, '2026-07-31 01:43:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3505, 8, '2026-07-31 01:43:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3506, 1, '2026-07-31 01:44:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3507, 5, '2026-07-31 01:44:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3508, 6, '2026-07-31 01:44:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3509, 7, '2026-07-31 01:44:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3510, 8, '2026-07-31 01:44:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3511, 1, '2026-07-31 01:45:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3512, 5, '2026-07-31 01:45:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3513, 6, '2026-07-31 01:45:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3514, 7, '2026-07-31 01:45:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3515, 8, '2026-07-31 01:45:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3516, 1, '2026-07-31 01:46:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3517, 5, '2026-07-31 01:46:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3518, 6, '2026-07-31 01:46:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3519, 7, '2026-07-31 01:46:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3520, 8, '2026-07-31 01:46:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3521, 1, '2026-07-31 01:47:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3522, 5, '2026-07-31 01:47:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3523, 6, '2026-07-31 01:47:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3524, 7, '2026-07-31 01:47:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3525, 8, '2026-07-31 01:47:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3526, 1, '2026-07-31 01:48:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3527, 5, '2026-07-31 01:48:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3528, 6, '2026-07-31 01:48:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3529, 7, '2026-07-31 01:48:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3530, 8, '2026-07-31 01:48:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3531, 1, '2026-07-31 01:49:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3532, 5, '2026-07-31 01:49:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3533, 6, '2026-07-31 01:49:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3534, 7, '2026-07-31 01:49:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3535, 8, '2026-07-31 01:49:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3536, 1, '2026-07-31 01:50:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3537, 5, '2026-07-31 01:50:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3538, 6, '2026-07-31 01:50:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3539, 7, '2026-07-31 01:50:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3540, 8, '2026-07-31 01:50:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3541, 1, '2026-07-31 01:51:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3542, 5, '2026-07-31 01:51:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3543, 6, '2026-07-31 01:51:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3544, 7, '2026-07-31 01:51:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3545, 8, '2026-07-31 01:51:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3546, 1, '2026-07-31 01:52:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3547, 5, '2026-07-31 01:52:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3548, 6, '2026-07-31 01:52:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3549, 7, '2026-07-31 01:52:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3550, 8, '2026-07-31 01:52:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3551, 1, '2026-07-31 01:53:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3552, 5, '2026-07-31 01:53:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3553, 6, '2026-07-31 01:53:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3554, 7, '2026-07-31 01:53:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3555, 8, '2026-07-31 01:53:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3556, 1, '2026-07-31 01:54:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3557, 5, '2026-07-31 01:54:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3558, 6, '2026-07-31 01:54:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3559, 7, '2026-07-31 01:54:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3560, 8, '2026-07-31 01:54:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3561, 1, '2026-07-31 01:55:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3562, 5, '2026-07-31 01:55:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3563, 6, '2026-07-31 01:55:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3564, 7, '2026-07-31 01:55:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3565, 8, '2026-07-31 01:55:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3566, 1, '2026-07-31 01:56:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3567, 5, '2026-07-31 01:56:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3568, 6, '2026-07-31 01:56:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3569, 7, '2026-07-31 01:56:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3570, 8, '2026-07-31 01:56:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3571, 1, '2026-07-31 01:57:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3572, 5, '2026-07-31 01:57:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3573, 6, '2026-07-31 01:57:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3574, 7, '2026-07-31 01:57:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3575, 8, '2026-07-31 01:57:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3576, 1, '2026-07-31 01:58:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3577, 5, '2026-07-31 01:58:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3578, 6, '2026-07-31 01:58:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3579, 7, '2026-07-31 01:58:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3580, 8, '2026-07-31 01:58:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3581, 1, '2026-07-31 01:59:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3582, 5, '2026-07-31 01:59:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3583, 6, '2026-07-31 01:59:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3584, 7, '2026-07-31 01:59:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3585, 8, '2026-07-31 01:59:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3586, 1, '2026-07-31 02:00:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3587, 5, '2026-07-31 02:00:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3588, 6, '2026-07-31 02:00:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3589, 7, '2026-07-31 02:00:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3590, 8, '2026-07-31 02:00:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3591, 1, '2026-07-31 02:01:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3592, 5, '2026-07-31 02:01:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3593, 6, '2026-07-31 02:01:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3594, 7, '2026-07-31 02:01:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3595, 8, '2026-07-31 02:01:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3596, 1, '2026-07-31 02:02:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3597, 5, '2026-07-31 02:02:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3598, 6, '2026-07-31 02:02:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3599, 7, '2026-07-31 02:02:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3600, 8, '2026-07-31 02:02:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3601, 1, '2026-07-31 02:03:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3602, 5, '2026-07-31 02:03:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3603, 6, '2026-07-31 02:03:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3604, 7, '2026-07-31 02:03:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3605, 8, '2026-07-31 02:03:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3606, 1, '2026-07-31 02:04:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3607, 5, '2026-07-31 02:04:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3608, 6, '2026-07-31 02:04:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3609, 7, '2026-07-31 02:04:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3610, 8, '2026-07-31 02:04:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3611, 1, '2026-07-31 02:05:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3612, 5, '2026-07-31 02:05:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3613, 6, '2026-07-31 02:05:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3614, 7, '2026-07-31 02:05:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3615, 8, '2026-07-31 02:05:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3616, 1, '2026-07-31 02:06:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3617, 5, '2026-07-31 02:06:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3618, 6, '2026-07-31 02:06:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3619, 7, '2026-07-31 02:06:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3620, 8, '2026-07-31 02:06:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3621, 1, '2026-07-31 02:07:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3622, 5, '2026-07-31 02:07:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3623, 6, '2026-07-31 02:07:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3624, 7, '2026-07-31 02:07:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3625, 8, '2026-07-31 02:07:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3626, 1, '2026-07-31 02:08:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3627, 5, '2026-07-31 02:08:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3628, 6, '2026-07-31 02:08:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3629, 7, '2026-07-31 02:08:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3630, 8, '2026-07-31 02:08:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3631, 1, '2026-07-31 02:09:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3632, 5, '2026-07-31 02:09:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3633, 6, '2026-07-31 02:09:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3634, 7, '2026-07-31 02:09:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3635, 8, '2026-07-31 02:09:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3636, 1, '2026-07-31 02:10:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3637, 5, '2026-07-31 02:10:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3638, 6, '2026-07-31 02:10:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3639, 7, '2026-07-31 02:10:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3640, 8, '2026-07-31 02:10:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3641, 1, '2026-07-31 02:11:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3642, 5, '2026-07-31 02:11:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3643, 6, '2026-07-31 02:11:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3644, 7, '2026-07-31 02:11:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3645, 8, '2026-07-31 02:11:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3646, 6, '2026-07-31 14:13:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3647, 6, '2026-07-31 14:14:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3648, 6, '2026-07-31 14:15:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3649, 6, '2026-07-31 14:16:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3650, 8, '2026-07-31 14:16:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3651, 6, '2026-07-31 14:17:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3652, 8, '2026-07-31 14:17:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3653, 6, '2026-07-31 14:18:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3654, 6, '2026-07-31 14:19:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3655, 6, '2026-07-31 14:20:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3656, 6, '2026-07-31 14:21:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3657, 6, '2026-07-31 14:22:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3658, 6, '2026-07-31 14:23:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3659, 6, '2026-07-31 14:24:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3660, 6, '2026-07-31 14:25:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3661, 6, '2026-07-31 14:26:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3662, 6, '2026-07-31 14:27:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3663, 6, '2026-07-31 14:28:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3664, 6, '2026-07-31 14:29:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3665, 6, '2026-07-31 14:30:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3666, 6, '2026-07-31 14:31:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3667, 6, '2026-07-31 14:32:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3668, 6, '2026-07-31 14:33:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3669, 6, '2026-07-31 14:34:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3670, 6, '2026-07-31 14:35:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3671, 6, '2026-07-31 14:36:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3672, 6, '2026-07-31 14:37:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3673, 6, '2026-07-31 14:38:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3674, 6, '2026-07-31 14:39:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3675, 6, '2026-07-31 14:40:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3676, 6, '2026-07-31 14:41:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3677, 6, '2026-07-31 14:42:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3678, 6, '2026-07-31 14:43:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3679, 6, '2026-07-31 14:44:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3680, 6, '2026-07-31 14:45:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3681, 6, '2026-07-31 14:46:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3682, 6, '2026-07-31 14:47:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3683, 6, '2026-07-31 14:48:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3684, 6, '2026-07-31 14:49:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3685, 6, '2026-07-31 14:50:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3686, 6, '2026-07-31 14:51:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3687, 5, '2026-07-31 14:52:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3688, 6, '2026-07-31 14:52:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3689, 6, '2026-07-31 14:53:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3690, 6, '2026-07-31 14:54:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3691, 6, '2026-07-31 14:55:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3692, 6, '2026-07-31 14:56:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3693, 6, '2026-07-31 14:58:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3695, 6, '2026-07-31 14:59:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3697, 6, '2026-07-31 15:00:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3699, 6, '2026-07-31 15:01:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3701, 6, '2026-07-31 15:02:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3703, 6, '2026-07-31 15:03:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3705, 6, '2026-07-31 15:04:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3707, 6, '2026-07-31 15:05:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3709, 6, '2026-07-31 15:06:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3711, 6, '2026-07-31 15:07:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3713, 6, '2026-07-31 15:08:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3715, 6, '2026-07-31 15:09:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3717, 6, '2026-07-31 15:11:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3718, 6, '2026-07-31 15:12:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3719, 6, '2026-07-31 15:13:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3720, 6, '2026-07-31 15:14:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3721, 6, '2026-07-31 15:15:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3722, 6, '2026-07-31 15:16:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3723, 6, '2026-07-31 15:17:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3724, 6, '2026-07-31 15:18:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3725, 6, '2026-07-31 15:19:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3726, 6, '2026-07-31 15:20:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3727, 6, '2026-07-31 15:21:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3728, 6, '2026-07-31 15:22:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3729, 6, '2026-07-31 15:23:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3730, 6, '2026-07-31 15:24:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3731, 6, '2026-07-31 15:25:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3732, 6, '2026-07-31 15:26:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3733, 6, '2026-07-31 15:27:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3734, 6, '2026-07-31 15:28:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3735, 6, '2026-07-31 15:29:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3736, 6, '2026-07-31 15:30:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3737, 6, '2026-07-31 15:31:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3738, 6, '2026-07-31 15:32:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3739, 6, '2026-07-31 15:33:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3740, 6, '2026-07-31 15:34:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3741, 6, '2026-07-31 15:35:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3742, 6, '2026-07-31 15:36:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3743, 6, '2026-07-31 15:37:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3744, 6, '2026-07-31 15:38:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3745, 6, '2026-07-31 15:39:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3746, 6, '2026-07-31 15:40:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3747, 6, '2026-07-31 15:41:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3748, 6, '2026-07-31 15:42:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3749, 6, '2026-07-31 15:43:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3750, 6, '2026-07-31 15:44:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3751, 6, '2026-07-31 15:45:00', 2, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3752, 6, '2026-07-31 15:47:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3754, 6, '2026-07-31 15:48:00', 3, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3756, 6, '2026-07-31 15:49:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3758, 6, '2026-07-31 15:50:00', 4, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3759, 6, '2026-07-31 15:51:00', 5, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3761, 6, '2026-07-31 15:52:00', 5, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3762, 6, '2026-07-31 15:54:00', 6, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3763, 6, '2026-07-31 15:56:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3764, 6, '2026-07-31 15:57:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3765, 6, '2026-07-31 15:58:00', 7, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3766, 6, '2026-07-31 16:00:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3767, 6, '2026-07-31 16:01:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3768, 6, '2026-07-31 16:02:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3769, 6, '2026-07-31 16:03:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3770, 6, '2026-07-31 16:04:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3771, 6, '2026-07-31 16:05:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3772, 6, '2026-07-31 16:06:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3773, 6, '2026-07-31 16:07:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3774, 6, '2026-07-31 16:08:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3775, 6, '2026-07-31 16:09:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3776, 6, '2026-07-31 16:10:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3777, 6, '2026-07-31 16:11:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3778, 6, '2026-07-31 16:12:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3779, 6, '2026-07-31 16:13:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3780, 6, '2026-07-31 16:14:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3781, 6, '2026-07-31 16:15:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3782, 6, '2026-07-31 16:16:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3783, 6, '2026-07-31 16:17:00', 8, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3784, 6, '2026-07-31 16:18:00', 8, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3785, 6, '2026-07-31 16:19:00', 8, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3786, 6, '2026-07-31 16:20:00', 8, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3787, 6, '2026-07-31 16:21:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3788, 6, '2026-07-31 16:22:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3789, 6, '2026-07-31 16:23:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3790, 6, '2026-07-31 16:24:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3791, 6, '2026-07-31 16:25:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3792, 6, '2026-07-31 16:26:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3793, 6, '2026-07-31 16:27:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3794, 6, '2026-07-31 16:28:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3795, 6, '2026-07-31 16:29:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3796, 6, '2026-07-31 16:30:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3797, 6, '2026-07-31 16:31:00', 9, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3798, 6, '2026-07-31 16:33:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3799, 6, '2026-07-31 16:34:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3800, 6, '2026-07-31 16:35:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3801, 6, '2026-07-31 16:36:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3802, 6, '2026-07-31 16:37:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3803, 6, '2026-07-31 16:38:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3804, 6, '2026-07-31 16:39:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3805, 6, '2026-07-31 16:40:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3806, 6, '2026-07-31 16:41:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3807, 6, '2026-07-31 16:42:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3808, 6, '2026-07-31 16:43:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3809, 6, '2026-07-31 16:44:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3810, 6, '2026-07-31 16:45:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3811, 6, '2026-07-31 16:46:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3812, 6, '2026-07-31 16:47:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3813, 6, '2026-07-31 16:48:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3814, 6, '2026-07-31 16:49:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3815, 6, '2026-07-31 16:50:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3816, 6, '2026-07-31 16:51:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3817, 6, '2026-07-31 16:52:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3818, 6, '2026-07-31 16:53:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3819, 6, '2026-07-31 16:54:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3820, 6, '2026-07-31 16:55:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3821, 6, '2026-07-31 16:56:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3822, 6, '2026-07-31 16:57:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3823, 6, '2026-07-31 16:58:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3824, 6, '2026-07-31 16:59:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3825, 6, '2026-07-31 17:00:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3826, 6, '2026-07-31 17:01:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3827, 6, '2026-07-31 17:02:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3828, 6, '2026-07-31 17:03:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3829, 6, '2026-07-31 17:04:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3830, 6, '2026-07-31 17:05:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3831, 6, '2026-07-31 17:06:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3832, 6, '2026-07-31 17:07:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3833, 6, '2026-07-31 17:08:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3834, 6, '2026-07-31 17:09:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3835, 6, '2026-07-31 17:10:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3836, 6, '2026-07-31 17:11:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3837, 6, '2026-07-31 17:12:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3838, 6, '2026-07-31 17:13:00', 10, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3839, 6, '2026-07-31 17:14:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3840, 6, '2026-07-31 17:15:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3841, 6, '2026-07-31 17:16:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3842, 6, '2026-07-31 17:17:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3843, 6, '2026-07-31 17:18:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3844, 6, '2026-07-31 17:19:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3845, 6, '2026-07-31 17:20:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3846, 6, '2026-07-31 17:21:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3847, 6, '2026-07-31 17:22:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3848, 6, '2026-07-31 17:23:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3849, 6, '2026-07-31 17:24:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3850, 6, '2026-07-31 17:25:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3851, 6, '2026-07-31 17:26:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3852, 6, '2026-07-31 17:27:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3853, 6, '2026-07-31 17:28:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3854, 6, '2026-07-31 17:29:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3855, 6, '2026-07-31 17:30:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3856, 6, '2026-07-31 17:31:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3857, 6, '2026-07-31 17:32:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3858, 6, '2026-07-31 17:33:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3859, 6, '2026-07-31 17:34:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3860, 6, '2026-07-31 17:35:00', 11, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3861, 6, '2026-07-31 17:36:00', 12, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3862, 6, '2026-07-31 17:37:00', 12, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3863, 6, '2026-07-31 18:05:00', 12, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3864, 6, '2026-07-31 18:06:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3865, 6, '2026-07-31 18:07:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3866, 6, '2026-07-31 18:08:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3867, 6, '2026-07-31 18:15:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3868, 6, '2026-07-31 18:16:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3869, 6, '2026-07-31 18:17:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3870, 6, '2026-07-31 18:18:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3871, 6, '2026-07-31 18:19:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3872, 6, '2026-07-31 18:20:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3873, 6, '2026-07-31 18:21:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3874, 6, '2026-07-31 18:22:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3875, 6, '2026-07-31 18:23:00', 13, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3876, 6, '2026-07-31 18:24:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3877, 6, '2026-07-31 18:25:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3878, 6, '2026-07-31 18:26:00', 14, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3879, 6, '2026-07-31 18:27:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3880, 6, '2026-07-31 18:28:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3881, 6, '2026-07-31 18:29:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3882, 6, '2026-07-31 18:30:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3883, 6, '2026-07-31 18:31:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3884, 6, '2026-07-31 18:32:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3885, 6, '2026-07-31 18:33:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3886, 6, '2026-07-31 18:34:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3887, 6, '2026-07-31 18:35:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3888, 6, '2026-07-31 18:37:00', 15, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3889, 6, '2026-07-31 18:38:00', 17, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3890, 6, '2026-07-31 18:39:00', 17, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3891, 6, '2026-07-31 18:40:00', 17, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3892, 6, '2026-07-31 18:41:00', 17, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3893, 6, '2026-07-31 18:42:00', 17, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3894, 6, '2026-07-31 18:43:00', 17, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3895, 6, '2026-07-31 18:44:00', 17, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3896, 6, '2026-07-31 18:45:00', 17, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3897, 6, '2026-07-31 18:46:00', 17, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3898, 6, '2026-07-31 18:47:00', 17, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3899, 6, '2026-07-31 18:48:00', 17, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3900, 6, '2026-07-31 18:49:00', 17, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3901, 6, '2026-07-31 18:50:00', 17, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3902, 6, '2026-07-31 18:51:00', 17, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3903, 6, '2026-07-31 18:52:00', 17, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3904, 6, '2026-07-31 18:53:00', 17, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3905, 6, '2026-07-31 18:54:00', 17, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3906, 6, '2026-07-31 18:55:00', 17, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3907, 6, '2026-07-31 18:56:00', 17, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3908, 6, '2026-07-31 18:57:00', 17, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3909, 6, '2026-07-31 18:58:00', 17, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3910, 6, '2026-07-31 18:59:00', 17, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3911, 6, '2026-07-31 19:18:00', 17, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3912, 6, '2026-07-31 19:19:00', 18, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3913, 6, '2026-07-31 19:20:00', 18, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3914, 6, '2026-07-31 19:21:00', 18, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3915, 6, '2026-07-31 19:32:00', 18, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3916, 6, '2026-07-31 19:33:00', 19, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3917, 6, '2026-07-31 19:34:00', 19, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3918, 6, '2026-07-31 19:35:00', 19, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3919, 6, '2026-07-31 19:36:00', 19, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3920, 6, '2026-07-31 19:37:00', 19, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3921, 6, '2026-07-31 19:38:00', 19, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3922, 6, '2026-07-31 20:04:00', 20, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3923, 6, '2026-07-31 20:05:00', 20, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3924, 6, '2026-07-31 20:06:00', 20, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3925, 6, '2026-07-31 20:07:00', 20, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3926, 6, '2026-07-31 20:08:00', 20, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3927, 6, '2026-07-31 20:09:00', 20, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3928, 6, '2026-07-31 20:10:00', 20, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3929, 6, '2026-07-31 20:11:00', 20, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3930, 6, '2026-07-31 20:12:00', 20, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3931, 6, '2026-07-31 20:13:00', 20, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3932, 6, '2026-07-31 20:14:00', 20, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3933, 6, '2026-07-31 20:15:00', 20, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3934, 6, '2026-07-31 20:16:00', 20, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3935, 6, '2026-07-31 20:17:00', 20, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3936, 6, '2026-07-31 20:18:00', 20, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3937, 6, '2026-07-31 20:19:00', 20, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3938, 6, '2026-07-31 20:20:00', 20, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3939, 6, '2026-07-31 20:21:00', 20, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3940, 6, '2026-07-31 20:22:00', 20, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3941, 6, '2026-07-31 20:24:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3942, 6, '2026-07-31 20:25:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3943, 6, '2026-07-31 20:26:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3945, 6, '2026-07-31 20:27:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3947, 6, '2026-07-31 20:28:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3949, 6, '2026-07-31 20:29:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3951, 6, '2026-07-31 20:30:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3953, 6, '2026-07-31 20:31:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3955, 6, '2026-07-31 20:32:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3957, 6, '2026-07-31 20:33:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3959, 6, '2026-07-31 20:34:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3961, 6, '2026-07-31 20:35:00', 21, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3963, 6, '2026-07-31 20:37:00', 22, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3964, 6, '2026-07-31 20:38:00', 22, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3965, 6, '2026-07-31 20:39:00', 22, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3966, 6, '2026-07-31 20:41:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3967, 6, '2026-07-31 20:43:00', 24, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3968, 6, '2026-07-31 20:44:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3969, 6, '2026-07-31 20:45:00', 24, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3970, 6, '2026-07-31 20:46:00', 24, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3971, 6, '2026-07-31 20:47:00', 24, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3972, 6, '2026-07-31 20:48:00', 24, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3973, 6, '2026-07-31 20:49:00', 24, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3974, 6, '2026-07-31 20:50:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3976, 7, '2026-07-31 20:50:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3977, 6, '2026-07-31 20:51:00', 24, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3978, 7, '2026-07-31 20:51:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3980, 6, '2026-07-31 20:52:00', 24, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3982, 6, '2026-07-31 20:53:00', 24, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3984, 6, '2026-07-31 20:54:00', 24, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3986, 6, '2026-07-31 20:55:00', 24, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3988, 6, '2026-07-31 20:56:00', 24, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3990, 6, '2026-07-31 20:57:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3991, 6, '2026-07-31 20:58:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3992, 7, '2026-07-31 20:58:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3995, 6, '2026-07-31 20:59:00', 24, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3996, 7, '2026-07-31 20:59:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3998, 6, '2026-07-31 21:00:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (3999, 7, '2026-07-31 21:00:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4002, 6, '2026-07-31 21:01:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4003, 7, '2026-07-31 21:01:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4004, 8, '2026-07-31 21:01:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4007, 6, '2026-07-31 21:02:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4008, 7, '2026-07-31 21:02:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4009, 8, '2026-07-31 21:02:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4011, 6, '2026-07-31 21:03:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4013, 6, '2026-07-31 21:04:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4015, 6, '2026-07-31 21:05:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4017, 6, '2026-07-31 21:06:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4019, 6, '2026-07-31 21:07:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4021, 6, '2026-07-31 21:08:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4023, 6, '2026-07-31 21:09:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4025, 6, '2026-07-31 21:10:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4027, 6, '2026-07-31 21:11:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4029, 6, '2026-07-31 21:12:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4031, 6, '2026-07-31 21:13:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4033, 6, '2026-07-31 21:14:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4035, 6, '2026-07-31 21:15:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4037, 6, '2026-07-31 21:16:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4039, 6, '2026-07-31 21:17:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4041, 6, '2026-07-31 21:18:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4043, 6, '2026-07-31 21:19:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4045, 6, '2026-07-31 21:20:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4047, 6, '2026-07-31 21:21:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4049, 6, '2026-07-31 21:22:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4051, 6, '2026-07-31 21:23:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4053, 6, '2026-07-31 21:24:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4055, 6, '2026-07-31 21:25:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4057, 6, '2026-07-31 21:26:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4059, 6, '2026-07-31 21:27:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4061, 6, '2026-07-31 21:28:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4063, 6, '2026-07-31 21:29:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4065, 6, '2026-07-31 21:30:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4067, 6, '2026-07-31 21:31:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4069, 6, '2026-07-31 21:32:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4071, 6, '2026-07-31 21:33:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4073, 6, '2026-07-31 21:34:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4075, 6, '2026-07-31 21:35:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4077, 6, '2026-07-31 21:36:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4079, 6, '2026-07-31 21:37:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4081, 6, '2026-07-31 21:38:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4083, 6, '2026-07-31 21:39:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4085, 6, '2026-07-31 21:40:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4087, 6, '2026-07-31 21:41:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4089, 6, '2026-07-31 21:42:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4091, 6, '2026-07-31 21:43:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4093, 6, '2026-07-31 21:44:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4095, 6, '2026-07-31 21:45:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4097, 6, '2026-07-31 21:46:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4099, 6, '2026-07-31 21:47:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4101, 6, '2026-07-31 21:48:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4103, 6, '2026-07-31 21:49:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4105, 6, '2026-07-31 21:50:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4107, 6, '2026-07-31 21:51:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4109, 6, '2026-07-31 21:52:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4111, 6, '2026-07-31 21:53:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4113, 6, '2026-07-31 21:54:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4115, 6, '2026-07-31 21:55:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4117, 6, '2026-07-31 21:56:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4119, 6, '2026-07-31 21:57:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4121, 6, '2026-07-31 21:58:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4123, 6, '2026-07-31 21:59:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4125, 6, '2026-07-31 22:00:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4127, 6, '2026-07-31 22:01:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4129, 6, '2026-07-31 22:02:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4131, 6, '2026-07-31 22:03:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4132, 6, '2026-07-31 22:04:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4133, 6, '2026-07-31 22:05:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4134, 6, '2026-07-31 22:06:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4135, 6, '2026-07-31 22:07:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4136, 6, '2026-07-31 22:08:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4137, 6, '2026-07-31 22:09:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4138, 6, '2026-07-31 22:10:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4139, 6, '2026-07-31 22:11:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4140, 6, '2026-07-31 22:12:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4141, 6, '2026-07-31 22:13:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4142, 6, '2026-07-31 22:14:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4143, 6, '2026-07-31 22:15:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4145, 6, '2026-07-31 22:16:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4147, 6, '2026-07-31 22:17:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4148, 6, '2026-07-31 22:18:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4149, 6, '2026-07-31 22:19:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4151, 6, '2026-07-31 22:20:00', 25, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4153, 6, '2026-07-31 22:21:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4155, 5, '2026-07-31 22:22:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4156, 6, '2026-07-31 22:22:00', 25, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4159, 5, '2026-07-31 22:23:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4160, 6, '2026-07-31 22:23:00', 24, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4162, 6, '2026-07-31 22:24:00', 24, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4164, 5, '2026-07-31 22:25:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4165, 6, '2026-07-31 22:25:00', 24, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4168, 5, '2026-07-31 22:26:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4169, 6, '2026-07-31 22:26:00', 24, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4172, 5, '2026-07-31 22:27:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4173, 6, '2026-07-31 22:27:00', 24, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4175, 6, '2026-07-31 22:28:00', 24, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4177, 6, '2026-07-31 22:29:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4179, 6, '2026-07-31 22:30:00', 24, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4181, 6, '2026-07-31 22:31:00', 24, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4183, 6, '2026-07-31 22:32:00', 25, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4185, 6, '2026-07-31 22:33:00', 25, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4187, 6, '2026-07-31 22:34:00', 25, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4189, 6, '2026-07-31 22:35:00', 25, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4191, 6, '2026-07-31 22:36:00', 25, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4193, 6, '2026-07-31 22:37:00', 25, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4195, 6, '2026-07-31 22:38:00', 25, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4197, 6, '2026-07-31 22:39:00', 25, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4199, 6, '2026-07-31 22:40:00', 25, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4201, 6, '2026-07-31 22:41:00', 25, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4203, 6, '2026-07-31 22:42:00', 25, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4205, 6, '2026-07-31 22:43:00', 25, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4207, 6, '2026-07-31 22:44:00', 25, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4209, 6, '2026-07-31 22:45:00', 25, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4211, 6, '2026-07-31 22:46:00', 25, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4213, 6, '2026-07-31 22:47:00', 25, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4215, 6, '2026-07-31 22:48:00', 24, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4217, 6, '2026-07-31 22:49:00', 24, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4219, 6, '2026-07-31 22:50:00', 24, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4221, 6, '2026-07-31 22:51:00', 24, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4223, 6, '2026-07-31 22:52:00', 25, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4225, 6, '2026-07-31 22:53:00', 25, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4227, 6, '2026-07-31 22:54:00', 25, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4230, 6, '2026-07-31 22:55:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4233, 6, '2026-07-31 22:56:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4236, 6, '2026-07-31 22:57:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4239, 6, '2026-07-31 22:58:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4242, 6, '2026-07-31 22:59:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4245, 6, '2026-07-31 23:00:00', 25, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4248, 6, '2026-07-31 23:01:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4251, 6, '2026-07-31 23:02:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4254, 6, '2026-07-31 23:03:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4257, 6, '2026-07-31 23:04:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4260, 6, '2026-07-31 23:05:00', 25, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4263, 6, '2026-07-31 23:06:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4267, 6, '2026-07-31 23:07:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4271, 6, '2026-07-31 23:08:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4275, 6, '2026-07-31 23:09:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4279, 6, '2026-07-31 23:10:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4283, 6, '2026-07-31 23:11:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4287, 6, '2026-07-31 23:12:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4291, 6, '2026-07-31 23:13:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4295, 6, '2026-07-31 23:14:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4299, 6, '2026-07-31 23:15:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4303, 6, '2026-07-31 23:16:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4304, 6, '2026-07-31 23:17:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4305, 6, '2026-07-31 23:18:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4306, 6, '2026-07-31 23:19:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4307, 6, '2026-07-31 23:20:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4308, 6, '2026-07-31 23:21:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4309, 6, '2026-07-31 23:22:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4310, 6, '2026-07-31 23:23:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4311, 6, '2026-07-31 23:24:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4312, 6, '2026-07-31 23:25:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4313, 6, '2026-07-31 23:26:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4314, 6, '2026-07-31 23:27:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4315, 6, '2026-07-31 23:28:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4316, 6, '2026-07-31 23:29:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4317, 6, '2026-07-31 23:30:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4319, 6, '2026-07-31 23:31:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4321, 6, '2026-07-31 23:32:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4323, 6, '2026-07-31 23:33:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4325, 6, '2026-07-31 23:34:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4327, 6, '2026-07-31 23:35:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4329, 6, '2026-07-31 23:36:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4331, 6, '2026-07-31 23:37:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4333, 6, '2026-07-31 23:38:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4335, 6, '2026-07-31 23:39:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4337, 6, '2026-07-31 23:40:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4339, 6, '2026-07-31 23:41:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4341, 6, '2026-07-31 23:42:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4343, 6, '2026-07-31 23:43:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4345, 6, '2026-07-31 23:44:00', 23, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4346, 6, '2026-07-31 23:45:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4348, 6, '2026-07-31 23:46:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4350, 6, '2026-07-31 23:47:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4352, 6, '2026-07-31 23:48:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4354, 6, '2026-07-31 23:49:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4356, 6, '2026-07-31 23:50:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4358, 6, '2026-07-31 23:51:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4360, 6, '2026-07-31 23:52:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4362, 6, '2026-07-31 23:53:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4364, 6, '2026-07-31 23:54:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4366, 6, '2026-07-31 23:55:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4368, 6, '2026-07-31 23:56:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4370, 6, '2026-07-31 23:57:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4372, 6, '2026-07-31 23:58:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4374, 6, '2026-07-31 23:59:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4376, 6, '2026-08-01 00:00:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4378, 6, '2026-08-01 00:01:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4380, 6, '2026-08-01 00:02:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4382, 6, '2026-08-01 00:03:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4384, 6, '2026-08-01 00:04:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4385, 6, '2026-08-01 00:05:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4387, 6, '2026-08-01 00:06:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4389, 6, '2026-08-01 00:07:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4391, 6, '2026-08-01 00:08:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4393, 6, '2026-08-01 00:09:00', 25, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4395, 6, '2026-08-01 00:10:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4397, 6, '2026-08-01 00:11:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4399, 6, '2026-08-01 00:12:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4401, 6, '2026-08-01 00:13:00', 25, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4403, 6, '2026-08-01 00:14:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4405, 6, '2026-08-01 00:15:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4407, 6, '2026-08-01 00:16:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4409, 6, '2026-08-01 00:17:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4412, 6, '2026-08-01 00:18:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4415, 6, '2026-08-01 00:19:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4418, 6, '2026-08-01 00:20:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4421, 6, '2026-08-01 00:21:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4424, 6, '2026-08-01 00:22:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4427, 6, '2026-08-01 00:23:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4430, 6, '2026-08-01 00:24:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4433, 6, '2026-08-01 00:25:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4436, 6, '2026-08-01 00:26:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4439, 6, '2026-08-01 00:27:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4442, 6, '2026-08-01 00:28:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4445, 6, '2026-08-01 00:29:00', 26, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4447, 6, '2026-08-01 00:30:00', 27, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4450, 6, '2026-08-01 00:31:00', 27, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4453, 6, '2026-08-01 00:32:00', 27, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4456, 6, '2026-08-01 00:33:00', 27, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4459, 6, '2026-08-01 00:34:00', 27, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4463, 6, '2026-08-01 00:35:00', 27, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4467, 6, '2026-08-01 00:36:00', 27, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4471, 6, '2026-08-01 00:37:00', 27, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4475, 6, '2026-08-01 00:38:00', 27, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4478, 6, '2026-08-01 00:41:00', 30, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4479, 6, '2026-08-01 00:42:00', 30, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4480, 6, '2026-08-01 00:44:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4481, 6, '2026-08-01 00:45:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4482, 6, '2026-08-01 00:46:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4483, 6, '2026-08-01 00:47:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4484, 6, '2026-08-01 00:48:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4485, 6, '2026-08-01 00:49:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4486, 6, '2026-08-01 00:50:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4487, 6, '2026-08-01 00:51:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4488, 6, '2026-08-01 00:52:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4489, 6, '2026-08-01 00:53:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4490, 6, '2026-08-01 00:54:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4491, 6, '2026-08-01 00:55:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4492, 6, '2026-08-01 00:56:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4493, 6, '2026-08-01 00:57:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4494, 6, '2026-08-01 00:58:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4495, 6, '2026-08-01 00:59:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4496, 6, '2026-08-01 01:00:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4497, 6, '2026-08-01 01:01:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4498, 6, '2026-08-01 01:02:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4499, 6, '2026-08-01 01:03:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4500, 6, '2026-08-01 01:04:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4501, 6, '2026-08-01 01:05:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4502, 6, '2026-08-01 01:06:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4503, 6, '2026-08-01 01:07:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4504, 6, '2026-08-01 01:08:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4505, 6, '2026-08-01 01:09:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4506, 6, '2026-08-01 01:10:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4507, 6, '2026-08-01 01:11:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4508, 6, '2026-08-01 01:12:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4509, 6, '2026-08-01 01:13:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4510, 6, '2026-08-01 01:14:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4511, 6, '2026-08-01 01:15:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4512, 6, '2026-08-01 01:16:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4513, 6, '2026-08-01 01:17:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4514, 6, '2026-08-01 01:18:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4515, 6, '2026-08-01 01:19:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4516, 6, '2026-08-01 01:20:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4517, 6, '2026-08-01 01:21:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4518, 6, '2026-08-01 01:22:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4519, 6, '2026-08-01 01:23:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4520, 6, '2026-08-01 01:24:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4521, 6, '2026-08-01 01:25:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4522, 6, '2026-08-01 01:26:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4523, 6, '2026-08-01 01:27:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4524, 6, '2026-08-01 01:28:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4525, 6, '2026-08-01 01:29:00', 31, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4526, 6, '2026-08-01 01:30:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4528, 6, '2026-08-01 01:31:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4530, 6, '2026-08-01 01:32:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4532, 6, '2026-08-01 01:33:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4534, 6, '2026-08-01 01:34:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4536, 6, '2026-08-01 01:35:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4538, 6, '2026-08-01 01:36:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4540, 6, '2026-08-01 01:37:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4542, 6, '2026-08-01 01:38:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4544, 6, '2026-08-01 01:39:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4546, 6, '2026-08-01 01:40:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4548, 6, '2026-08-01 01:41:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4550, 6, '2026-08-01 01:42:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4552, 6, '2026-08-01 01:43:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4554, 6, '2026-08-01 01:44:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4556, 6, '2026-08-01 01:45:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4558, 6, '2026-08-01 01:46:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4560, 6, '2026-08-01 01:47:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4562, 6, '2026-08-01 01:48:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4564, 6, '2026-08-01 01:49:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4566, 6, '2026-08-01 01:50:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4568, 6, '2026-08-01 01:51:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4570, 6, '2026-08-01 01:52:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4572, 6, '2026-08-01 01:53:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4574, 6, '2026-08-01 01:54:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4576, 6, '2026-08-01 01:55:00', 32, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4578, 6, '2026-08-01 01:59:00', 33, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4579, 6, '2026-08-01 02:00:00', 33, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4580, 6, '2026-08-01 02:01:00', 34, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4581, 6, '2026-08-01 02:02:00', 34, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4582, 6, '2026-08-01 02:03:00', 34, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4583, 6, '2026-08-01 02:04:00', 34, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4584, 6, '2026-08-01 02:05:00', 33, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4585, 6, '2026-08-01 02:06:00', 33, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4586, 6, '2026-08-01 02:07:00', 34, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4587, 6, '2026-08-01 02:08:00', 34, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4588, 6, '2026-08-01 02:10:00', 35, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4589, 6, '2026-08-01 02:11:00', 34, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4590, 6, '2026-08-01 02:12:00', 35, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4591, 6, '2026-08-01 02:13:00', 35, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4592, 6, '2026-08-01 02:14:00', 35, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4593, 6, '2026-08-01 02:15:00', 35, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4594, 6, '2026-08-01 02:16:00', 35, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4595, 6, '2026-08-01 02:17:00', 35, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4596, 6, '2026-08-01 02:18:00', 35, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4597, 6, '2026-08-01 02:19:00', 35, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4598, 6, '2026-08-01 02:20:00', 35, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4599, 6, '2026-08-01 02:21:00', 35, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4600, 6, '2026-08-01 02:22:00', 35, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4601, 6, '2026-08-01 02:23:00', 35, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4602, 6, '2026-08-01 02:24:00', 35, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4603, 6, '2026-08-01 02:25:00', 35, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4604, 6, '2026-08-01 02:26:00', 35, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4605, 6, '2026-08-01 02:27:00', 35, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4606, 6, '2026-08-01 02:28:00', 35, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4607, 6, '2026-08-01 02:29:00', 35, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4608, 6, '2026-08-01 02:30:00', 35, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4609, 6, '2026-08-01 02:31:00', 35, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4610, 6, '2026-08-01 02:32:00', 35, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4612, 6, '2026-08-01 02:33:00', 35, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4613, 6, '2026-08-01 02:35:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4614, 6, '2026-08-01 02:36:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4615, 6, '2026-08-01 02:37:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4616, 6, '2026-08-01 02:38:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4617, 6, '2026-08-01 02:39:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4618, 6, '2026-08-01 02:40:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4620, 6, '2026-08-01 02:41:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4622, 6, '2026-08-01 02:42:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4624, 6, '2026-08-01 02:43:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4626, 6, '2026-08-01 02:44:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4628, 6, '2026-08-01 02:45:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4630, 6, '2026-08-01 02:46:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4632, 6, '2026-08-01 02:47:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4634, 6, '2026-08-01 02:48:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4636, 6, '2026-08-01 02:49:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4638, 6, '2026-08-01 02:50:00', 37, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4639, 6, '2026-08-01 02:51:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4640, 6, '2026-08-01 02:52:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4641, 6, '2026-08-01 02:53:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4642, 6, '2026-08-01 02:54:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4643, 6, '2026-08-01 02:55:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4644, 6, '2026-08-01 02:56:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4645, 6, '2026-08-01 02:57:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4646, 6, '2026-08-01 02:58:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4647, 6, '2026-08-01 02:59:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4648, 6, '2026-08-01 03:00:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4649, 6, '2026-08-01 03:01:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4650, 6, '2026-08-01 03:02:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4651, 6, '2026-08-01 03:03:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4652, 6, '2026-08-01 03:04:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4653, 6, '2026-08-01 03:05:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4654, 6, '2026-08-01 03:06:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4655, 6, '2026-08-01 03:07:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4656, 6, '2026-08-01 03:08:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4657, 6, '2026-08-01 03:09:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4658, 6, '2026-08-01 03:10:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4659, 6, '2026-08-01 03:11:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4660, 6, '2026-08-01 03:12:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4661, 6, '2026-08-01 03:13:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4662, 6, '2026-08-01 03:14:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4663, 6, '2026-08-01 03:15:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4664, 6, '2026-08-01 03:16:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4665, 6, '2026-08-01 03:17:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4666, 6, '2026-08-01 03:18:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4667, 6, '2026-08-01 03:19:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4668, 6, '2026-08-01 03:20:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4669, 6, '2026-08-01 03:21:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4670, 6, '2026-08-01 03:22:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4671, 6, '2026-08-01 03:23:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4672, 6, '2026-08-01 03:24:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4673, 6, '2026-08-01 03:25:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4674, 6, '2026-08-01 03:26:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4675, 6, '2026-08-01 03:27:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4676, 6, '2026-08-01 03:28:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4678, 6, '2026-08-01 03:29:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4680, 6, '2026-08-01 03:30:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4681, 6, '2026-08-01 03:34:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4682, 6, '2026-08-01 03:35:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4683, 6, '2026-08-01 03:36:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4684, 6, '2026-08-01 03:37:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4685, 6, '2026-08-01 03:38:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4686, 6, '2026-08-01 03:39:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4687, 6, '2026-08-01 03:40:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4688, 6, '2026-08-01 03:41:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4689, 6, '2026-08-01 03:42:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4690, 6, '2026-08-01 03:43:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4691, 6, '2026-08-01 03:44:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4692, 6, '2026-08-01 03:45:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4693, 6, '2026-08-01 03:46:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4694, 6, '2026-08-01 03:47:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4695, 6, '2026-08-01 03:48:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4696, 6, '2026-08-01 03:49:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4697, 6, '2026-08-01 03:50:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4698, 6, '2026-08-01 03:51:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4699, 6, '2026-08-01 03:52:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4700, 6, '2026-08-01 03:53:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4701, 6, '2026-08-01 03:54:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4702, 6, '2026-08-01 03:55:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4703, 6, '2026-08-01 03:56:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4704, 6, '2026-08-01 03:57:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4705, 6, '2026-08-01 03:58:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4706, 6, '2026-08-01 03:59:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4707, 6, '2026-08-01 04:00:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4708, 6, '2026-08-01 04:01:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4709, 6, '2026-08-01 04:02:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4710, 6, '2026-08-01 04:03:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4711, 6, '2026-08-01 04:04:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4712, 6, '2026-08-01 04:05:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4713, 6, '2026-08-01 04:06:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4714, 6, '2026-08-01 04:07:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4715, 6, '2026-08-01 04:08:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4716, 6, '2026-08-01 04:09:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4717, 6, '2026-08-01 04:10:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4718, 6, '2026-08-01 04:11:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4719, 6, '2026-08-01 04:12:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4720, 6, '2026-08-01 04:13:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4721, 6, '2026-08-01 04:14:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4722, 6, '2026-08-01 04:15:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4723, 6, '2026-08-01 04:16:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4724, 6, '2026-08-01 04:17:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4725, 6, '2026-08-01 04:18:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4726, 6, '2026-08-01 04:19:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4727, 6, '2026-08-01 04:20:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4728, 6, '2026-08-01 04:21:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4729, 6, '2026-08-01 04:22:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4730, 6, '2026-08-01 04:23:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4731, 6, '2026-08-01 04:24:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4732, 6, '2026-08-01 04:25:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4733, 6, '2026-08-01 04:26:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4734, 6, '2026-08-01 04:27:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4735, 6, '2026-08-01 04:28:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4736, 6, '2026-08-01 04:29:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4737, 6, '2026-08-01 04:30:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4738, 6, '2026-08-01 04:31:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4739, 6, '2026-08-01 04:32:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4740, 6, '2026-08-01 04:33:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4741, 6, '2026-08-01 04:34:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4742, 6, '2026-08-01 04:35:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4743, 6, '2026-08-01 04:36:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4744, 6, '2026-08-01 04:37:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4745, 6, '2026-08-01 04:38:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4746, 6, '2026-08-01 04:39:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4747, 6, '2026-08-01 04:40:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4748, 6, '2026-08-01 04:41:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4749, 6, '2026-08-01 04:42:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4750, 6, '2026-08-01 04:43:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4751, 6, '2026-08-01 04:44:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4752, 6, '2026-08-01 04:45:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4753, 6, '2026-08-01 04:46:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4754, 6, '2026-08-01 04:47:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4755, 6, '2026-08-01 04:48:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4756, 6, '2026-08-01 04:49:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4757, 6, '2026-08-01 04:50:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4758, 6, '2026-08-01 04:51:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4759, 6, '2026-08-01 04:52:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4760, 6, '2026-08-01 04:53:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4761, 6, '2026-08-01 04:54:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4762, 6, '2026-08-01 04:55:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4763, 6, '2026-08-01 04:56:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4764, 6, '2026-08-01 04:57:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4765, 6, '2026-08-01 04:58:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4766, 6, '2026-08-01 04:59:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4767, 6, '2026-08-01 05:00:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4768, 6, '2026-08-01 05:01:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4769, 6, '2026-08-01 05:02:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4770, 6, '2026-08-01 05:03:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4771, 6, '2026-08-01 05:04:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4772, 6, '2026-08-01 05:05:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4773, 6, '2026-08-01 05:06:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4774, 6, '2026-08-01 05:07:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4775, 6, '2026-08-01 05:08:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4776, 6, '2026-08-01 05:09:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4777, 6, '2026-08-01 05:10:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4778, 6, '2026-08-01 05:11:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4779, 6, '2026-08-01 05:12:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4780, 6, '2026-08-01 05:13:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4781, 6, '2026-08-01 05:14:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4782, 6, '2026-08-01 05:15:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4783, 6, '2026-08-01 05:16:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4784, 6, '2026-08-01 05:17:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4785, 6, '2026-08-01 05:18:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4786, 6, '2026-08-01 05:19:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4787, 6, '2026-08-01 05:20:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4788, 6, '2026-08-01 05:21:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4789, 6, '2026-08-01 05:22:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4790, 6, '2026-08-01 05:23:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4791, 6, '2026-08-01 05:24:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4792, 6, '2026-08-01 05:25:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4793, 6, '2026-08-01 05:26:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4794, 6, '2026-08-01 05:27:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4795, 6, '2026-08-01 05:28:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4796, 6, '2026-08-01 05:29:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4797, 6, '2026-08-01 05:30:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4798, 6, '2026-08-01 05:31:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4799, 6, '2026-08-01 05:32:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4800, 6, '2026-08-01 05:33:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4801, 6, '2026-08-01 05:34:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4802, 6, '2026-08-01 05:35:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4803, 6, '2026-08-01 05:36:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4804, 6, '2026-08-01 05:37:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4805, 6, '2026-08-01 05:38:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4806, 6, '2026-08-01 05:39:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4807, 6, '2026-08-01 05:40:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4808, 6, '2026-08-01 05:41:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4809, 6, '2026-08-01 05:42:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4810, 6, '2026-08-01 05:43:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4811, 6, '2026-08-01 05:44:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4812, 6, '2026-08-01 05:45:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4813, 6, '2026-08-01 05:46:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4814, 6, '2026-08-01 05:47:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4815, 6, '2026-08-01 05:48:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4816, 6, '2026-08-01 05:49:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4817, 6, '2026-08-01 05:50:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4818, 6, '2026-08-01 05:51:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4819, 6, '2026-08-01 05:52:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4820, 6, '2026-08-01 05:53:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4821, 6, '2026-08-01 05:54:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4822, 6, '2026-08-01 05:55:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4823, 6, '2026-08-01 05:56:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4824, 6, '2026-08-01 05:57:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4825, 6, '2026-08-01 05:58:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4826, 6, '2026-08-01 05:59:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4827, 6, '2026-08-01 06:00:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4828, 6, '2026-08-01 06:01:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4829, 6, '2026-08-01 06:02:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4830, 6, '2026-08-01 06:03:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4831, 6, '2026-08-01 06:04:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4832, 6, '2026-08-01 06:05:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4833, 6, '2026-08-01 06:06:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4834, 6, '2026-08-01 06:07:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4835, 6, '2026-08-01 06:08:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4836, 6, '2026-08-01 06:09:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4837, 6, '2026-08-01 06:10:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4838, 6, '2026-08-01 06:11:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4839, 6, '2026-08-01 06:12:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4840, 6, '2026-08-01 06:13:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4841, 6, '2026-08-01 06:14:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4842, 6, '2026-08-01 06:15:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4843, 6, '2026-08-01 06:16:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4844, 6, '2026-08-01 06:17:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4845, 6, '2026-08-01 06:18:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4846, 6, '2026-08-01 06:19:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4847, 6, '2026-08-01 06:20:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4848, 6, '2026-08-01 06:21:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4849, 6, '2026-08-01 06:22:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4850, 6, '2026-08-01 06:23:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4851, 6, '2026-08-01 06:24:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4852, 6, '2026-08-01 06:25:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4853, 6, '2026-08-01 06:26:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4854, 6, '2026-08-01 06:27:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4855, 6, '2026-08-01 06:28:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4856, 6, '2026-08-01 06:29:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4857, 6, '2026-08-01 06:30:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4858, 6, '2026-08-01 06:31:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4859, 6, '2026-08-01 06:32:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4860, 6, '2026-08-01 06:33:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4861, 6, '2026-08-01 06:34:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4862, 6, '2026-08-01 06:35:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4863, 6, '2026-08-01 06:36:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4864, 6, '2026-08-01 06:37:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4865, 6, '2026-08-01 06:38:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4866, 6, '2026-08-01 06:39:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4867, 6, '2026-08-01 06:40:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4868, 6, '2026-08-01 06:41:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4869, 6, '2026-08-01 06:42:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4870, 6, '2026-08-01 06:43:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4871, 6, '2026-08-01 06:44:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4872, 6, '2026-08-01 06:45:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4873, 6, '2026-08-01 06:46:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4874, 6, '2026-08-01 06:47:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4875, 6, '2026-08-01 06:48:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4876, 6, '2026-08-01 06:49:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4877, 6, '2026-08-01 06:50:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4878, 6, '2026-08-01 06:51:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4879, 6, '2026-08-01 06:52:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4880, 6, '2026-08-01 06:53:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4881, 6, '2026-08-01 06:54:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4882, 6, '2026-08-01 06:55:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4883, 6, '2026-08-01 06:56:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4884, 6, '2026-08-01 06:57:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4885, 6, '2026-08-01 06:58:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4886, 6, '2026-08-01 06:59:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4887, 6, '2026-08-01 07:00:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4888, 6, '2026-08-01 07:01:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4889, 6, '2026-08-01 07:02:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4890, 6, '2026-08-01 07:03:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4891, 6, '2026-08-01 07:04:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4892, 6, '2026-08-01 07:05:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4893, 6, '2026-08-01 07:06:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4894, 6, '2026-08-01 07:07:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4895, 6, '2026-08-01 07:08:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4896, 6, '2026-08-01 07:09:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4897, 6, '2026-08-01 07:10:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4898, 6, '2026-08-01 07:11:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4899, 6, '2026-08-01 07:12:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4900, 6, '2026-08-01 07:13:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4901, 6, '2026-08-01 07:14:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4902, 6, '2026-08-01 07:15:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4903, 6, '2026-08-01 07:16:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4904, 6, '2026-08-01 07:17:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4905, 6, '2026-08-01 07:18:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4906, 6, '2026-08-01 07:19:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4907, 6, '2026-08-01 07:20:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4908, 6, '2026-08-01 07:21:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4909, 6, '2026-08-01 07:22:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4910, 6, '2026-08-01 07:23:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4911, 6, '2026-08-01 07:24:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4912, 6, '2026-08-01 07:25:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4913, 6, '2026-08-01 07:26:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4914, 6, '2026-08-01 07:27:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4915, 6, '2026-08-01 07:28:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4916, 6, '2026-08-01 07:29:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4917, 6, '2026-08-01 07:30:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4918, 6, '2026-08-01 07:31:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4919, 6, '2026-08-01 07:32:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4920, 6, '2026-08-01 07:33:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4921, 6, '2026-08-01 07:34:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4922, 6, '2026-08-01 07:35:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4923, 6, '2026-08-01 07:36:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4924, 6, '2026-08-01 07:37:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4925, 6, '2026-08-01 07:38:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4926, 6, '2026-08-01 07:39:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4927, 6, '2026-08-01 07:40:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4928, 6, '2026-08-01 07:41:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4929, 6, '2026-08-01 07:42:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4930, 6, '2026-08-01 07:43:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4931, 6, '2026-08-01 07:44:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4932, 6, '2026-08-01 07:45:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4933, 6, '2026-08-01 07:46:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4934, 6, '2026-08-01 07:47:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4935, 6, '2026-08-01 07:48:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4936, 6, '2026-08-01 07:49:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4937, 6, '2026-08-01 07:50:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4938, 6, '2026-08-01 07:51:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4939, 6, '2026-08-01 07:52:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4940, 6, '2026-08-01 07:53:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4941, 6, '2026-08-01 07:54:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4942, 6, '2026-08-01 07:55:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4943, 6, '2026-08-01 07:56:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4944, 6, '2026-08-01 07:57:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4945, 6, '2026-08-01 07:58:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4946, 6, '2026-08-01 07:59:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4947, 6, '2026-08-01 08:00:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4948, 6, '2026-08-01 08:01:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4949, 6, '2026-08-01 08:02:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4950, 6, '2026-08-01 08:03:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4951, 6, '2026-08-01 08:04:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4952, 6, '2026-08-01 08:05:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4953, 6, '2026-08-01 08:06:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4954, 6, '2026-08-01 08:07:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4955, 6, '2026-08-01 08:08:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4956, 6, '2026-08-01 08:09:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4957, 6, '2026-08-01 08:10:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4958, 6, '2026-08-01 08:11:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4959, 6, '2026-08-01 08:12:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4960, 6, '2026-08-01 08:13:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4961, 6, '2026-08-01 08:14:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4962, 6, '2026-08-01 08:15:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4963, 6, '2026-08-01 08:16:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4964, 6, '2026-08-01 08:17:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4965, 6, '2026-08-01 08:18:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4966, 6, '2026-08-01 08:19:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4967, 6, '2026-08-01 08:20:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4968, 6, '2026-08-01 08:21:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4969, 6, '2026-08-01 08:22:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4970, 6, '2026-08-01 08:23:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4971, 6, '2026-08-01 08:24:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4972, 6, '2026-08-01 08:25:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4973, 6, '2026-08-01 08:26:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4974, 6, '2026-08-01 08:27:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4975, 6, '2026-08-01 08:28:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4976, 6, '2026-08-01 08:29:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4977, 6, '2026-08-01 08:30:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4978, 6, '2026-08-01 08:31:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4979, 6, '2026-08-01 08:32:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4980, 6, '2026-08-01 08:33:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4981, 6, '2026-08-01 08:34:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4982, 6, '2026-08-01 08:35:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4983, 6, '2026-08-01 08:36:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4984, 6, '2026-08-01 08:37:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4985, 6, '2026-08-01 08:38:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4986, 6, '2026-08-01 08:39:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4987, 6, '2026-08-01 08:40:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4988, 6, '2026-08-01 08:41:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4989, 6, '2026-08-01 08:42:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4990, 6, '2026-08-01 08:43:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4991, 6, '2026-08-01 08:44:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4992, 6, '2026-08-01 08:45:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4993, 6, '2026-08-01 08:46:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4994, 6, '2026-08-01 08:47:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4995, 6, '2026-08-01 08:48:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4996, 6, '2026-08-01 08:49:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4997, 6, '2026-08-01 08:50:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4998, 6, '2026-08-01 08:51:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (4999, 6, '2026-08-01 08:52:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5000, 6, '2026-08-01 08:53:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5001, 6, '2026-08-01 08:54:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5002, 6, '2026-08-01 08:55:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5003, 6, '2026-08-01 08:56:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5004, 6, '2026-08-01 08:57:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5005, 6, '2026-08-01 08:58:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5006, 6, '2026-08-01 08:59:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5007, 6, '2026-08-01 09:00:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5008, 6, '2026-08-01 09:01:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5009, 6, '2026-08-01 09:02:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5010, 6, '2026-08-01 09:03:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5011, 6, '2026-08-01 09:04:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5012, 6, '2026-08-01 09:05:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5013, 6, '2026-08-01 09:06:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5014, 6, '2026-08-01 09:07:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5015, 6, '2026-08-01 09:08:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5016, 6, '2026-08-01 09:09:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5017, 6, '2026-08-01 09:10:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5018, 6, '2026-08-01 09:11:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5019, 6, '2026-08-01 09:12:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5020, 6, '2026-08-01 09:13:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5021, 6, '2026-08-01 09:14:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5022, 6, '2026-08-01 09:15:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5023, 6, '2026-08-01 09:16:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5024, 6, '2026-08-01 09:17:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5025, 6, '2026-08-01 09:18:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5026, 6, '2026-08-01 09:19:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5027, 6, '2026-08-01 09:20:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5028, 6, '2026-08-01 09:21:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5029, 6, '2026-08-01 09:22:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5030, 6, '2026-08-01 09:23:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5031, 6, '2026-08-01 09:24:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5032, 6, '2026-08-01 09:25:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5033, 6, '2026-08-01 09:26:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5034, 6, '2026-08-01 09:27:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5035, 6, '2026-08-01 09:28:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5036, 6, '2026-08-01 09:29:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5037, 6, '2026-08-01 09:30:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5038, 6, '2026-08-01 09:31:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5039, 6, '2026-08-01 09:32:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5040, 6, '2026-08-01 09:33:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5041, 6, '2026-08-01 09:34:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5042, 6, '2026-08-01 09:35:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5043, 6, '2026-08-01 09:36:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5044, 6, '2026-08-01 09:37:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5045, 6, '2026-08-01 09:38:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5046, 6, '2026-08-01 09:39:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5047, 6, '2026-08-01 09:40:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5048, 6, '2026-08-01 09:41:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5049, 6, '2026-08-01 09:42:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5050, 6, '2026-08-01 09:43:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5051, 6, '2026-08-01 09:44:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5052, 6, '2026-08-01 09:45:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5053, 6, '2026-08-01 09:46:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5054, 6, '2026-08-01 09:47:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5055, 6, '2026-08-01 09:48:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5056, 6, '2026-08-01 09:49:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5057, 6, '2026-08-01 09:50:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5058, 6, '2026-08-01 09:51:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5059, 6, '2026-08-01 09:52:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5060, 6, '2026-08-01 09:53:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5061, 6, '2026-08-01 09:54:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5062, 6, '2026-08-01 09:55:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5063, 6, '2026-08-01 09:56:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5064, 6, '2026-08-01 09:57:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5065, 6, '2026-08-01 09:58:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5066, 6, '2026-08-01 09:59:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5067, 6, '2026-08-01 10:00:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5068, 6, '2026-08-01 10:01:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5069, 6, '2026-08-01 10:02:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5070, 6, '2026-08-01 10:03:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5071, 6, '2026-08-01 10:04:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5072, 6, '2026-08-01 10:05:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5073, 6, '2026-08-01 10:06:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5074, 6, '2026-08-01 10:07:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5075, 6, '2026-08-01 10:08:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5076, 6, '2026-08-01 10:09:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5077, 6, '2026-08-01 10:10:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5078, 6, '2026-08-01 10:11:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5079, 6, '2026-08-01 10:12:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5080, 6, '2026-08-01 10:13:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5081, 6, '2026-08-01 10:14:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5082, 6, '2026-08-01 10:15:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5083, 6, '2026-08-01 10:16:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5084, 6, '2026-08-01 10:17:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5085, 6, '2026-08-01 10:18:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5086, 6, '2026-08-01 10:19:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5087, 6, '2026-08-01 10:20:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5088, 6, '2026-08-01 10:21:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5089, 6, '2026-08-01 10:22:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5090, 6, '2026-08-01 10:23:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5091, 6, '2026-08-01 10:24:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5092, 6, '2026-08-01 10:25:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5093, 6, '2026-08-01 10:26:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5094, 6, '2026-08-01 10:27:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5095, 6, '2026-08-01 10:28:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5096, 6, '2026-08-01 10:29:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5097, 6, '2026-08-01 10:30:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5098, 6, '2026-08-01 10:31:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5099, 6, '2026-08-01 10:32:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5100, 6, '2026-08-01 10:33:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5101, 6, '2026-08-01 10:34:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5102, 6, '2026-08-01 10:35:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5103, 6, '2026-08-01 10:36:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5104, 6, '2026-08-01 10:37:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5105, 6, '2026-08-01 10:38:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5106, 6, '2026-08-01 10:39:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5107, 6, '2026-08-01 10:40:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5108, 6, '2026-08-01 10:41:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5109, 6, '2026-08-01 10:42:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5110, 6, '2026-08-01 10:43:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5111, 6, '2026-08-01 10:44:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5112, 6, '2026-08-01 10:45:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5113, 6, '2026-08-01 10:46:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5114, 6, '2026-08-01 10:47:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5115, 6, '2026-08-01 10:48:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5116, 6, '2026-08-01 10:49:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5117, 6, '2026-08-01 10:50:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5118, 6, '2026-08-01 10:51:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5119, 6, '2026-08-01 10:52:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5120, 6, '2026-08-01 10:53:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5121, 6, '2026-08-01 10:54:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5122, 6, '2026-08-01 10:55:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5123, 6, '2026-08-01 10:56:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5124, 6, '2026-08-01 10:57:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5125, 6, '2026-08-01 10:58:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5126, 6, '2026-08-01 10:59:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5127, 6, '2026-08-01 11:00:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5128, 6, '2026-08-01 11:01:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5129, 6, '2026-08-01 11:02:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5130, 6, '2026-08-01 11:03:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5131, 6, '2026-08-01 11:04:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5132, 6, '2026-08-01 11:05:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5133, 6, '2026-08-01 11:06:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5134, 6, '2026-08-01 11:07:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5135, 6, '2026-08-01 11:08:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5136, 6, '2026-08-01 11:09:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5137, 6, '2026-08-01 11:10:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5138, 6, '2026-08-01 11:11:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5139, 6, '2026-08-01 11:12:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5140, 6, '2026-08-01 11:13:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5141, 6, '2026-08-01 11:14:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5142, 6, '2026-08-01 11:15:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5143, 6, '2026-08-01 11:16:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5144, 6, '2026-08-01 11:17:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5145, 6, '2026-08-01 11:18:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5146, 6, '2026-08-01 11:19:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5147, 6, '2026-08-01 11:20:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5148, 6, '2026-08-01 11:21:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5149, 6, '2026-08-01 11:22:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5150, 6, '2026-08-01 11:23:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5151, 6, '2026-08-01 11:24:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5152, 6, '2026-08-01 11:25:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5153, 6, '2026-08-01 11:26:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5154, 6, '2026-08-01 11:27:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5155, 6, '2026-08-01 11:28:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5156, 6, '2026-08-01 11:29:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5157, 6, '2026-08-01 11:30:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5158, 6, '2026-08-01 11:31:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5159, 6, '2026-08-01 11:32:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5160, 6, '2026-08-01 11:33:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5161, 6, '2026-08-01 11:34:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5162, 6, '2026-08-01 11:35:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5163, 6, '2026-08-01 11:36:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5164, 6, '2026-08-01 11:37:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5165, 6, '2026-08-01 11:38:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5166, 6, '2026-08-01 11:39:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5167, 6, '2026-08-01 11:40:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5168, 6, '2026-08-01 11:41:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5169, 6, '2026-08-01 11:42:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5170, 6, '2026-08-01 11:43:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5171, 6, '2026-08-01 11:44:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5172, 6, '2026-08-01 11:45:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5173, 6, '2026-08-01 11:46:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5174, 6, '2026-08-01 11:47:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5175, 6, '2026-08-01 11:48:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5176, 6, '2026-08-01 11:49:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5177, 6, '2026-08-01 11:50:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5178, 6, '2026-08-01 11:51:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5179, 6, '2026-08-01 11:52:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5180, 6, '2026-08-01 11:53:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5181, 6, '2026-08-01 11:54:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5182, 6, '2026-08-01 11:55:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5183, 6, '2026-08-01 11:56:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5184, 6, '2026-08-01 11:57:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5185, 6, '2026-08-01 11:58:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5186, 6, '2026-08-01 11:59:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5187, 6, '2026-08-01 12:00:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5188, 6, '2026-08-01 12:01:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5189, 6, '2026-08-01 12:02:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5190, 6, '2026-08-01 12:03:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5191, 6, '2026-08-01 12:04:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5192, 6, '2026-08-01 12:05:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5193, 6, '2026-08-01 12:06:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5194, 6, '2026-08-01 12:07:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5195, 6, '2026-08-01 12:08:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5196, 6, '2026-08-01 12:09:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5197, 6, '2026-08-01 12:10:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5198, 6, '2026-08-01 12:11:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5199, 6, '2026-08-01 12:12:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5200, 6, '2026-08-01 12:13:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5201, 6, '2026-08-01 12:14:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5202, 6, '2026-08-01 12:15:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5203, 6, '2026-08-01 12:16:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5204, 6, '2026-08-01 12:17:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5205, 6, '2026-08-01 12:18:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5206, 6, '2026-08-01 12:19:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5207, 6, '2026-08-01 12:20:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5208, 6, '2026-08-01 12:21:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5209, 6, '2026-08-01 12:22:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5210, 6, '2026-08-01 12:23:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5211, 6, '2026-08-01 12:24:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5212, 6, '2026-08-01 12:25:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5213, 6, '2026-08-01 12:26:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5214, 6, '2026-08-01 12:27:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5215, 6, '2026-08-01 12:28:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5216, 6, '2026-08-01 12:29:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5217, 6, '2026-08-01 12:30:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5218, 6, '2026-08-01 12:31:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5219, 6, '2026-08-01 12:32:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5220, 6, '2026-08-01 12:33:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5221, 6, '2026-08-01 12:34:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5222, 6, '2026-08-01 12:35:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5223, 6, '2026-08-01 12:36:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5224, 6, '2026-08-01 12:37:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5225, 6, '2026-08-01 12:38:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5226, 6, '2026-08-01 12:39:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5227, 6, '2026-08-01 12:40:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5228, 6, '2026-08-01 12:41:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5229, 6, '2026-08-01 12:42:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5230, 6, '2026-08-01 12:43:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5231, 6, '2026-08-01 12:44:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5232, 6, '2026-08-01 12:45:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5233, 6, '2026-08-01 12:46:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5234, 6, '2026-08-01 12:47:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5235, 6, '2026-08-01 12:48:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5236, 6, '2026-08-01 12:49:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5237, 6, '2026-08-01 12:50:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5238, 6, '2026-08-01 12:51:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5239, 6, '2026-08-01 12:52:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5240, 6, '2026-08-01 12:53:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5241, 6, '2026-08-01 12:54:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5242, 6, '2026-08-01 12:55:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5243, 6, '2026-08-01 12:56:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5244, 6, '2026-08-01 12:57:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5245, 6, '2026-08-01 12:58:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5246, 6, '2026-08-01 12:59:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5247, 6, '2026-08-01 13:00:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5248, 6, '2026-08-01 13:01:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5249, 6, '2026-08-01 13:02:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5250, 6, '2026-08-01 13:03:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5251, 6, '2026-08-01 13:04:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5252, 6, '2026-08-01 13:05:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5253, 6, '2026-08-01 13:06:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5254, 6, '2026-08-01 13:07:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5255, 6, '2026-08-01 13:08:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5256, 6, '2026-08-01 13:09:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5257, 6, '2026-08-01 13:10:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5258, 6, '2026-08-01 13:11:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5259, 6, '2026-08-01 13:12:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5260, 6, '2026-08-01 13:13:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5261, 6, '2026-08-01 13:14:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5262, 6, '2026-08-01 13:15:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5263, 6, '2026-08-01 13:16:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5264, 6, '2026-08-01 13:17:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5265, 6, '2026-08-01 13:18:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5266, 6, '2026-08-01 13:19:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5267, 6, '2026-08-01 13:20:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5268, 6, '2026-08-01 13:21:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5269, 6, '2026-08-01 13:22:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5270, 6, '2026-08-01 13:23:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5271, 6, '2026-08-01 13:24:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5272, 6, '2026-08-01 13:25:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5273, 6, '2026-08-01 13:26:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5274, 6, '2026-08-01 13:27:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5275, 6, '2026-08-01 13:28:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5276, 6, '2026-08-01 13:29:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5277, 6, '2026-08-01 13:30:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5278, 6, '2026-08-01 13:31:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5279, 6, '2026-08-01 13:32:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5280, 6, '2026-08-01 13:33:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5281, 6, '2026-08-01 13:34:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5282, 6, '2026-08-01 13:35:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5283, 6, '2026-08-01 13:36:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5284, 6, '2026-08-01 13:37:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5285, 6, '2026-08-01 13:38:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5286, 6, '2026-08-01 13:39:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5287, 6, '2026-08-01 13:40:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5288, 6, '2026-08-01 13:41:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5289, 6, '2026-08-01 13:42:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5290, 6, '2026-08-01 13:43:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5291, 6, '2026-08-01 13:44:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5292, 6, '2026-08-01 13:45:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5293, 6, '2026-08-01 13:46:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5294, 6, '2026-08-01 13:47:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5295, 6, '2026-08-01 13:48:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5296, 6, '2026-08-01 13:49:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5297, 6, '2026-08-01 13:50:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5298, 6, '2026-08-01 13:51:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5299, 6, '2026-08-01 13:52:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5300, 6, '2026-08-01 13:53:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5301, 6, '2026-08-01 13:54:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5302, 6, '2026-08-01 13:55:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5303, 6, '2026-08-01 13:56:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5304, 6, '2026-08-01 13:57:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5305, 6, '2026-08-01 13:58:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5306, 6, '2026-08-01 13:59:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5307, 6, '2026-08-01 14:00:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5308, 6, '2026-08-01 14:01:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5309, 6, '2026-08-01 14:02:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5310, 6, '2026-08-01 14:03:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5311, 6, '2026-08-01 14:04:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5312, 6, '2026-08-01 14:05:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5313, 6, '2026-08-01 14:06:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5314, 6, '2026-08-01 14:07:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5315, 6, '2026-08-01 14:08:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5316, 6, '2026-08-01 14:09:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5317, 6, '2026-08-01 14:10:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5318, 6, '2026-08-01 14:11:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5319, 6, '2026-08-01 14:12:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5320, 6, '2026-08-01 14:13:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5321, 6, '2026-08-01 14:14:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5322, 6, '2026-08-01 14:15:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5323, 6, '2026-08-01 14:16:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5324, 6, '2026-08-01 14:17:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5325, 6, '2026-08-01 14:18:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5326, 6, '2026-08-01 14:19:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5327, 6, '2026-08-01 14:20:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5328, 6, '2026-08-01 14:21:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5329, 6, '2026-08-01 14:22:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5330, 6, '2026-08-01 14:23:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5331, 6, '2026-08-01 14:24:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5332, 6, '2026-08-01 14:25:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5333, 6, '2026-08-01 14:26:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5334, 6, '2026-08-01 14:27:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5335, 6, '2026-08-01 14:28:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5336, 6, '2026-08-01 14:29:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5337, 6, '2026-08-01 14:30:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5338, 6, '2026-08-01 14:31:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5339, 6, '2026-08-01 14:32:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5340, 6, '2026-08-01 14:33:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5341, 6, '2026-08-01 14:34:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5342, 6, '2026-08-01 14:35:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5343, 6, '2026-08-01 14:36:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5344, 6, '2026-08-01 14:37:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5345, 6, '2026-08-01 14:38:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5346, 6, '2026-08-01 14:39:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5347, 6, '2026-08-01 14:40:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5348, 6, '2026-08-01 14:41:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5349, 6, '2026-08-01 14:42:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5350, 6, '2026-08-01 14:43:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5351, 6, '2026-08-01 14:44:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5352, 6, '2026-08-01 14:45:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5353, 6, '2026-08-01 14:46:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5354, 6, '2026-08-01 14:47:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5355, 6, '2026-08-01 14:48:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5356, 6, '2026-08-01 14:49:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5357, 6, '2026-08-01 14:50:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5358, 6, '2026-08-01 14:51:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5359, 6, '2026-08-01 14:52:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5360, 6, '2026-08-01 14:53:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5361, 6, '2026-08-01 14:54:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5362, 6, '2026-08-01 14:55:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5364, 6, '2026-08-01 14:56:00', 39, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5366, 6, '2026-08-01 14:57:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5367, 7, '2026-08-01 14:57:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5370, 6, '2026-08-01 14:58:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5371, 7, '2026-08-01 14:58:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5374, 6, '2026-08-01 14:59:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5375, 7, '2026-08-01 14:59:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5378, 6, '2026-08-01 15:00:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5379, 7, '2026-08-01 15:00:00', 1, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5382, 6, '2026-08-01 15:01:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5383, 7, '2026-08-01 15:01:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5385, 6, '2026-08-01 15:02:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5387, 6, '2026-08-01 15:03:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5388, 6, '2026-08-01 15:04:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5390, 6, '2026-08-01 15:05:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5392, 6, '2026-08-01 15:06:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5394, 6, '2026-08-01 15:07:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5396, 6, '2026-08-01 15:08:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5398, 6, '2026-08-01 15:09:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5400, 6, '2026-08-01 15:10:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5402, 6, '2026-08-01 15:11:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5404, 6, '2026-08-01 15:12:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5406, 6, '2026-08-01 15:13:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5408, 6, '2026-08-01 15:14:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5410, 6, '2026-08-01 15:15:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5412, 6, '2026-08-01 15:16:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5414, 6, '2026-08-01 15:17:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5416, 6, '2026-08-01 15:18:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5418, 6, '2026-08-01 15:19:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5420, 6, '2026-08-01 15:20:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5422, 6, '2026-08-01 15:21:00', 38, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5424, 6, '2026-08-01 15:29:00', 39, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5425, 7, '2026-08-01 15:29:00', 0, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5426, 6, '2026-08-01 15:30:00', 39, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5427, 6, '2026-08-01 15:31:00', 39, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5428, 6, '2026-08-01 15:32:00', 39, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5429, 6, '2026-08-01 15:34:00', 39, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5430, 6, '2026-08-01 15:35:00', 39, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5431, 6, '2026-08-01 15:36:00', 39, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5432, 6, '2026-08-01 15:37:00', 39, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5433, 6, '2026-08-01 15:38:00', 39, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5434, 6, '2026-08-01 15:39:00', 39, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5435, 6, '2026-08-01 15:43:00', 39, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5436, 6, '2026-08-01 15:44:00', 39, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5437, 6, '2026-08-01 15:45:00', 39, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5438, 6, '2026-08-01 15:46:00', 39, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5439, 6, '2026-08-01 15:47:00', 39, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5440, 6, '2026-08-01 15:48:00', 39, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5441, 6, '2026-08-01 15:49:00', 39, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5442, 6, '2026-08-01 15:50:00', 39, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5443, 6, '2026-08-01 15:53:00', 40, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5444, 6, '2026-08-01 15:54:00', 40, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5445, 6, '2026-08-01 15:55:00', 40, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5446, 6, '2026-08-01 15:56:00', 40, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5447, 6, '2026-08-01 16:06:00', 41, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5448, 6, '2026-08-01 16:07:00', 43, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5449, 6, '2026-08-01 16:08:00', 43, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5450, 6, '2026-08-01 16:09:00', 43, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5451, 6, '2026-08-01 16:10:00', 43, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5452, 6, '2026-08-01 16:12:00', 44, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5453, 6, '2026-08-01 16:13:00', 44, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5454, 6, '2026-08-01 16:14:00', 44, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5455, 6, '2026-08-01 16:15:00', 44, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5456, 6, '2026-08-01 16:16:00', 44, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5457, 6, '2026-08-01 16:17:00', 44, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5458, 6, '2026-08-01 16:18:00', 44, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5459, 6, '2026-08-01 16:19:00', 45, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5460, 6, '2026-08-01 16:20:00', 45, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5461, 6, '2026-08-01 16:21:00', 45, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5462, 6, '2026-08-01 16:22:00', 45, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5463, 6, '2026-08-01 16:24:00', 46, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5464, 6, '2026-08-01 16:25:00', 46, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5465, 6, '2026-08-01 16:26:00', 46, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5466, 6, '2026-08-01 16:27:00', 46, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5467, 6, '2026-08-01 16:28:00', 46, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5468, 6, '2026-08-01 16:29:00', 46, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5469, 6, '2026-08-01 16:30:00', 46, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5470, 6, '2026-08-01 16:31:00', 46, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5471, 6, '2026-08-01 16:32:00', 46, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5472, 6, '2026-08-01 16:33:00', 46, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5473, 6, '2026-08-01 16:34:00', 46, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5474, 6, '2026-08-01 16:35:00', 46, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5475, 6, '2026-08-01 16:36:00', 46, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5476, 6, '2026-08-01 16:37:00', 46, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5477, 6, '2026-08-01 16:38:00', 46, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5478, 6, '2026-08-01 16:39:00', 46, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5479, 6, '2026-08-01 16:40:00', 46, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5480, 6, '2026-08-01 16:41:00', 46, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5481, 6, '2026-08-01 16:42:00', 46, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5482, 6, '2026-08-01 16:43:00', 46, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5483, 6, '2026-08-01 16:44:00', 46, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5484, 6, '2026-08-01 16:45:00', 46, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5485, 6, '2026-08-01 16:46:00', 46, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5486, 6, '2026-08-01 16:47:00', 46, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5487, 6, '2026-08-01 16:49:00', 48, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5488, 6, '2026-08-01 16:50:00', 48, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5489, 6, '2026-08-01 16:51:00', 48, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5490, 6, '2026-08-01 16:52:00', 48, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5491, 6, '2026-08-01 16:54:00', 50, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5492, 6, '2026-08-01 16:55:00', 50, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5493, 6, '2026-08-01 16:56:00', 50, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5494, 6, '2026-08-01 16:57:00', 50, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5495, 6, '2026-08-01 16:59:00', 51, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5496, 6, '2026-08-01 17:00:00', 51, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5497, 6, '2026-08-01 17:01:00', 51, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5498, 6, '2026-08-01 17:02:00', 51, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5499, 6, '2026-08-01 17:03:00', 51, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5500, 6, '2026-08-01 17:04:00', 51, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5501, 6, '2026-08-01 17:06:00', 52, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5502, 6, '2026-08-01 17:07:00', 52, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5503, 6, '2026-08-01 17:10:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5504, 6, '2026-08-01 17:11:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5505, 6, '2026-08-01 17:12:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5506, 6, '2026-08-01 17:13:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5507, 6, '2026-08-01 17:14:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5508, 6, '2026-08-01 17:15:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5509, 6, '2026-08-01 17:16:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5510, 6, '2026-08-01 17:17:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5511, 6, '2026-08-01 17:18:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5512, 6, '2026-08-01 17:19:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5513, 6, '2026-08-01 17:20:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5514, 6, '2026-08-01 17:21:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5515, 6, '2026-08-01 17:22:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5516, 6, '2026-08-01 17:23:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5517, 6, '2026-08-01 17:24:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5518, 6, '2026-08-01 17:25:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5519, 6, '2026-08-01 17:26:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5520, 6, '2026-08-01 17:27:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5521, 6, '2026-08-01 17:28:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5522, 6, '2026-08-01 17:29:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5523, 6, '2026-08-01 17:30:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5524, 6, '2026-08-01 17:31:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5525, 6, '2026-08-01 17:32:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5526, 6, '2026-08-01 17:33:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5527, 6, '2026-08-01 17:34:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5528, 6, '2026-08-01 17:35:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5529, 6, '2026-08-01 17:36:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5530, 6, '2026-08-01 17:37:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5531, 6, '2026-08-01 17:38:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5532, 6, '2026-08-01 17:39:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5533, 6, '2026-08-01 17:40:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5534, 6, '2026-08-01 17:41:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5535, 6, '2026-08-01 17:42:00', 53, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5536, 6, '2026-08-01 17:43:00', 52, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5537, 6, '2026-08-01 17:44:00', 52, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5538, 6, '2026-08-01 17:45:00', 52, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5539, 6, '2026-08-01 17:46:00', 52, 0, 0.00);
INSERT INTO `lp_room_online_minute` VALUES (5540, 6, '2026-08-01 17:47:00', 52, 0, 0.00);

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
  `scheduled_at` datetime(0) NULL DEFAULT NULL COMMENT '调度时间',
  `started_at` datetime(0) NULL DEFAULT NULL COMMENT '开始时间',
  `ended_at` datetime(0) NULL DEFAULT NULL COMMENT '结束时间',
  `created_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_room_status_priority`(`room_id`, `status`, `priority`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '房间播放任务' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for lp_room_state_snapshot
-- ----------------------------
DROP TABLE IF EXISTS `lp_room_state_snapshot`;
CREATE TABLE `lp_room_state_snapshot`  (
  `room_id` bigint(20) UNSIGNED NOT NULL COMMENT '房间ID',
  `current_state` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public_ready' COMMENT '当前状态',
  `current_mode` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public' COMMENT '当前模式:public interaction privilege',
  `current_task_id` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '当前任务ID',
  `privilege_expire_at` datetime(0) NULL DEFAULT NULL COMMENT '特权过期时间',
  `version` int(11) NOT NULL DEFAULT 1 COMMENT '乐观锁版本',
  `updated_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`room_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '房间状态快照' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_room_state_snapshot
-- ----------------------------
INSERT INTO `lp_room_state_snapshot` VALUES (5, 'offline', 'public', NULL, NULL, 32, '2026-08-01 14:54:30');
INSERT INTO `lp_room_state_snapshot` VALUES (6, 'offline', 'public', NULL, NULL, 108, '2026-08-07 12:34:24');
INSERT INTO `lp_room_state_snapshot` VALUES (7, 'offline', 'public', NULL, NULL, 108, '2026-08-01 16:07:03');
INSERT INTO `lp_room_state_snapshot` VALUES (8, 'offline', 'public', NULL, NULL, 72, '2026-08-01 14:54:30');

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
  `scheduled_at` datetime(0) NULL DEFAULT NULL COMMENT '调度时间',
  `started_at` datetime(0) NULL DEFAULT NULL COMMENT '开始时间',
  `ended_at` datetime(0) NULL DEFAULT NULL COMMENT '结束时间',
  `created_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_switch_task_no`(`task_no`) USING BTREE,
  INDEX `idx_room_status_scheduled`(`room_id`, `status`, `scheduled_at`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '房间切换任务' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for lp_room_tag
-- ----------------------------
DROP TABLE IF EXISTS `lp_room_tag`;
CREATE TABLE `lp_room_tag`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `room_id` bigint(20) UNSIGNED NOT NULL COMMENT '房间ID',
  `tag_name` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '标签名',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_room_tag`(`room_id`, `tag_name`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 48 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '房间标签' ROW_FORMAT = Dynamic;

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
INSERT INTO `lp_room_tag` VALUES (47, 6, '22');
INSERT INTO `lp_room_tag` VALUES (44, 7, '陪伴');

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
  UNIQUE INDEX `uk_stream_template_code`(`template_code`) USING BTREE
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
  `created_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_user_no`(`user_no`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `idx_email`(`email`) USING BTREE
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
  `created_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_auth_type_key`(`auth_type`, `auth_key`) USING BTREE,
  INDEX `idx_user_id`(`user_id`) USING BTREE
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
  `created_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_user_device`(`user_id`, `device_id`) USING BTREE,
  INDEX `idx_device_id`(`device_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '用户设备表' ROW_FORMAT = Dynamic;

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
  `last_login_at` datetime(0) NULL DEFAULT NULL COMMENT '最近登录时间',
  PRIMARY KEY (`user_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '用户扩展资料' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_user_profile
-- ----------------------------
INSERT INTO `lp_user_profile` VALUES (1, 0, '', '', '127.0.0.1', '2026-08-07 14:10:16');
INSERT INTO `lp_user_profile` VALUES (2, 0, '', '', '127.0.0.1', '2026-08-07 14:00:50');
INSERT INTO `lp_user_profile` VALUES (3, 0, '', '', '127.0.0.1', '2026-08-01 16:58:33');
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
  `updated_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_wallet_user_id`(`user_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '钱包账户' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_wallet_account
-- ----------------------------
INSERT INTO `lp_wallet_account` VALUES (1, 1, 9880.00, 1, '2026-08-07 14:24:52');
INSERT INTO `lp_wallet_account` VALUES (2, 2, 62000.00, 1, '2026-08-07 13:11:27');

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
  `created_at` datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_user_created`(`user_id`, `created_at`) USING BTREE,
  INDEX `idx_biz_type_biz_id`(`biz_type`, `biz_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 13 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '钱包流水' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lp_wallet_ledger
-- ----------------------------
INSERT INTO `lp_wallet_ledger` VALUES (1, 1, 'adjust', 1, 'diamond', 100.00, 0.00, 100.00, NULL, '测试充值（众筹测试）', '2026-08-07 11:56:46');
INSERT INTO `lp_wallet_ledger` VALUES (2, 2, 'recharge', 1, 'diamond', 10000.00, 0.00, 10000.00, 1, '管理员审核充值', '2026-08-07 12:31:24');
INSERT INTO `lp_wallet_ledger` VALUES (3, 2, 'recharge', 1, 'diamond', 10000.00, 10000.00, 20000.00, 2, '管理员审核充值', '2026-08-07 12:33:05');
INSERT INTO `lp_wallet_ledger` VALUES (4, 2, 'recharge', 1, 'diamond', 10000.00, 20000.00, 30000.00, 3, '管理员审核充值', '2026-08-07 12:53:14');
INSERT INTO `lp_wallet_ledger` VALUES (5, 2, 'recharge', 1, 'diamond', 10000.00, 30000.00, 40000.00, 4, '管理员审核充值', '2026-08-07 13:00:39');
INSERT INTO `lp_wallet_ledger` VALUES (6, 2, 'recharge', 1, 'diamond', 10000.00, 40000.00, 50000.00, 6, '管理员审核充值', '2026-08-07 13:03:38');
INSERT INTO `lp_wallet_ledger` VALUES (7, 2, 'recharge', 1, 'diamond', 1000.00, 50000.00, 51000.00, 7, '管理员审核充值', '2026-08-07 13:05:27');
INSERT INTO `lp_wallet_ledger` VALUES (8, 2, 'recharge', 1, 'diamond', 1000.00, 51000.00, 52000.00, 7, '管理员审核充值', '2026-08-07 13:08:29');
INSERT INTO `lp_wallet_ledger` VALUES (9, 2, 'recharge', 1, 'diamond', 10000.00, 52000.00, 62000.00, 8, '管理员审核充值', '2026-08-07 13:11:27');
INSERT INTO `lp_wallet_ledger` VALUES (10, 1, 'recharge', 1, 'diamond', 9800.00, 100.00, 9900.00, 9, '管理员审核充值', '2026-08-07 14:22:54');
INSERT INTO `lp_wallet_ledger` VALUES (11, 1, 'crowdfunding_pledge', 2, 'diamond', 10.00, 9900.00, 9890.00, 1, '众筹支持冻结', '2026-08-07 14:24:45');
INSERT INTO `lp_wallet_ledger` VALUES (12, 1, 'crowdfunding_pledge', 2, 'diamond', 10.00, 9890.00, 9880.00, 1, '众筹支持冻结', '2026-08-07 14:24:52');

SET FOREIGN_KEY_CHECKS = 1;
