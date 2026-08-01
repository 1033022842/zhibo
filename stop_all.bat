@echo off
title Stop All Services

echo ========================================
echo    Stopping All Services
echo ========================================
echo.

:: 按端口杀进程（比窗口标题更可靠）
echo [1] Stopping by port...
for %%p in (8001 8002 8003 8787 8788 3000 8889 1936) do (
    for /f "tokens=5" %%a in ('netstat -ano ^| findstr ":%%p.*LISTENING" 2^>nul') do (
        taskkill /F /PID %%a 2>nul
    )
)

echo [2] Killing ffmpeg...
taskkill /F /IM ffmpeg.exe 2>nul

echo [3] Killing channel workers...
taskkill /F /FI "WINDOWTITLE eq ChannelWorker*" 2>nul

echo [4] Killing mediamtx...
taskkill /F /IM mediamtx.exe 2>nul

echo [5] Killing lingering PHP...
taskkill /F /IM php.exe 2>nul

echo.
echo ========================================
echo    All services stopped.
echo ========================================
pause
