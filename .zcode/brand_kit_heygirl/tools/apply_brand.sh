#!/bin/bash
# apply_brand.sh — 部署时把中性站点打上客户品牌（幂等，可反复执行）
#
# 背景：见 docs/多客户品牌隔离与上游同步方案.md
#   仓库业务文件保持与上游(Sugus)一致的中性基线；品牌差异全部收敛在
#   brand/<client>/（资产 + 本脚本的正则规则），部署最后一步打上。
#   上游 merge 因此零品牌冲突。
#
# 用法: apply_brand.sh <client> [site_dir] [repo_root]
#   client   brand/ 下的客户名（如 heygirl）
#   site_dir 站点目录（默认 /www/wwwroot/douyin/ai-girl-malaysia.com）
#   repo_root 仓库根（默认脚本上上级目录；服务器上可用 git 检出后的 tools/）
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

# ── 1) 文本替换（中性 → 品牌）──
# 占位保护：cdn.sugus.ai 外链、sugus.ai-<hash>.svg 资源文件名（内容另行覆盖）
# 归一化规则（heygirl.Ai -> heygirl.live）保证脚本幂等：对已打过旧品牌的目录重跑也生效
sed -i -E \
  -e 's/cdn\.sugus\.ai/__BRAND_CDN__/g' \
  -e 's/sugus\.ai-[0-9a-f]{16,}/__BRAND_SVG__/g' \
  -e 's#heygirl<em>\.Ai</em>#heygirl<em>.live</em>#g' \
  -e 's/heygirl\.Ai/heygirl.live/g' \
  -e 's#Sugus<em>\.ai</em>#heygirl<em>.live</em>#g' \
  -e "s/apple-touch-icon-v3\.png/apple-touch-icon-heygirl-v4.png?v=$STAMP/g" \
  -e "s/sugus-favicon-v3\.png/heygirl-favicon-v4.png?v=$STAMP/g" \
  -e 's/sugus-wordmark@2x\.png/heygirl-wordmark@2x.png/g' \
  -e 's/sugus-wordmark\.png/heygirl-wordmark.png/g' \
  -e 's/icon-192-v3\.png/icon-192-heygirl.png/g' \
  -e 's#(/assets/brand/)share_card\.png#\1share_card_heygirl.png#g' \
  -e 's#media\.sugus\.ai#media.heygirl.live#g' \
  -e 's#https://(www\.)?sugus\.ai#https://heygirl.live#g' \
  -e 's#https://(www\.)?candy\.ai#https://heygirl.live#g' \
  -e 's/Candy AI/heygirl.live/g' \
  -e 's/support@sugusmail\.ai/support@heygirl.live/g' \
  -e 's/Sugus\.ai/heygirl.live/g' \
  -e 's/Sugus AI/heygirl AI/g' \
  -e 's/\bSugus\b/heygirl/g' \
  -e 's/(^|[^.\/])sugus\.ai/\1heygirl.live/g' \
  -e 's/__BRAND_CDN__/cdn.sugus.ai/g' \
  -e 's/__BRAND_SVG__/sugus.ai-65df24cd0fbc2899bc9e90b5e1af77e05e951ea0d6c3aec1b76f811040e368f6/g' \
  "${FILES[@]}"

# ── 2) 资产与清单 ──
cp -f "$BRAND"/assets/*.png "$SITE"/
cp -f "$BRAND"/assets/share_card_heygirl.png "$SITE"/assets/brand/
cp -f "$BRAND"/assets/site-manifest.json "$SITE"/site-manifest.json
# 矢量字标：文件名保持中性（sugus.ai-*.svg），内容替换为 heygirl 版
if [ -f "$BRAND"/assets/wordmark.svg ]; then
  find "$SITE" -name 'sugus.ai-*.svg' -exec cp -f "$BRAND"/assets/wordmark.svg {} \;
fi

# ── 3.5) 静态资源改走 CDN（可选；由 brand/<client>/cdn_assets.env 的 CDN_ASSET_BASE 控制）──
# 只改 HTML 里的相对资源引用，页面跳转（*.html）、favicon 根级小图、/api/ /ws/ 均不动——
# 因此同源会话（localStorage JWT）不受影响，只有 js/css/图片从 CDN 边缘加载。
# 资源名自带 hash 或 ?v=，可长缓存；源站仍是本机，CDN 未命中时回源。
if [ -f "$BRAND/cdn_assets.env" ]; then
  # shellcheck disable=SC1090
  . "$BRAND/cdn_assets.env"
  if [ -n "${CDN_ASSET_BASE:-}" ]; then
    CDN_ASSET_BASE=${CDN_ASSET_BASE%/}
    echo "== 静态资源改走 CDN: $CDN_ASSET_BASE =="
    sed -i -E \
      -e "s#(src|href)=\"\./(js|_shared|assets)/#\1=\"$CDN_ASSET_BASE/\2/#g" \
      -e "s#(src|href)=\"\./([A-Za-z0-9_-]+_files)/#\1=\"$CDN_ASSET_BASE/\2/#g" \
      -e "s#url\((['\"]?)\./(assets|_shared)/#url(\1$CDN_ASSET_BASE/\2/#g" \
      "${FILES[@]}"
    echo "   剩余相对资源引用（应为 0）: $(grep -ohE '(src|href)=\"\./(js|_shared|assets)/' "${FILES[@]}" 2>/dev/null | wc -l)"
  fi
fi

# ── 3) 自检 ──
LEFT=$(grep -lE 'Sugus|sugusmail' "${FILES[@]}" 2>/dev/null | grep -vE 'js/' || true)
if [ -n "$LEFT" ]; then
  echo "!! 这些文件仍有 Sugus 残留（JS 标识符 SugusDialog/SugusTrack 属正常）:"
  echo "$LEFT"
fi
echo "== [$CLIENT] 完成。抽查: =="
grep -oE '<title>[^<]{0,50}' Home.html | head -1 || true
grep -oE 'class="logo">[a-zA-Z]+<em>[a-zA-Z.]+' Home.html | head -1 || true
