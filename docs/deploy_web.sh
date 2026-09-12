#!/bin/bash
# ============================================================
# 172 一键部署：从 gitee 拉取最新代码构建上线
# 用法: /root/deploy/deploy_web.sh [all|vue|php|admin|static|worker]
#   all    = 全部（默认）
#   vue    = 只更新直播间 SPA（构建+发布）
#   php    = 只更新 php 后端（含 admin 后端逻辑）
#   admin  = 只重建管理后台前端（php/web → admin/dist）
#   static = 只更新 ai-girl / ai_web 静态站
#   worker = 只更新推流 worker 并重启
# 说明: 不动 MySQL/Redis/SRS/mediamtx；安全，可重复执行
# ============================================================
set -e
REPO=/root/deploy/zhibo
TARGET=/www/wwwroot/douyin
REMOTE=origin
BRANCH=main
export GIT_SSH_COMMAND='ssh -i /root/.ssh/gitee_deploy -o StrictHostKeyChecking=accept-new'
ACTION=${1:-all}

echo "==> 拉取 gitee/$BRANCH 最新代码"
cd $REPO
git fetch origin $BRANCH
git reset --hard origin/$BRANCH
echo "    当前提交: $(git log --oneline -1)"

rsync_php() {
  echo "==> 同步 php 后端"
  # 注意：vendor 不在仓库内，禁止对 php 使用 --delete
  rsync -a \
    --exclude 'runtime/' \
    --exclude 'web/node_modules/' \
    --exclude 'web/dist/' \
    $REPO/php/ $TARGET/php/
  mkdir -p $TARGET/php/runtime
  # rsync 会带 repo 的 root 属主，运行时目录必须还给 www-data（否则 debug.log/上传/缓存全炸）
  rm -f $TARGET/php/public/debug.log
  chown -R www-data:www-data $TARGET/php/public $TARGET/php/runtime
  systemctl reload php8.3-fpm
}

build_vue() {
  echo "==> 构建直播间 SPA"
  rsync -a --delete \
    --exclude 'node_modules/' \
    --exclude 'dist/' \
    --exclude '.env.local' \
    $REPO/vue/ $TARGET/vue/
  cd $TARGET/vue
  npm install --no-audit --no-fund --legacy-peer-deps
  npx vite build
}

build_admin() {
  echo "==> 构建管理后台前端"
  cd $TARGET/php/web
  npm install --no-audit --no-fund --legacy-peer-deps
  npm run build
  rsync -a --delete $TARGET/php/web/dist/ $TARGET/admin/dist/
}

sync_static() {
  echo "==> 同步 ai-girl / ai_web 静态站"
  rsync -a --delete $REPO/ai-girl-malaysia.com/ $TARGET/ai-girl-malaysia.com/
  rsync -a --delete $REPO/ai_web/ $TARGET/ai_web/
}

deploy_worker() {
  echo "==> 更新推流 worker"
  cp $REPO/services/channel-worker/python/worker.py /opt/channel-worker/worker.py
  for i in 1 $(seq 17 26); do systemctl restart live-worker@$i; done
  sleep 3
  echo "    worker 状态: $(for i in 1 $(seq 17 26); do printf '%s:%s ' $i $(systemctl is-active live-worker@$i); done)"
}

case $ACTION in
  vue)    build_vue ;;
  php)    rsync_php ;;
  admin)  build_admin ;;
  static) sync_static ;;
  worker) deploy_worker ;;
  all)
    rsync_php
    sync_static
    build_vue
    build_admin
    deploy_worker
    ;;
  *) echo "用法: $0 [all|vue|php|admin|static|worker]"; exit 1 ;;
esac

echo "==> 完成 $(date '+%F %T')"
