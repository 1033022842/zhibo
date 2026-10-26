# media-src —— Image 页卡片素材（仅 sugus / heygirl 覆盖版使用）

新版 `Image.html` 的瀑布流卡片用这套素材：gs-01..gs-50 共 50 条，49 个视频 + 1 张图片，
另有 58 张卡片左上角的角色小图（角标）和每条的封面帧。

## 为什么素材不在仓库里

媒体总量约 205 MB。仓库只放**清单和抓取脚本**，媒体由服务器自己从公开 CDN 拉：

- `clips-manifest.json` —— 每条素材的源地址、尺寸、文件名映射
- `fetch_media.py` —— 在服务器上跑，按清单下载 + ffmpeg 抽封面 + 生成 `clips.json`

## 部署

```bash
# 1) 把清单和脚本传到服务器
scp clips-manifest.json fetch_media.py root@<host>:/tmp/
# 脚本默认读 /tmp/gs_deploy_manifest.json，按需改名或设 GS_MANIFEST

# 2) 在服务器上执行（幂等：已存在的文件跳过）
GS_MANIFEST=/tmp/clips-manifest.json python3 /tmp/fetch_media.py
```

产物落在 `<站点根>/media/gs/`：

```
media/gs/
  clips.json      # 前端读这个（customVideo.js 的 GS_BASE = './media/gs/'）
  01.mp4 … 50.mp4 # 视频（第 8 条是 08.png，图片型模板）
  01.jpg … 50.jpg # 封面帧（首屏先显示封面，视频出画后淡入）
  01-1.webp …     # 卡片左上角角标，按 <序号>-<n>.webp
```

## 素材来源

来自 genspicy.ai 首页模板列表（`/api/home/cached-generation-template-list`）。
清单里保存的是 `cdn.genspicy.ai` / `cdn.openlover.ai` 的公开直链，无需鉴权即可下载。

## 注意

- 站点 nginx 开了 `open_file_cache_errors on`，新增文件后如果之前请求过 404，
  需要 `nginx -s reload` 才能立刻读到（否则会持续 404 一小段时间）。
- 角标图和视频**必须成对存在**：卡片左上角角标缺图会显示灰底方块。
- 换素材时改 `clips-manifest.json` 再跑一次脚本，`clips.json` 会一并重写。
