#!/bin/bash
# apply_brand.sh — 部署时把中性站点打上 kiss/kisss 品牌（幂等，可反复执行）
#
# 从 kelymi 版拷贝改造（结构一致）；kiss 域名 kisss.ai（解析中），暂以 103.217.252.52 访问。
# 额外职责：每次同步后自动砍掉「换脸」前台入口（页面 / 导航 / 推广弹窗 CTA）。
#
# 用法: apply_brand.sh <client> [site_dir] [repo_root]
set -euo pipefail

CLIENT=${1:?用法: apply_brand.sh <client> [site_dir] [repo_root]}
SITE=${2:-/www/wwwroot/douyin/ai-girl-malaysia.com}
ROOT=${3:-$(cd "$(dirname "$0")/.." && pwd)}
BRAND="$ROOT/brand/$CLIENT"
[ -d "$BRAND" ] || { echo "!! 未知客户 $CLIENT（$BRAND 不存在）"; exit 1; }

cd "$SITE"
mapfile -t FILES < <(ls *.html js/*.js 2>/dev/null | grep -vE '^(privacy|terms)\.html$')
[ ${#FILES[@]} -gt 0 ] || { echo "!! 站点目录没有可处理文件: $SITE"; exit 1; }

echo "== [$CLIENT] 应用品牌到 $SITE =="
STAMP=$(date +%Y%m%d)
BAK="/tmp/brand_bak_${CLIENT}_$STAMP"
mkdir -p "$BAK"
cp -a --parents "${FILES[@]}" "$BAK/" 2>/dev/null || true

# ── 1) 文本替换（中性 Sugus → kisss）──
# 占位保护：cdn.sugus.ai 共享 CDN 外链、sugus.ai-<hash>.svg 矢量字标文件名（内容另行覆盖）
# 规则顺序有依赖：Sugus.ai→Kisss 要在 \bSugus\b 之前；@2x wordmark 要在 1x 之前
sed -i -E \
  -e 's/cdn\.sugus\.ai/__BRAND_CDN__/g' \
  -e 's/sugus\.ai-[0-9a-f]{16,}/__BRAND_SVG__/g' \
  -e 's#Sugus<em>\.ai</em>#kisss<em>.ai</em>#g' \
  -e "s/apple-touch-icon-v3\.png/apple-touch-icon-kisss-v4.png?v=$STAMP/g" \
  -e "s/sugus-favicon-v3\.png/kisss-favicon-v4.png?v=$STAMP/g" \
  -e "s/sugus-wordmark@2x\.png/kisss-wordmark@2x.png?v=$STAMP/g" \
  -e "s/sugus-wordmark\.png/kisss-wordmark.png?v=$STAMP/g" \
  -e "s/sugus-logo\.png/kisss-logo.png?v=$STAMP/g" \
  -e 's#(/assets/brand/)share_card\.png#\1share_card_kisss.png#g' \
  -e 's#(https?:)?//(www\.)?sugus\.ai#https://kisss.ai#g' \
  -e 's/support@sugusmail\.ai/support@kisss.ai/g' \
  -e 's/Sugus\.ai/Kisss/g' \
  -e 's/Sugus AI/Kisss/g' \
  -e 's/sugusai/kisssai/g' \
  -e "s/'sugus\\./'kisss./g" \
  -e 's/sugus\\\.\[a-z.\]\+/kisss\\.[a-z.]+/g' \
  -e 's/(^|[^.\/])sugus\.ai/\1__BRAND_HOST__/g' \
  -e 's/__BRAND_HOST__/kisss.ai/g' \
  -e 's/\bSugus\b/Kisss/g' \
  -e 's/__BRAND_CDN__/cdn.sugus.ai/g' \
  -e 's/__BRAND_SVG__/sugus.ai-65df24cd0fbc2899bc9e90b5e1af77e05e951ea0d6c3aec1b76f811040e368f6/g' \
  "${FILES[@]}"

# ── 2) 资产与清单 ──
cp -f "$BRAND"/assets/*.png "$SITE"/
cp -f "$BRAND"/assets/share_card_kisss.png "$SITE"/assets/brand/
cp -f "$BRAND"/assets/site-manifest.json "$SITE"/site-manifest.json
cp -f "$BRAND"/assets/brand.js "$SITE"/js/brand.js

# ── 3) kiss 特有：砍掉换脸前台入口（仅前台，后端路由/后台菜单保留）──
rm -f "$SITE"/face_swap.html "$SITE"/js/face_swap.js
sed -i '/face_swap\.html/d' "$SITE"/js/site-shell.js "$SITE"/js/promo-popup.js 2>/dev/null || true

# ── 4) 自检 ──
LEFT=$(grep -lE 'Sugus|sugusmail' "${FILES[@]}" 2>/dev/null | grep -vE 'js/' || true)
if [ -n "$LEFT" ]; then
  echo "!! 这些文件仍有 Sugus 残留（JS 标识符 SugusDialog / try_sugus_banner / cdn.sugus.ai / sugus.ai-*.svg 属正常）:"
  echo "$LEFT"
fi
echo "== [$CLIENT] 完成。抽查: =="
grep -oE '<title>[^<]{0,50}' Home.html | head -1 || true
grep -oE 'class="logo">[a-zA-Z]+<em>[a-zA-Z.]+' Home.html | head -1 || true
echo "-- 换脸入口检查（应无输出）:"
grep -rn 'face_swap' "$SITE"/js/site-shell.js "$SITE"/js/promo-popup.js 2>/dev/null || true
