# 角色预览素材 catalog-v1（Female 2 画风 × 6 族裔 + Male 同构）

生成时间：2026-11-21 18:10 (+08:00)
素材根：`ai-girl-malaysia.com/assets/create/`
manifest：`ai-girl-malaysia.com/assets/create/manifest.json`（path 相对本文件所在目录）

## 1. 结论

| 项 | 结果 |
| --- | --- |
| manifest key 数 | 684（5 段 key，全部带 ethnicity；含 120 条族裔卡选择器别名 + 48 条 male chest 别名，均为显式映射，非顶替） |
| 覆盖组合 | Female 2 画风 × 6 族裔 × 5 组；Male 2 画风 × 6 族裔 × 4 组 + chest 4 值（别名到 body；无 butt） |
| 明确缺口 | 2 条：`male|entry|Realistic`、`male|entry|Anime`（男入口图暂缺） |
| 图片尺寸 | 515 张 320×480，1 张 320×428（female Realistic black body，参考站原图即此比例） |
| 磁盘 | catalog-v1 共 516 webp，约 7.98 MB（未压缩字节和），去重后 456 张不同图片 |
| 完整/可解码/sha | 516/516 通过；0 破图、0 孤儿文件、0 sha 不符 |
| 跨画风是否不同图 | 是，Anime 与 Realistic 同 option 全部不同 sha |
| 首图 | `entry-realistic.webp`、`entry-anime.webp` 未改动（sha256 与 git HEAD 完全一致） |

## 2. manifest 结构（前端按此解析）

```
{
  "version": "catalog-v1-20261121",
  "root": "./assets/create",
  "entry": { "female": { "Realistic": "entry-realistic.webp", "Anime": "entry-anime.webp" }, "male": {} },
  "options": { "female|Realistic": { "ethnicity": [...6], "body": [...5], "breast": [...5], "butt": [...5], "hairstyle": [...8] }, ... },
  "alias_groups": { "chest": {"slim":"body|slim","toned":"body|average","muscular":"body|muscular","broad":"body|stout"} },
  "aliases": { "breast": {"medium":"average","large":"big","xl":"huge"}, ... },
  "assets": { "female|Realistic|white|ethnicity|white": { "path","w","h","bytes","sha256" }, ... },
  "shared_reference_assets": [ ... 15 组参考站自身复用 ... ],
  "missing": [ "male|entry|Realistic", "male|entry|Anime" ],
  "reference_evidence": [ { "file": "female-realistic-white-s2.json", "unique_images": 30 }, ... ]
}
```

- key 固定 5 段：`gender|style|ethnicity|group|option`；没有无 ethnicity 的旧 key。
- 组图路径：`catalog-v1/{gender}/{style}/{eth}/{group}-{option}.webp`；族裔卡：`catalog-v1/{gender}/{style}/eth-{eth}.webp`。
- 颜色卡不出图（前端 CSS 色块），`color_swatches` 给出参考站七个颜色值。

## 3. 覆盖矩阵（按 key 实测，非推断）

| gender | style | ethnicity | body | breast | butt | hairstyle | ethnicity 卡 |
| --- | --- | --- | --- | --- | --- | --- | --- |
| female | Realistic | 6 | 5 | 5 | 5 | 8 | 6 |
| female | Anime | 6 | 5 | 5 | 5 | 8 | 6 |
| male | Realistic | 6 | 5 | 5 | — | 8 | 6（另 chest 4） |
| male | Anime | 6 | 5 | 5 | — | 8 | 6（另 chest 4） |

- Female = 2×6×(5+5+5+8) + 12 族裔卡 = 288 key；加 60 条族裔选择器别名 = 348 key。
- Male = 2×6×(5+5+8) + 12 族裔卡 = 228 key；加 60 条族裔选择器别名 + 48 条 chest 别名 = 336 key。
- Male 的 `breast` 是参考站尺寸组（Micro/Small/Average/Big/Huge）。
- Male **没有 butt 步骤**：`male-{style}-white-s2.json` 的 25 张卡 alt 只覆盖 color 7 + hairstyle 8 + body 5（Slim/Average/Muscular/Stout/Large）+ 尺寸 5（Micro/Small/Average/Big/Huge），不存在 butt 的 Small/Slim/Athletic/Big/Huge 行。manifest 用 `options["male|…"].butt = []` + `not_in_reference: ["male|butt"]` 显式标注“不适用”，前端应隐藏该卡而不是当缺图 pending。
- Male `chest` 是前端 UI 组（参考站没有 chest 组），manifest 里按 `alias_groups.chest` 显式映射到参考站 body 图：`slim→body|slim`、`toned→body|average`、`muscular→body|muscular`、`broad→body|stout`；4 个选项每个族裔/画风都有独立 key，路径指向对应 body 文件。
- 族裔卡兼容键：前端若用“当前已选族裔”拼 key（如 `female|Realistic|black|ethnicity|south-asian`），manifest 也提供全部 6×6 选择器组合，统一指向 `option` 对应的那张族裔卡；卡面永远是 option 指定的族裔，不是当前选择。

## 4. Middle Eastern 专用卡

4 张 Middle Eastern 族裔卡全部为参考站该族裔实际渲染，与其他 5 族同画风卡 sha256 均不同：

- `female|Realistic|middle-eastern|ethnicity|middle-eastern` → `catalog-v1/female/realistic/eth-middle-eastern.webp` sha `ab5ef290…`
- `female|anime|middle-eastern|ethnicity|middle-eastern` → `…/female/anime/eth-middle-eastern.webp` sha `27baad02…`
- `male|Realistic|middle-eastern|…` sha `f7cf5828…`；`male|anime|middle-eastern|…` sha `0deb95fa…`

即：不是把白人卡调色顶替，是参考站该族裔的独立卡。

## 5. 首图未变证明

```
$ git status --porcelain ai-girl-malaysia.com/assets/create/entry-*.webp
（无输出）

$ sha256sum ai-girl-malaysia.com/assets/create/entry-realistic.webp ai-girl-malaysia.com/assets/create/entry-anime.webp
dc1c61900784b5bd36440b35ebee5337140e4bc5c973b620e2b9974f9f4bf65f  entry-realistic.webp
fa0cf9032ac6c9c7094b549a2a84a5c396d79afa56d48a6e657902b99135329e  entry-anime.webp

$ git show HEAD:…/entry-realistic.webp | sha256sum   → dc1c6190…（一致）
$ git show HEAD:…/entry-anime.webp    | sha256sum   → fa0cf903…（一致）
```

## 6. 证据来源与“非占位/非预载”判定

- 取证方式：打开参考站公开角色页 `https://joi.com/generate/character?g={gender}&s={style}&e={eth}&step={step}`，点公开页 18+ 门（非登录、非验证码、非付费墙），横向/纵向滚动触发懒加载后读取 `<img>` 的 image id、alt、自然尺寸与位置；不下载页面脚本、不改请求。
- 排除规则：跳过 `variant` 含 `width=32` 的缩略/预载；同 id 取 `naturalWidth` 最大的一份；只收有 alt 且自然尺寸 320×480 的卡片。
- 证据 JSON：`tests/create-character/assets/evidence/ref/{gender}-{style}-{eth}-s{step}.json`，共 28 份（female 14、male 14）。
- 下载：`https://imagedelivery.net/XCC9OTLycTbXl7Ir7oXy0g/{id}/width=320,quality=85,format=auto`（返回 JPEG），本地 PIL 转 webp q84。参考站素材未热链进正式页面，全部落本地 `assets/create/catalog-v1/`。

## 7. 局限 / 需产品决策

1. **Male 入口图缺（当前交付的最终状态）**：`entry.male` 为空，manifest `missing` 已列两条；前端应显示中性入口占位，不要拿 female 入口顶。本轮只取证参考站 step 1/2（族裔卡与组图），入口大卡属另一类素材，未纳入 catalog-v1；如需补齐需另做一次入口取证（会新增 `catalog-v1/male/entry-*.webp`，不动 female 首图）。
1b. **Male butt 不适用**：参考站男向导无 butt 步骤（见上），不是缺图。
2. **Female Realistic 的 body/breast/butt 在参考站自身复用同一张图**：6 个族裔里 5 个非黑族裔返回字节完全相同的图（仅 black 是另一张）。15 个 option 组因此只有 2 张不同图，已写入 `shared_reference_assets`。这不是我方跨族裔顶替，但也无法满足“同 option 跨族裔视觉不同”的验收项；如产品要求差异，只能另行生成（参考站没有可抓的差异素材）。
3. Female Anime 与 Male 两种画风的 body/breast/butt/hairstyle 均为 6 族裔 6 张不同图，hairstyle 全部 6 张不同。
4. 只取证了参考站 step 1（族裔卡）与 step 2（组图）；step 3/4 的选项命名若与 step 2 不同，需要再补一次取证。
5. 素材是 320×480 的 CDN 预览尺寸；正式页按卡片实际显示尺寸使用，不放大。

## 8. 复现命令

```bash
# 取证（会新开无头浏览器，点公开 18+ 门）
node tests/create-character/assets/tools/capture_ref.cjs female Realistic middle-eastern 2
node tests/create-character/assets/tools/capture_ref.cjs male anime black 2

# 生成 manifest + 下载/转换素材（幂等，已存在则复用）
python tests/create-character/assets/tools/build_catalog.py

# 离线校验：存在/解码/尺寸/sha/重复/跨族裔/跨画风/入口/孤儿
python tests/create-character/assets/tools/verify_assets.py
# → tests/create-character/assets/report/asset-verify.json
```

最近一次校验输出：`keys: 684 | distinct images: 456 | catalog files: 516 | orphan: 0 | duplicate byte groups: 0 | cross-ethnicity issues: 0 | cross-style issues: 0 | problems: 0`（chest 与族裔选择器别名组在重复检查中按其显式映射声明豁免；族裔卡仍逐 option 校验 6 张不同 sha）。

## 9. 改动文件

- 新增 `ai-girl-malaysia.com/assets/create/manifest.json`
- 新增 `ai-girl-malaysia.com/assets/create/catalog-v1/**`（516 个 webp）
- 新增 `tests/create-character/assets/tools/{capture_ref.cjs,build_catalog.py,verify_assets.py}`
- 新增 `tests/create-character/assets/evidence/ref/*.json`（28 份）
- 新增 `tests/create-character/assets/report/{asset-verify.json,probe/*.jpg}`
- 未改：入口图、`createCharacter.html/js/css`、同步脚本、`_clone_probe`
