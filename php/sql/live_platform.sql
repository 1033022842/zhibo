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

 Date: 17/05/2026 00:02:13
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
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '管理员日志表' ROW_FORMAT = DYNAMIC;

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
) ENGINE = InnoDB AUTO_INCREMENT = 90 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '菜单和权限规则表' ROW_FORMAT = DYNAMIC;

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
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '附件表' ROW_FORMAT = DYNAMIC;

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
) ENGINE = InnoDB AUTO_INCREMENT = 15 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '系统配置' ROW_FORMAT = DYNAMIC;

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
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'CRUD记录表' ROW_FORMAT = DYNAMIC;

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
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '数据回收记录表' ROW_FORMAT = DYNAMIC;

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
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '敏感数据修改记录' ROW_FORMAT = DYNAMIC;

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
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '知识库表' ROW_FORMAT = DYNAMIC;

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
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '会员余额变动表' ROW_FORMAT = DYNAMIC;

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
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '会员积分变动表' ROW_FORMAT = DYNAMIC;

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
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '聊天消息' ROW_FORMAT = Dynamic;

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
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '礼物订单' ROW_FORMAT = Dynamic;

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
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '素材池' ROW_FORMAT = Dynamic;

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
-- Table structure for lp_persona
-- ----------------------------
DROP TABLE IF EXISTS `lp_persona`;
CREATE TABLE `lp_persona`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `code` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '人设编码',
  `name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '人设名称',
  `tags` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '标签,逗号分隔',
  `cover_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '封面',
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '状态',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_persona_code`(`code` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '数字人人设' ROW_FORMAT = Dynamic;

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
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '播单模板' ROW_FORMAT = Dynamic;

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
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '播单模板项' ROW_FORMAT = Dynamic;

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
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '直播房间' ROW_FORMAT = Dynamic;

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
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '房间绑定配置' ROW_FORMAT = Dynamic;

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
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '共享源房间分组' ROW_FORMAT = Dynamic;

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
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '房间分钟聚合数据' ROW_FORMAT = Dynamic;

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
-- Table structure for lp_room_tag
-- ----------------------------
DROP TABLE IF EXISTS `lp_room_tag`;
CREATE TABLE `lp_room_tag`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `room_id` bigint(20) UNSIGNED NOT NULL COMMENT '房间ID',
  `tag_name` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '标签名',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_room_tag`(`room_id` ASC, `tag_name` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '房间标签' ROW_FORMAT = Dynamic;

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
-- Table structure for lp_user
-- ----------------------------
DROP TABLE IF EXISTS `lp_user`;
CREATE TABLE `lp_user`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_no` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '用户编号',
  `nickname` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '昵称',
  `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '头像',
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '状态:0禁用 1正常',
  `level` int(11) NOT NULL DEFAULT 1 COMMENT '用户等级',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_user_no`(`user_no` ASC) USING BTREE,
  INDEX `idx_status`(`status` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '用户主表' ROW_FORMAT = Dynamic;

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
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '用户认证表' ROW_FORMAT = Dynamic;

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

SET FOREIGN_KEY_CHECKS = 1;
