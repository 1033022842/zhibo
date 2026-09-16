# -*- coding: utf-8 -*-
"""一次性脚本：把 .trae/_shorts_items.json 的 34 张卡片转成 upgrade_shorts.sql。

用法：
    python .trae/gen_shorts_seed.py
执行后会在 php/sql/upgrade_shorts.sql 生成
  1) lp_short_item 建表
  2) 预置数据（仅表为空时插入，幂等）
  3) 后台「短剧管理」菜单节点
并打印实际写入的 INSERT 行数（应为 34）。
"""

from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
JSON_FILE = ROOT / ".trae" / "_shorts_items.json"
SQL_FILE = ROOT / "php" / "sql" / "upgrade_shorts.sql"

# JSON 里的 section key -> 入库的 section 值（表定义只允许这三个值）
SECTION_MAP = {
    "continue_watching": "continue_watching",
    "top_series": "top_series",
    "explore_all_shorts": "explore",
}
# 入库顺序 == 前端展示顺序（continue_watching -> top_series -> explore）
SECTION_ORDER = ["continue_watching", "top_series", "explore_all_shorts"]


def sql_str(value: str) -> str:
    """单引号转义（标准 SQL 用两个单引号）"""
    return "'" + str(value).replace("'", "''") + "'"


def build_rows() -> list[dict]:
    data = json.loads(JSON_FILE.read_text(encoding="utf-8"))
    sections = {s["key"]: s for s in data["sections"]}

    rows: list[dict] = []
    for key in SECTION_ORDER:
        items = sections[key]["items"]
        total = len(items)
        for idx, item in enumerate(items):
            poster = str(item.get("poster") or "")
            if poster.startswith("./"):
                poster = poster[1:]  # ./candy-shorts_files/xxx.webp -> /candy-shorts_files/xxx.webp

            rows.append(
                {
                    "title": str(item.get("title") or ""),
                    "poster": poster,
                    "href": str(item.get("href") or ""),
                    "section": SECTION_MAP[key],
                    # 同一 section 内按 weigh 降序保证还原原站展示顺序
                    "rank": int(item.get("rank") or 0),
                    "progress": "%.2f" % float(item.get("progress") or 0),
                    "spicy": 1 if item.get("spicy") else 0,
                    "featured": str(item.get("featured") or ""),
                    "new_episodes": 1 if item.get("new_episodes") else 0,
                    "weigh": (total - idx) * 10,
                }
            )
    return rows


def build_insert(rows: list[dict]) -> str:
    cols = (
        "`title`, `poster`, `href`, `section`, `rank`, `progress`, "
        "`spicy`, `featured`, `new_episodes`, `weigh`"
    )
    lines = [
        f"INSERT INTO `lp_short_item` ({cols})",
        "SELECT * FROM (",
    ]
    for i, r in enumerate(rows):
        select = "SELECT" if i == 0 else "UNION ALL SELECT"
        values = (
            f"{sql_str(r['title'])}, {sql_str(r['poster'])}, {sql_str(r['href'])}, "
            f"{sql_str(r['section'])}, {r['rank']}, {r['progress']}, "
            f"{r['spicy']}, {sql_str(r['featured'])}, {r['new_episodes']}, {r['weigh']}"
        )
        lines.append(f"  {select} {values}")
    lines.append(") AS seed")
    lines.append("WHERE NOT EXISTS (SELECT 1 FROM `lp_short_item`);")
    return "\n".join(lines)


def build_sql(rows: list[dict]) -> str:
    head = """-- ============================================
-- AI 女友端「Candy Shorts 短剧」功能
-- 执行方式：source php/sql/upgrade_shorts.sql（数据库 live_platform）
--
-- 说明：
--   1) 新增短剧卡片表 lp_short_item（后台可配置，前台 /api/live/shorts 读取）
--   2) 预置 candy.ai/candy-shorts 存档页抽取的 34 张卡片
--      section 取值：continue_watching / top_series / explore
--      （原 JSON 里的 key 为 continue_watching / top_series / explore_all_shorts，
--        第三条统一归一化为 explore）
--   3) 新增后台菜单「短剧管理」（挂在「直播运营」目录下）
--
-- 本文件由 .trae/gen_shorts_seed.py 生成，改动数据请改脚本后重新生成。
-- ============================================

-- 1. 短剧卡片表
CREATE TABLE IF NOT EXISTS `lp_short_item` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `title` VARCHAR(160) NOT NULL DEFAULT '' COMMENT '卡片标题',
  `poster` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '封面图(相对路径或外链)',
  `href` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '卡片兜底链接',
  `section` VARCHAR(32) NOT NULL DEFAULT 'explore' COMMENT '分区(continue_watching/top_series/explore)',
  `rank` INT NOT NULL DEFAULT 0 COMMENT '排行榜名次(仅 top_series 用)',
  `progress` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '观看进度百分比',
  `spicy` TINYINT NOT NULL DEFAULT 0 COMMENT '是否 SPICY 角标',
  `featured` VARCHAR(16) NOT NULL DEFAULT '' COMMENT '高亮变体(空/ring/gradient)',
  `new_episodes` TINYINT NOT NULL DEFAULT 0 COMMENT '是否 New Episodes 角标',
  `weigh` INT NOT NULL DEFAULT 0 COMMENT '权重(越大越靠前)',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态(1上架0下架)',
  `created_at` DATETIME DEFAULT NULL COMMENT '创建时间',
  `updated_at` DATETIME DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_status_section_weigh` (`status`, `section`, `weigh`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI女友端短剧卡片';
"""

    seed = f"\n-- 2. 预置原站存档的 {len(rows)} 张短剧卡片（仅表为空时插入）\n" + build_insert(rows) + "\n"

    tail = """
UPDATE `lp_short_item` SET `status` = 1, `created_at` = NOW(), `updated_at` = NOW() WHERE `created_at` IS NULL;

-- 3. 后台菜单「短剧管理」（挂在「直播运营」目录下）
SET @live_pid = IFNULL((SELECT `id` FROM `ba_admin_rule` WHERE `title` = '直播运营' AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1), 0);

INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `keepalive`, `weigh`, `status`) VALUES
(@live_pid, 'menu', 'live/shortItem', '短剧管理', 'fa fa-film', 'live/shortItem', '/src/views/backend/live/shortItem/index.vue', 'tab', 1, 50, 1);

SET @short_pid = (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'live/shortItem' AND `type` = 'menu' LIMIT 1);

INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `keepalive`, `weigh`, `status`) VALUES
(@short_pid, 'button', 'live/shortItem/index', '查看', '', '', '', NULL, 0, 10, 1),
(@short_pid, 'button', 'live/shortItem/add', '新增', '', '', '', NULL, 0, 9, 1),
(@short_pid, 'button', 'live/shortItem/edit', '编辑', '', '', '', NULL, 0, 8, 1),
(@short_pid, 'button', 'live/shortItem/del', '删除', '', '', '', NULL, 0, 7, 1);

SELECT `id`, `pid`, `title`, `name`, `component`, `status` FROM `ba_admin_rule` WHERE `name` LIKE 'live/shortItem%';
SELECT COUNT(*) AS short_items FROM `lp_short_item`;
"""
    return head + seed + tail


def main() -> None:
    rows = build_rows()
    SQL_FILE.write_text(build_sql(rows), encoding="utf-8")

    print(f"生成文件: {SQL_FILE}")
    print(f"INSERT 行数: {len(rows)}")
    by_section: dict[str, int] = {}
    for r in rows:
        by_section[r["section"]] = by_section.get(r["section"], 0) + 1
    for k in SECTION_ORDER:
        print(f"  - {SECTION_MAP[k]}: {by_section.get(SECTION_MAP[k], 0)}")


if __name__ == "__main__":
    main()
