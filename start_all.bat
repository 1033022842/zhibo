@echo off
title Start All Services
set "PHP=d:\phpstudy_pro\Extensions\php\php8.2.9nts\php.exe"
set "ROOT=d:\phpstudy_pro\WWW\douyin"

echo ========================================
echo    Starting All Services
echo ========================================
echo.

echo [0] Clean up stale processes...
taskkill /F /FI "WINDOWTITLE eq PHP-API*" 2>nul
taskkill /F /FI "WINDOWTITLE eq WebSocket*" 2>nul
taskkill /F /FI "WINDOWTITLE eq ChannelWorker*" 2>nul
taskkill /F /FI "WINDOWTITLE eq Vue*" 2>nul
taskkill /F /FI "WINDOWTITLE eq AI-Girl*" 2>nul
taskkill /F /FI "WINDOWTITLE eq MediaMTX*" 2>nul
taskkill /F /FI "WINDOWTITLE eq MediaServer*" 2>nul
echo       Done
echo.

echo [1/10] Starting PHP API (port 8001)...
cd /d %ROOT%\php
start "PHP-API-8001" %PHP% -S 127.0.0.1:8001 -t public public/router.php
echo        OK

echo [2/10] Starting WebSocket (ports 8787/8788)...
cd /d %ROOT%\apps\ws-webman
start "WebSocket-8788" %PHP% windows.php start
echo        OK

echo [3/10] Starting MediaMTX (port 8889/1936)...
cd /d %ROOT%\services\mediamtx
start "MediaMTX-8889" mediamtx.exe
echo        OK

echo [4/10] Starting Node Media Server (port 8002)...
cd /d %ROOT%\media-server
start "MediaServer-8002" node server.js
echo        OK

echo [5/10] Starting Vue Frontend (port 3000)...
cd /d %ROOT%\vue
start "Vue-3000" npx vite --host 0.0.0.0 --port 3000
echo        OK

echo [6/10] Starting AI Girlfriend (port 8003)...
cd /d %ROOT%\ai-girl-malaysia.com
start "AI-Girl-8003" %PHP% -S 127.0.0.1:8003
echo        OK

echo.
echo Waiting 8s for base services to be ready...
timeout /t 8 /nobreak >nul

echo [7/10] Starting Channel Worker (room 5)...
cd /d %ROOT%\services\channel-worker
set FFMPEG_BIN=%ROOT%\services\channel-worker\bin\ffmpeg-tools\ffmpeg-8.1.2-essentials_build\bin\ffmpeg.exe
set DB_HOST=127.0.0.1
set DB_PORT=3306
set DB_NAME=live_platform
set DB_USER=root
set DB_PASSWORD=root
set DB_CHARSET=utf8mb4
set REDIS_HOST=127.0.0.1
set REDIS_PORT=6379
start "ChannelWorker-Room5" %PHP% bin\channel-worker.php --room=5
echo        OK

echo [8/10] Starting Channel Worker (room 6)...
set FFMPEG_BIN=%ROOT%\services\channel-worker\bin\ffmpeg-tools\ffmpeg-8.1.2-essentials_build\bin\ffmpeg.exe
set DB_HOST=127.0.0.1
set DB_PORT=3306
set DB_NAME=live_platform
set DB_USER=root
set DB_PASSWORD=root
set DB_CHARSET=utf8mb4
set REDIS_HOST=127.0.0.1
set REDIS_PORT=6379
start "ChannelWorker-Room6" %PHP% bin\channel-worker.php --room=6
echo        OK

echo [9/10] Starting Channel Worker (room 7)...
set FFMPEG_BIN=%ROOT%\services\channel-worker\bin\ffmpeg-tools\ffmpeg-8.1.2-essentials_build\bin\ffmpeg.exe
set DB_HOST=127.0.0.1
set DB_PORT=3306
set DB_NAME=live_platform
set DB_USER=root
set DB_PASSWORD=root
set DB_CHARSET=utf8mb4
set REDIS_HOST=127.0.0.1
set REDIS_PORT=6379
start "ChannelWorker-Room7" %PHP% bin\channel-worker.php --room=7
echo        OK

echo [10/10] Starting Channel Worker (room 8)...
set FFMPEG_BIN=%ROOT%\services\channel-worker\bin\ffmpeg-tools\ffmpeg-8.1.2-essentials_build\bin\ffmpeg.exe
set DB_HOST=127.0.0.1
set DB_PORT=3306
set DB_NAME=live_platform
set DB_USER=root
set DB_PASSWORD=root
set DB_CHARSET=utf8mb4
set REDIS_HOST=127.0.0.1
set REDIS_PORT=6379
start "ChannelWorker-Room8" %PHP% bin\channel-worker.php --room=8
echo        OK

echo.
echo ========================================
echo    All services started!
echo.
echo    Frontend:          http://localhost:3000
echo    AI Girlfriend:     http://localhost:8003
echo    API:               http://localhost:8001
echo    WebSocket:         ws://localhost:8788
echo    MediaMTX WebRTC:   http://localhost:8889
echo ========================================
echo.
echo Use stop_all.bat to kill all services.
pause
