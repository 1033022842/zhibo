@echo off
:: 注意：不再杀掉所有 ffmpeg，只跑诊断
D:\phpstudy_pro\Extensions\php\php8.2.9nts\php.exe d:\phpstudy_pro\WWW\douyin\_status.php > d:\phpstudy_pro\WWW\douyin\_result.txt 2>&1
