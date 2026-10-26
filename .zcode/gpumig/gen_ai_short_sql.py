#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""一次性：从 docx 提示词 + clips-manifest 生成 upgrade_ai_short.sql（含 50 条种子）。

输出 php/sql/upgrade_ai_short.sql。
"""
import json
import os
import re
import zipfile

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
DOCX = r"C:\Users\a1033\Downloads\Sex_Short_Video_Prompts_Revised.docx"
MANIFEST = os.path.join(ROOT, "client_overrides/image-sugus/media-src/clips-manifest.json")
OUT = os.path.join(ROOT, "php/sql/upgrade_ai_short.sql")

MB = "minimax-h3_fl2v_8Step_motion_enhancer.safetensors"
F = "MMH3_Perfect_fingering_v1_000002000.safetensors"
M = "MysticXXX_MMH3-V4.safetensors"
P = "MM-H3 - Pussy Lora.safetensors"
S = "H3_slop_bounce.safetensors"
A = "ass_jiggle_H3_i2v_v1.0.safetensors"

# 每条 4 层：motion_enhancer(基座) + 主 LoRA + 2 个低强度补位
# 说明：11 条无语义匹配的动作 LoRA，靠低强度补位，效果需看成片校正
CHAIN = {
    1:  [("blowjob", [(MB, 1.0), (M, 0.70), (P, 0.35), (F, 0.25)])],
    2:  [("cowgirl", [(MB, 1.0), (S, 0.80), (P, 0.40), (A, 0.35)])],
    3:  [("solo",    [(MB, 1.0), (F, 0.80), (P, 0.50), (A, 0.30)])],
    4:  [("doggy",   [(MB, 1.0), (A, 0.80), (P, 0.40), (S, 0.35)])],
    5:  [("other",   [(MB, 1.0), (P, 0.75), (A, 0.35), (S, 0.30)])],
    6:  [("doggy",   [(MB, 1.0), (A, 0.75), (P, 0.40), (S, 0.30)])],
    7:  [("solo",    [(MB, 1.0), (F, 0.70), (P, 0.45), (A, 0.25)])],
    8:  [("other",   [])],                       # 图片模板，走 qwenedit
    9:  [("solo",    [(MB, 1.0), (F, 0.50), (P, 0.40), (A, 0.25)])],
    10: [("cowgirl", [(MB, 1.0), (S, 0.85), (P, 0.40), (A, 0.35)])],
    11: [("blowjob", [(MB, 1.0), (M, 0.70), (P, 0.35), (F, 0.25)])],
    12: [("doggy",   [(MB, 1.0), (A, 0.85), (P, 0.35), (S, 0.30)])],
    13: [("other",   [(MB, 1.0), (P, 0.80), (A, 0.35), (S, 0.30)])],
    14: [("solo",    [(MB, 1.0), (F, 0.75), (P, 0.45), (A, 0.25)])],
    15: [("doggy",   [(MB, 1.0), (A, 0.80), (P, 0.40), (S, 0.30)])],
    16: [("solo",    [(MB, 1.0), (F, 0.50), (P, 0.40), (A, 0.30)])],
    17: [("solo",    [(MB, 1.0), (F, 0.70), (P, 0.45), (A, 0.25)])],
    18: [("other",   [(MB, 1.0), (P, 0.40), (F, 0.30), (A, 0.25)])],
    19: [("other",   [(MB, 1.0), (P, 0.60), (A, 0.35), (S, 0.30)])],
    20: [("solo",    [(MB, 1.0), (F, 0.70), (P, 0.45), (A, 0.25)])],
    21: [("solo",    [(MB, 1.0), (F, 0.70), (P, 0.45), (S, 0.30)])],
    22: [("other",   [(MB, 1.0), (P, 0.55), (A, 0.30), (S, 0.30)])],
    23: [("cumshot", [(MB, 1.0), (P, 0.70), (A, 0.30), (S, 0.30)])],
    24: [("cumshot", [(MB, 1.0), (P, 0.70), (A, 0.30), (S, 0.30)])],
    25: [("blowjob", [(MB, 1.0), (M, 0.70), (P, 0.35), (F, 0.25)])],
    26: [("other",   [(MB, 1.0), (P, 0.70), (A, 0.35), (S, 0.40)])],
    27: [("blowjob", [(MB, 1.0), (M, 0.80), (P, 0.35), (F, 0.25)])],
    28: [("doggy",   [(MB, 1.0), (A, 0.75), (P, 0.40), (S, 0.35)])],
    29: [("cowgirl", [(MB, 1.0), (S, 0.85), (P, 0.40), (A, 0.40)])],
    30: [("other",   [(MB, 1.0), (P, 0.60), (A, 0.35), (S, 0.35)])],
    31: [("cumshot", [(MB, 1.0), (P, 0.70), (A, 0.30), (S, 0.25)])],
    32: [("doggy",   [(MB, 1.0), (A, 0.85), (P, 0.40), (S, 0.35)])],
    33: [("blowjob", [(MB, 1.0), (M, 0.75), (P, 0.35), (F, 0.25)])],
    34: [("cowgirl", [(MB, 1.0), (S, 0.80), (P, 0.40), (A, 0.35)])],
    35: [("solo",    [(MB, 1.0), (F, 0.60), (P, 0.40), (A, 0.25)])],
    36: [("blowjob", [(MB, 1.0), (M, 0.70), (P, 0.35), (F, 0.30)])],
    37: [("cowgirl", [(MB, 1.0), (S, 0.80), (A, 0.50), (P, 0.40)])],
    38: [("other",   [(MB, 1.0), (P, 0.80), (A, 0.35), (S, 0.35)])],
    39: [("blowjob", [(MB, 1.0), (M, 0.80), (P, 0.30), (F, 0.25)])],
    40: [("blowjob", [(MB, 1.0), (M, 0.75), (P, 0.35), (F, 0.25)])],
    41: [("blowjob", [(MB, 1.0), (M, 0.85), (P, 0.30), (F, 0.25)])],
    42: [("other",   [(MB, 1.0), (P, 0.85), (A, 0.35), (S, 0.35)])],
    43: [("cumshot", [(MB, 1.0), (P, 0.50), (F, 0.40), (A, 0.25)])],
    44: [("cowgirl", [(MB, 1.0), (S, 0.90), (P, 0.40), (A, 0.40)])],
    45: [("cumshot", [(MB, 1.0), (P, 0.75), (A, 0.30), (S, 0.30)])],
    46: [("other",   [(MB, 1.0), (P, 0.55), (A, 0.30), (S, 0.30)])],
    47: [("cowgirl", [(MB, 1.0), (S, 0.75), (P, 0.40), (A, 0.30)])],
    48: [("doggy",   [(MB, 1.0), (A, 0.75), (P, 0.40), (S, 0.30)])],
    49: [("doggy",   [(MB, 1.0), (A, 0.90), (P, 0.40), (S, 0.40)])],
    50: [("cowgirl", [(MB, 1.0), (S, 0.90), (P, 0.40), (A, 0.40)])],
}


def parse_docx():
    z = zipfile.ZipFile(DOCX)
    import html as _h
    x = z.read("word/document.xml").decode("utf-8")
    x = re.sub(r"</w:p>", "\n", x)
    x = _h.unescape(re.sub(r"<[^>]+>", "", x))
    items, cur = [], None
    for line in [s.strip() for s in x.split("\n") if s.strip()]:
        m = re.match(r"^(\d{1,2})\.\s+(.*)$", line)
        if m:
            if cur:
                items.append(cur)
            cur = {"num": int(m.group(1)), "title": m.group(2), "body": ""}
        elif cur and not line.startswith(("Sex Short", "Revised", "Prompts rewritten")):
            cur["body"] += (" " if cur["body"] else "") + line
    if cur:
        items.append(cur)
    return items


def q(s):
    """MySQL 字符串转义"""
    s = (s or "").replace("\\", "\\\\").replace("'", "\\'")
    s = s.replace("\n", "\\n").replace("\r", "")
    return "'" + s + "'"


def main():
    items = parse_docx()
    assert len(items) == 50, len(items)
    clips = json.load(open(MANIFEST, encoding="utf-8"))["clips"]
    assert len(clips) == 50

    rows = []
    for it, clip in zip(items, clips):
        num = it["num"]
        cat, chain = CHAIN[num][0]
        kind = "image" if num == 8 else "video"
        code = "gs-%02d" % num
        title = it["title"].strip()
        prompt = re.sub(r"\s+", " ", it["body"]).strip()
        lora_json = json.dumps([[n, s] for n, s in chain], ensure_ascii=False) if chain else "[]"
        cover = "/ai/media/gs/" + (clip.get("poster") or clip.get("image") or "")
        preview = "/ai/media/gs/" + (clip.get("video") or "") if clip.get("video") else ""
        weigh = 100 - num
        rows.append(
            "(%s,%s,%s,%s,%s,%s,8,%s,%s,%d,1,NOW(),NOW())"
            % (q(code), q(title), q(prompt), q(cat), q(kind),
               q(lora_json), q(cover), q(preview), weigh)
        )

    sql = """-- ============================================
-- 50 条短视频预设（独立通道 aiShort）
-- 执行：source php/sql/upgrade_ai_short.sql   （库 zhibo / live_platform）
--
-- 设计要点：
--   1) 与现有 customVideo 完全隔离：任务用 task_type=ai_short_video，
--      现有 pending/accept/uploadVideo 都硬编码 IN('custom_video','custom_image')，
--      新任务天然不会被老 worker 捞走。
--   2) lp_ai_task.content 是 varchar(1000)，装不下长 prompt + lora_chain，
--      因此 content 只存 preset_code，正文留在本表，由 /pending 服务端 join。
--   3) 每条 = 一套提示词 + 4 层 LoRA 链 + 时长，API 用 code 精确寻址。
-- ============================================

CREATE TABLE IF NOT EXISTS `lp_short_preset` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `code`         VARCHAR(32)  NOT NULL DEFAULT '' COMMENT 'API 寻址键 gs-01..gs-50',
  `title`        VARCHAR(120) NOT NULL DEFAULT '' COMMENT '标题',
  `prompt`       TEXT         NOT NULL COMMENT '该条专属提示词(英文)',
  `category`     VARCHAR(32)  NOT NULL DEFAULT '' COMMENT 'blowjob/cowgirl/doggy/other/cumshot/solo',
  `kind`         VARCHAR(16)  NOT NULL DEFAULT 'video' COMMENT 'video|image',
  `lora_chain`   TEXT         NULL COMMENT '[[文件名,强度],...] JSON；空=仅基座',
  `duration_sec` TINYINT UNSIGNED NOT NULL DEFAULT 8 COMMENT '生成时长(秒)',
  `cover_url`    VARCHAR(500) NOT NULL DEFAULT '' COMMENT '封面 /ai/media/gs/xx.jpg',
  `preview_url`  VARCHAR(500) NOT NULL DEFAULT '' COMMENT '参考视频 /ai/media/gs/xx.mp4',
  `weigh`        INT NOT NULL DEFAULT 0 COMMENT '权重(越大越靠前)',
  `status`       TINYINT NOT NULL DEFAULT 1 COMMENT '1启用 0下架',
  `created_at`   DATETIME DEFAULT NULL COMMENT '创建时间',
  `updated_at`   DATETIME DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_status_weigh` (`status`, `weigh`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='50条短视频预设(独立通道)';

-- 任务队列索引（若已由 upgrade_face_swap.sql 建过则跳过）
SET @has_idx := (SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'lp_ai_task' AND index_name = 'idx_type_status_id');
SET @sql := IF(@has_idx = 0,
  'ALTER TABLE `lp_ai_task` ADD INDEX `idx_type_status_id` (`task_type`,`status`,`id`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 50 条种子（按 code 幂等：重复执行只更新，不重复插入）
INSERT INTO `lp_short_preset`
  (`code`,`title`,`prompt`,`category`,`kind`,`lora_chain`,`duration_sec`,`cover_url`,`preview_url`,`weigh`,`status`,`created_at`,`updated_at`)
VALUES
%s
ON DUPLICATE KEY UPDATE
  `title`=VALUES(`title`), `prompt`=VALUES(`prompt`), `category`=VALUES(`category`),
  `lora_chain`=VALUES(`lora_chain`), `cover_url`=VALUES(`cover_url`),
  `preview_url`=VALUES(`preview_url`), `weigh`=VALUES(`weigh`), `updated_at`=VALUES(`updated_at`);
""" % (",\n".join(rows),)

    open(OUT, "w", encoding="utf-8", newline="\n").write(sql)
    print("written:", OUT)
    print("presets:", len(rows))
    # 自检：引号转义后能否还原
    import ast
    for r in rows[:2]:
        print("  sample:", r[:120])


if __name__ == "__main__":
    main()
