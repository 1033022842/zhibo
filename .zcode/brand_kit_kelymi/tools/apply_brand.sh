#!/bin/bash
# apply_brand.sh — 部署时把中性站点打上客户品牌（幂等，可反复执行）
#
# 从 heygirl 版拷贝改造（结构一致）；替换规则提炼自 kelymi 服务器 2026-10-16
# 人工打品牌的实测 diff（上游 5c764de 基线 vs 服务器文件，32 处变更逐条还原）。
#
# 用法: apply_brand.sh <client> [site_dir] [repo_root]
#   client   brand/ 下的客户名（如 kelymi）
#   site_dir 站点目录（默认 /www/wwwroot/douyin/ai-girl-malaysia.com）
#   repo_root brand kit 根（默认脚本上上级目录）
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
# 订阅页 JS 用分钟级版本号：同一天多次改 JS 时也能绕开 CDN 缓存
JS_STAMP=$(date +%Y%m%d%H%M)
BAK="/tmp/brand_bak_${CLIENT}_$STAMP"
mkdir -p "$BAK"
cp -a --parents "${FILES[@]}" "$BAK/" 2>/dev/null || true

# ── 1) 文本替换（中性 → kelymi 品牌）──
# 占位保护：cdn.sugus.ai 共享 CDN 外链、sugus.ai-<hash>.svg 矢量字标文件名（内容另行覆盖）
# 规则顺序有依赖：Sugus.ai→Kelymi 要在 \bSugus\b 之前；@2x wordmark 要在 1x 之前
sed -i -E \
  -e 's/cdn\.sugus\.ai/__BRAND_CDN__/g' \
  -e 's/sugus\.ai-[0-9a-f]{16,}/__BRAND_SVG__/g' \
  -e 's#Sugus<em>\.ai</em>#kelymi<em>.ai</em>#g' \
  -e "s/apple-touch-icon-v3\.png/apple-touch-icon-kelymi-v4.png?v=$STAMP/g" \
  -e "s/sugus-favicon-v3\.png/kelymi-favicon-v4.png?v=$STAMP/g" \
  -e "s/sugus-wordmark@2x\.png/kelymi-wordmark@2x.png?v=$STAMP/g" \
  -e "s/sugus-wordmark\.png/kelymi-wordmark.png?v=$STAMP/g" \
  -e "s/sugus-logo\.png/kelymi-logo.png?v=$STAMP/g" \
  -e 's#(/assets/brand/)share_card\.png#\1share_card_kelymi.png#g' \
  -e 's#media\.sugus\.ai#media.kelymi.autos#g' \
  -e 's#(https:)?//(www\.)?sugus\.ai#https://kelymi.autos#g' \
  -e 's/support@sugusmail\.ai/support@kelymi.autos/g' \
  -e 's/Sugus\.ai/Kelymi/g' \
  -e 's/Sugus AI/Kelymi/g' \
  -e 's/sugusai/kelymiai/g' \
  -e "s/'sugus\\./'kelymi./g" \
  -e 's/sugus\\\.\[a-z.\]\+/kelymi\\.[a-z.]+/g' \
  -e "s#(js/(subscriptions|purchasePoints|crypto-pay|pending-order|brand)\.js)(\?v=[^\"]*)?#\1?v=$JS_STAMP#g" \
  -e 's/(^|[^.\/])sugus\.ai/\1kelymi.autos/g' \
  -e 's/\bSugus\b/Kelymi/g' \
  -e 's/__BRAND_CDN__/cdn.sugus.ai/g' \
  -e 's/__BRAND_SVG__/sugus.ai-65df24cd0fbc2899bc9e90b5e1af77e05e951ea0d6c3aec1b76f811040e368f6/g' \
  "${FILES[@]}"

# ── 1.5) 定价方案：两档对外名称改为 VIP / SVIP（只改页面文案，不动 data-* / class / JS 逻辑）──
# 只在 HTML 上做，js/*.js 里的 'Premium' 是逻辑判断/兜底文案，不在这里改。
for f in "$SITE"/*.html; do
  case "$f" in
    */privacy.html|*/terms.html) continue ;;
  esac
  sed -i -e 's/Premium Plus/SVIP/g' -e 's/Premium/VIP/g' "$f"
  # 人民币：HTML 里写死的 $ 价格兜底文案改 ¥（只匹配价格形态，避免误伤 jQuery $ 选择器）
  sed -i -E 's/\$([0-9]+\.[0-9]{2})/¥\1/g; s/\$([0-9]{2,3})\b/¥\1/g' "$f"
  # 单档位加购文案里的 +$6/mo / +$10/mo 也改 ¥
  sed -i -E 's/\+\$([0-9]+)/+¥\1/g' "$f"
  # Compare Plans 静态表里的 Premium Plus 月 Token 档位同步为 SVIP 1000（兼容 CRLF）
  sed -i -E '/^[[:space:]]*330\r?$/s/330/1,000/' "$f"
  # 页面级货币符号：在 </head> 前注入，保证 pending-order.js 等首屏脚本一开始就拿到 ¥
  if ! grep -q 'window.SITE_CURRENCY' "$f"; then
    sed -i '0,/<\/head>/s/<\/head>/<script>window.SITE_CURRENCY="¥";<\/script><\/head>/' "$f"
  fi
done

# 未登录也展示 VIP / SVIP 切换器，定价页两档都能看到
if [ -f "$SITE/Subscriptions.html" ]; then
  sed -i 's/html\.no-auth #sub-tier-tabs{display:none;}/html.no-auth #sub-tier-tabs{display:flex;}/' "$SITE/Subscriptions.html"
fi

# Tokens 充值页：侧栏展开宽度 199px，单独给 Kelymi 加内容左偏移（不改上游共享页面）
if [ -f "$SITE/purchasePoints.html" ] && ! grep -q 'kelymi-token-sidebar-offset' "$SITE/purchasePoints.html"; then
  sed -i 's|</head>|<style id="kelymi-token-sidebar-offset">@media (min-width:1024px){#token-container{margin-left:200px;}}</style></head>|' "$SITE/purchasePoints.html"
fi

# ── 2) 资产与清单 ──
cp -f "$BRAND"/assets/*.png "$SITE"/
cp -f "$BRAND"/assets/share_card_kelymi.png "$SITE"/assets/brand/
cp -f "$BRAND"/assets/site-manifest.json "$SITE"/site-manifest.json
# kelymi 特有：brand.js 是运行时域名切换器（kelymi.autos/live/chat/me），
# sed 改不可靠（DOMAINS 数组/DEFAULT_BRAND/正则全套联动），整文件用 kit 版覆盖。
# 注意：上游 brand.js 逻辑更新时需同步重做 kit 版（见 brand/kelymi/manifest.env 提醒）。
cp -f "$BRAND"/assets/brand.js "$SITE"/js/brand.js

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
  echo "!! 这些文件仍有 Sugus 残留（JS 标识符 SugusDialog / try_sugus_banner / cdn.sugus.ai / sugus.ai-*.svg 属正常）:"
  echo "$LEFT"
fi
echo "== [$CLIENT] 完成。抽查: =="
grep -oE '<title>[^<]{0,50}' Home.html | head -1 || true
grep -oE 'class="logo">[a-zA-Z]+<em>[a-zA-Z.]+' Home.html | head -1 || true
