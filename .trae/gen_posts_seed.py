# -*- coding: utf-8 -*-
"""一次性脚本：把 .trae/_posts_items.json 转成 php/sql/upgrade_posts.sql 的预置 INSERT 段。

用法：python .trae/gen_posts_seed.py
输出：php/sql/upgrade_posts.sql（覆盖写入），并打印生成的 INSERT 行数（应为 6）。
"""
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
JSON_FILE = ROOT / '.trae' / '_posts_items.json'
SQL_FILE = ROOT / 'php' / 'sql' / 'upgrade_posts.sql'


def esc(value) -> str:
    """转义 SQL 单引号与反斜杠"""
    if value is None:
        return ''
    return str(value).replace('\\', '\\\\').replace("'", "''")


def rel(path) -> str:
    """./posts_files/xxx-webp90 -> /posts_files/xxx-webp90（外链原样保留）"""
    if not path:
        return ''
    path = str(path).strip()
    if path.startswith('./'):
        path = path[1:]
    if not path.startswith('/') and not path.startswith('http'):
        path = '/' + path
    return path


def build_seed(items) -> tuple[str, int]:
    rows = []
    total = len(items)
    for idx, it in enumerate(items):
        weigh = (total - idx) * 10
        rows.append(
            "  SELECT '{pid}' AS pid, '{cn}' AS cn, '{ca}' AS ca, '{cu}' AS cu, '{vu}' AS vu,\n"
            "         '{pu}' AS pu, '{ds}' AS ds, {lk} AS lk, {vw} AS vw, {w} AS w".format(
                pid=esc(it.get('post_id', '')),
                cn=esc(it.get('character_name', '')),
                ca=esc(rel(it.get('avatar'))),
                cu=esc(it.get('character_url', '')),
                vu=esc(rel(it.get('video'))),
                pu=esc(rel(it.get('poster'))),
                ds=esc(it.get('description', '')),
                lk=int(it.get('likes') or 0),
                vw=int(it.get('views') or 0),
                w=weigh,
            )
        )
    seed = "\n  UNION ALL\n".join(rows)
    return seed, total


TEMPLATE = """-- ============================================
-- AI 女友端「Posts 动态」功能
-- 执行方式：source php/sql/upgrade_posts.sql（数据库 live_platform）
--
-- 说明：
--   1) 新增动态表 lp_post_item（后台可配置，前台 posts 页展示）
--   2) 预置 candy.ai 帖子存档抽取的 {count} 条真实数据，后台可自行增删改
--   3) 新增后台菜单「动态管理」（挂在「直播运营」目录下）
-- ============================================

-- 1. 动态表
CREATE TABLE IF NOT EXISTS `lp_post_item` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `post_id` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '原站帖子id',
  `character_name` VARCHAR(120) NOT NULL DEFAULT '' COMMENT '角色名',
  `character_avatar` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '角色头像(相对路径或外链)',
  `character_url` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '角色主页链接',
  `video_url` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '帖子视频URL',
  `poster_url` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '视频封面URL(可空)',
  `description` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '帖子描述(可空)',
  `likes` INT NOT NULL DEFAULT 0 COMMENT '点赞数',
  `views` INT NOT NULL DEFAULT 0 COMMENT '浏览数',
  `weigh` INT NOT NULL DEFAULT 0 COMMENT '权重(越大越靠前)',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态(1上架0下架)',
  `created_at` DATETIME DEFAULT NULL COMMENT '创建时间',
  `updated_at` DATETIME DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_status_weigh` (`status`, `weigh`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI女友端帖子动态';

-- 2. 预置 candy.ai 帖子存档数据（按原站顺序，weigh 递减）
INSERT INTO `lp_post_item`
  (`post_id`, `character_name`, `character_avatar`, `character_url`, `video_url`, `poster_url`, `description`, `likes`, `views`, `weigh`)
SELECT * FROM (
{seed}
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `lp_post_item`);

UPDATE `lp_post_item` SET `status` = 1, `created_at` = NOW(), `updated_at` = NOW() WHERE `created_at` IS NULL;

-- 3. 后台菜单「动态管理」（挂在「直播运营」目录下）
SET @live_pid = IFNULL((SELECT `id` FROM `ba_admin_rule` WHERE `title` = '直播运营' AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1), 0);

INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `keepalive`, `weigh`, `status`) VALUES
(@live_pid, 'menu', 'live/postItem', '动态管理', 'fa fa-camera-retro', 'live/postItem', '/src/views/backend/live/postItem/index.vue', 'tab', 1, 39, 1);

SET @post_pid = (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'live/postItem' AND `type` = 'menu' LIMIT 1);

INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `keepalive`, `weigh`, `status`) VALUES
(@post_pid, 'button', 'live/postItem/index', '查看', '', '', '', NULL, 0, 10, 1),
(@post_pid, 'button', 'live/postItem/add', '新增', '', '', '', NULL, 0, 9, 1),
(@post_pid, 'button', 'live/postItem/edit', '编辑', '', '', '', NULL, 0, 8, 1),
(@post_pid, 'button', 'live/postItem/del', '删除', '', '', '', NULL, 0, 7, 1);

SELECT `id`, `pid`, `title`, `name`, `component`, `status` FROM `ba_admin_rule` WHERE `name` LIKE 'live/postItem%';
SELECT COUNT(*) AS post_items FROM `lp_post_item`;
"""


def main() -> None:
    data = json.loads(JSON_FILE.read_text(encoding='utf-8'))
    items = data['items'] if isinstance(data, dict) else data
    seed, count = build_seed(items)
    SQL_FILE.write_text(TEMPLATE.format(seed=seed, count=count), encoding='utf-8')
    print('INSERT 行数：%d' % count)


if __name__ == '__main__':
    main()
