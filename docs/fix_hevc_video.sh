#!/usr/bin/env bash
# 把 HEVC(H.265) 的私密视频转成浏览器通用的 H.264 + faststart。
#
# 背景：后台允许上传任意 mp4，素材里混进过 HEVC。HEVC 在多数浏览器里
#       「容器能解、编码解不了」，表现是有声音没画面（黑屏）。
#       本脚本只做转码，不负责上传，上传请走后台「私密内容管理」重新替换。
#
# 用法：
#   ./fix_hevc_video.sh input.mp4 output.mp4
#   ./fix_hevc_video.sh input.mp4            # 输出为 input.h264.mp4
#
# 依赖：ffmpeg（需含 libx264）
set -euo pipefail

SRC="${1:-}"
if [[ -z "$SRC" ]]; then
    echo "用法: $0 input.mp4 [output.mp4]" >&2
    exit 1
fi
if [[ ! -f "$SRC" ]]; then
    echo "文件不存在: $SRC" >&2
    exit 1
fi
DST="${2:-${SRC%.*}.h264.mp4}"

if ! command -v ffmpeg >/dev/null 2>&1; then
    echo "未找到 ffmpeg，请先安装" >&2
    exit 1
fi

echo "== 源文件编码 =="
ffprobe -v error -select_streams v:0 \
    -show_entries stream=codec_name,width,height,pix_fmt -of default=nw=1 "$SRC"

CODEC=$(ffprobe -v error -select_streams v:0 -show_entries stream=codec_name -of csv=p=0 "$SRC")
if [[ "$CODEC" == "h264" ]]; then
    echo "已是 H.264，无需转码（如需补 faststart 见下方提示）"
else
    echo "== 转码为 H.264（CRF 20，保留原分辨率/帧率）=="
    ffmpeg -y -v warning -stats -i "$SRC" \
        -map 0:v:0 -map 0:a? \
        -c:v libx264 -preset medium -crf 20 -pix_fmt yuv420p -profile:v high -level 4.2 \
        -c:a aac -b:a 128k \
        -movflags +faststart \
        "$DST"
fi

echo "== 输出校验 =="
ffprobe -v error -select_streams v:0 \
    -show_entries stream=codec_name,codec_tag_string,width,height,pix_fmt -of default=nw=1 "$DST"
ffprobe -v error -show_entries format=duration,size -of default=nw=1 "$DST"
echo "完成: $DST"
echo
echo "提示：若只是 moov 在文件尾导致首帧迟迟不显示，可无损改写："
echo "      ffmpeg -v error -i \"$SRC\" -c copy -movflags +faststart \"$DST\""
