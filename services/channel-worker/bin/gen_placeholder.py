"""生成用于 zmq 叠加的占位视频（0.5秒透明黑）"""
import subprocess
import sys
import os

out = sys.argv[1] if len(sys.argv) > 1 else 'placeholder.mp4'
ffmpeg = r'd:\phpstudy_pro\WWW\douyin\services\channel-worker\bin\ffmpeg-tools\ffmpeg-8.1.2-essentials_build\bin\ffmpeg.exe'

cmd = [
    ffmpeg, '-y', '-hide_banner',
    '-f', 'lavfi', '-i', 'color=c=black:s=720x1280:r=30:d=0.5',
    '-c:v', 'libx264', '-preset', 'ultrafast',
    '-pix_fmt', 'yuv420p', '-an',
    '-movflags', '+faststart',
    out
]
subprocess.run(cmd, check=True)
print(f'Generated: {out}')
