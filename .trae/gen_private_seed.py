# -*- coding: utf-8 -*-
"""一次性脚本：把 .trae/_private_items.json 的 94 张卡片转成 php/sql/upgrade_private_content.sql。

用法：
    python .trae/gen_private_seed.py
执行后会在 php/sql/upgrade_private_content.sql 生成
  1) lp_private_item 建表
  2) 预置数据（仅表为空时插入，幂等）
  3) 后台「私密内容管理」菜单节点
并打印实际写入的 INSERT 行数（应为 94）。

JSON 字段 -> 表字段映射：
  card_id      -> id            （原站 content_pack id，前台详情页要用到，直接沿用）
  title        -> title         （卡片上唯一的文字，原样保留）
  poster       -> poster        （去掉开头 ./，外链原样保留）
  avatar       -> avatar        （去掉开头 ./，外链原样保留）
  creator      -> creator
  price        -> price
  like         -> like_rate     （点赞率百分比）
  media_type   -> media_type
  video_count  -> video_count
  duration     -> duration
  image_count  -> image_count
  badge        -> badge         （'New' -> 'new'，与后台下拉取值一致）
  purchase_url -> purchase_url  （外链原样保留）
  weigh        -> 由 JSON 数组顺序推导（第一条最大），还原原站展示顺序
  href / unlocked / button_text：库里没有对应列，不落库
"""

from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
JSON_FILE = ROOT / ".trae" / "_private_items.json"
SQL_FILE = ROOT / "php" / "sql" / "upgrade_private_content.sql"

MEDIA_TYPES = {"video", "image", "mixed"}


def sql_str(value) -> str:
    """单引号/反斜杠转义（标准 SQL 用两个单引号）"""
    return "'" + str(value if value is not None else "").replace("\\", "\\\\").replace("'", "''") + "'"


def rel(path) -> str:
    """./private-content_files/xxx.webp -> /private-content_files/xxx.webp（外链原样保留）"""
    path = str(path or "").strip()
    if path.startswith("./"):
        path = path[1:]
    if path and not path.startswith("/") and not path.startswith("http") and not path.startswith("//"):
        path = "/" + path
    return path


def build_rows() -> list[dict]:
    data = json.loads(JSON_FILE.read_text(encoding="utf-8"))
    items = data["items"]
    total = len(items)

    rows: list[dict] = []
    for idx, item in enumerate(items):
        media_type = str(item.get("media_type") or "").strip().lower()
        if media_type not in MEDIA_TYPES:
            media_type = "video"

        rows.append(
            {
                "id": int(item.get("card_id") or 0),
                "title": str(item.get("title") or ""),
                "poster": rel(item.get("poster")),
                "avatar": rel(item.get("avatar")),
                "creator": str(item.get("creator") or ""),
                "price": max(0, int(item.get("price") or 0)),
                "like_rate": max(0, min(100, int(item.get("like") or 0))),
                "media_type": media_type,
                "video_count": max(0, int(item.get("video_count") or 0)),
                "duration": str(item.get("duration") or ""),
                "image_count": max(0, int(item.get("image_count") or 0)),
                "badge": "new" if str(item.get("badge") or "").strip().lower() == "new" else "",
                "purchase_url": str(item.get("purchase_url") or "").strip(),
                # 按原站展示顺序（JSON 数组顺序）weigh 递减
                "weigh": (total - idx) * 10,
            }
        )
    return rows


def build_insert(rows: list[dict]) -> str:
    cols = (
        "`id`, `title`, `poster`, `avatar`, `creator`, `price`, `like_rate`, `media_type`, "
        "`video_count`, `duration`, `image_count`, `badge`, `purchase_url`, `weigh`"
    )
    lines = [
        f"INSERT INTO `lp_private_item` ({cols})",
        "SELECT * FROM (",
    ]
    for i, r in enumerate(rows):
        select = "SELECT" if i == 0 else "UNION ALL SELECT"
        values = (
            f"{r['id']} AS id, {sql_str(r['title'])}, {sql_str(r['poster'])}, {sql_str(r['avatar'])}, "
            f"{sql_str(r['creator'])}, {r['price']}, {r['like_rate']}, {sql_str(r['media_type'])}, "
            f"{r['video_count']}, {sql_str(r['duration'])}, {r['image_count']}, {sql_str(r['badge'])}, "
            f"{sql_str(r['purchase_url'])}, {r['weigh']}"
        )
        lines.append(f"  {select} {values}")
    lines.append(") AS seed")
    lines.append("WHERE NOT EXISTS (SELECT 1 FROM `lp_private_item`);")
    return "\n".join(lines)


def build_sql(rows: list[dict]) -> str:
    head = """-- ============================================
-- AI 女友端「Private Content 私密内容」功能
-- 执行方式：source php/sql/upgrade_private_content.sql（数据库 live_platform）
--
-- 说明：
--   1) 新增私密内容卡片表 lp_private_item（后台可配置，前台 /api/live/privateContents 读取）
--   2) 预置 candy.ai/private-content 存档页抽取的 %d 条卡片（仅表为空时插入，幂等）
--      - 资源路径入库时去掉开头 ./（如 /private-content_files/xxx.webp），外链原样保留
--      - weigh 由存档顺序推导（第一条最大），保证「全部」列表还原原站展示顺序
--   3) 新增后台菜单「私密内容管理」（挂在「直播运营」目录下）
--
-- 本文件由 .trae/gen_private_seed.py 生成，改动数据请改脚本后重新生成。
-- ============================================

-- 1. 私密内容卡片表
CREATE TABLE IF NOT EXISTS `lp_private_item` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID(沿用原站 content_pack id)',
  `title` VARCHAR(1000) NOT NULL DEFAULT '' COMMENT '卡片描述文案(卡片上唯一的文字)',
  `poster` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '封面图(相对路径或外链)',
  `avatar` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '角色头像(相对路径或外链)',
  `creator` VARCHAR(120) NOT NULL DEFAULT '' COMMENT '角色名',
  `price` INT NOT NULL DEFAULT 0 COMMENT '价格(代币)',
  `like_rate` INT NOT NULL DEFAULT 0 COMMENT '点赞率百分比(0~100)',
  `media_type` VARCHAR(16) NOT NULL DEFAULT 'video' COMMENT '媒体类型(video/image/mixed)',
  `video_count` INT NOT NULL DEFAULT 0 COMMENT '视频数',
  `duration` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '时长文案(如 09:09)',
  `image_count` INT NOT NULL DEFAULT 0 COMMENT '图片数',
  `badge` VARCHAR(16) NOT NULL DEFAULT '' COMMENT '角标(空/new)',
  `purchase_url` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '详情/购买链接',
  `weigh` INT NOT NULL DEFAULT 0 COMMENT '权重(越大越靠前)',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态(1上架0下架)',
  `created_at` DATETIME DEFAULT NULL COMMENT '创建时间',
  `updated_at` DATETIME DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_status_weigh` (`status`, `weigh`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI女友端私密内容卡片';
""" % len(rows)

    seed = f"\n-- 2. 预置原站存档的 {len(rows)} 条私密内容卡片（仅表为空时插入）\n" + build_insert(rows) + "\n"

    tail = """
UPDATE `lp_private_item` SET `status` = 1, `created_at` = NOW(), `updated_at` = NOW() WHERE `created_at` IS NULL;

-- 3. 后台菜单「私密内容管理」（挂在「直播运营」目录下）
SET @live_pid = IFNULL((SELECT `id` FROM `ba_admin_rule` WHERE `title` = '直播运营' AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1), 0);

INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `keepalive`, `weigh`, `status`) VALUES
(@live_pid, 'menu', 'live/privateItem', '私密内容管理', 'fa fa-lock', 'live/privateItem', '/src/views/backend/live/privateItem/index.vue', 'tab', 1, 38, 1);

SET @private_pid = (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'live/privateItem' AND `type` = 'menu' LIMIT 1);

INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `keepalive`, `weigh`, `status`) VALUES
(@private_pid, 'button', 'live/privateItem/index', '查看', '', '', '', NULL, 0, 10, 1),
(@private_pid, 'button', 'live/privateItem/add', '新增', '', '', '', NULL, 0, 9, 1),
(@private_pid, 'button', 'live/privateItem/edit', '编辑', '', '', '', NULL, 0, 8, 1),
(@private_pid, 'button', 'live/privateItem/del', '删除', '', '', '', NULL, 0, 7, 1);

SELECT `id`, `pid`, `title`, `name`, `component`, `status` FROM `ba_admin_rule` WHERE `name` LIKE 'live/privateItem%';
SELECT COUNT(*) AS private_items FROM `lp_private_item`;
"""
    return head + seed + tail


def main() -> None:
    rows = build_rows()
    SQL_FILE.write_text(build_sql(rows), encoding="utf-8")

    print(f"生成文件: {SQL_FILE}")
    print(f"INSERT 行数: {len(rows)}")
    print(f"  含视频: {sum(1 for r in rows if r['media_type'] == 'video')}")
    print(f"  含图片: {sum(1 for r in rows if r['media_type'] == 'image')}")
    print(f"  badge=new: {sum(1 for r in rows if r['badge'] == 'new')}")
    print(f"  最长 title: {max(len(r['title']) for r in rows)} 字符")


if __name__ == "__main__":
    main()
