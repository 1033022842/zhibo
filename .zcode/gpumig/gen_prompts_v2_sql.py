#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""把新一版 I2VA 提示词（With Quality Constraint）写入 lp_short_preset。

原提示词问题（S4 验收发现）：
  1. 每条都写 "Single female character only"，但 cowgirl/doggy/missionary 本身需要 partner，
     描述自相矛盾 → 抽查发现 gs-03/gs-29/gs-46 出现多余人物。
  2. 用大段文字描述外貌，模型会自由发挥 → 画面与参考图人物不一致。
新提示词：
  - 统一用 "<Picture 1>" 引用参考图，不描述外貌
  - 官方 I2VA 结构：reference 声明 + integrated_multimodal_description
    + overall_soundscape + non_diegetic_music
  - 末尾带解剖质量约束（手指数量、面部一致性）

输出 php/sql/upgrade_ai_short_prompts_v2.sql，幂等（按 code UPDATE）。
"""
import json
import os

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
SRC = os.path.join(ROOT, ".zcode", "_docs", "new_prompts.json")
OUT = os.path.join(ROOT, "php", "sql", "upgrade_ai_short_prompts_v2.sql")


def q(s):
    s = (s or "").replace("\\", "\\\\").replace("'", "\\'")
    s = s.replace("\n", "\\n").replace("\r", "")
    return "'" + s + "'"


def main():
    items = json.load(open(SRC, encoding="utf-8"))
    assert len(items) == 50, len(items)

    rows = []
    for it in items:
        code = "gs-%02d" % it["num"]
        # 按官方 I2VA 结构拼成单条 prompt
        prompt = (
            (it.get("ref") or "").strip()
            + "\n\nintegrated_multimodal_description: " + (it.get("desc") or "").strip()
            + "\n\noverall_soundscape: " + (it.get("sound") or "N/A").strip()
            + "\n\nnon_diegetic_music: " + (it.get("music") or "N/A").strip()
        )
        rows.append((code, it["title"].strip(), prompt))

    sql = """-- ============================================
-- 50 条预设：替换为新版 I2VA 提示词（With Quality Constraint）
-- 执行：source php/sql/upgrade_ai_short_prompts_v2.sql
--
-- 背景：S4 验收发现原提示词有三个问题
--   1. 每条写死 "Single female character only"，但 cowgirl/doggy/missionary 需要 partner，
--      自相矛盾 → 画面里出现多余人物（gs-03/gs-29/gs-46 抽查确认）
--   2. 用大段文字描述外貌 → 模型自由发挥，人物与参考图不一致
--   3. 缺解剖约束 → 手指/面部畸变
-- 新版：<Picture 1> 引用 + 官方 I2VA 四段结构 + 末尾质量约束。
--
-- 只改 prompt（以及 gs-08 的 kind），lora_chain 保持不动，便于隔离变量对比。
-- ============================================

"""

    # gs-08 改回视频：新提示词是视频描述（"slowly removes clothing, turns, arches her back..."），
    # 走 qwenedit 图片编辑工作流会产出无关内容（S4 实测：举牌人像）
    sql += "UPDATE `lp_short_preset` SET `kind`='video', `updated_at`=NOW() WHERE `code`='gs-08';\n\n"

    for code, title, prompt in rows:
        sql += (
            "UPDATE `lp_short_preset` SET `prompt`=%s, `updated_at`=NOW() WHERE `code`='%s';\n"
            % (q(prompt), code)
        )

    sql += """
-- 自检：应输出 50，且 prompt 都包含 integrated_multimodal_description
SELECT COUNT(*) AS total,
       SUM(`prompt` LIKE '%integrated_multimodal_description%') AS with_i2va,
       SUM(`prompt` LIKE '%<Picture 1>%') AS with_picture_ref
FROM `lp_short_preset` WHERE `status`=1;
"""
    open(OUT, "w", encoding="utf-8", newline="\n").write(sql)
    print("written:", OUT)

    # 统计
    lens = [len(r[2]) for r in rows]
    print("prompt 长度 min/avg/max: %d / %.0f / %d" % (min(lens), sum(lens) / len(lens), max(lens)))
    print("样例 gs-01:")
    print(rows[0][2][:500])


if __name__ == "__main__":
    main()
