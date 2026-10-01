#!/bin/sh
# 临时：看 Game.html 渲染前后的可见文案，判断它到底是不是「成就」页
BASE=http://sugus.it.com:8082
curl -s -k -m 10 -o /tmp/_game.html "$BASE/Game.html"
echo "size: $(wc -c < /tmp/_game.html)"

echo ""
echo "===== 可见文本片段（前 40 条）====="
grep -oE '>[^<>]{3,60}<' /tmp/_game.html \
  | sed 's/^>//; s/<$//' \
  | sed 's/^[[:space:]]*//; s/[[:space:]]*$//' \
  | grep -vE '^[[:space:]]*$' \
  | head -40

echo ""
echo "===== 是否有 body 容器 / 空壳迹象 ====="
for k in 'id="app"' 'data-testid' 'js/game.js' 'ach-badge' 'Achievement' '成就' 'Game'; do
  printf '%s = %s\n' "$k" "$(grep -o "$k" /tmp/_game.html | wc -l)"
done

rm -f /tmp/_game.html
