# AI 直播平台一键启动脚本
# 启动 5 个服务窗口

$root = "d:\phpstudy_pro\WWW\douyin"

# 窗口1: ThinkPHP 后端 API (8001)
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$root\php'; Write-Host '=== ThinkPHP 后端 (8001) ===' -ForegroundColor Green; php think run -H 127.0.0.1 -p 8001"

# 窗口2: WebSocket 实时服务 (8788)
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$root\apps\ws-webman'; Write-Host '=== WebSocket (8788) ===' -ForegroundColor Green; php windows.php"

# 窗口3: Vue H5 前端 (3000)
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$root\vue'; Write-Host '=== Vue H5 前端 (3000) ===' -ForegroundColor Green; pnpm dev"

# 窗口4: AI 女友前端 (8080)
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$root\ai-girl-malaysia.com'; Write-Host '=== AI 前端 (8080) ===' -ForegroundColor Green; npx serve -p 8080"

# 窗口5: 管理后台前端 (1818)
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$root\php\web'; Write-Host '=== 管理后台 (1818) ===' -ForegroundColor Green; pnpm dev"

Write-Host "`n已启动 5 个服务窗口！" -ForegroundColor Cyan
Write-Host "  ThinkPHP API : http://127.0.0.1:8001" -ForegroundColor White
Write-Host "  WebSocket    : ws://127.0.0.1:8788" -ForegroundColor White
Write-Host "  Vue 前端     : http://localhost:3000" -ForegroundColor White
Write-Host "  AI 前端      : http://127.0.0.1:8080" -ForegroundColor White
Write-Host "  管理后台     : http://localhost:1818" -ForegroundColor White
Write-Host "`n按任意键关闭此窗口..." -ForegroundColor Gray
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
