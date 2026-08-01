import NodeMediaServer from 'node-media-server'

const config = {
  rtmp: {
    port: 1935,
    chunk_size: 60000,
    gop_cache: true,
    ping: 30,
    ping_timeout: 60
  },
  http: {
    port: 8002,
    allow_origin: '*',
    mediaroot: 'D:/phpstudy_pro/WWW/douyin/php/public'
  },
  trans: {
    ffmpeg: process.env.FFMPEG_PATH || 'ffmpeg',
    tasks: [
      {
        app: 'live',
        hls: true,
        hlsFlags: '[hls_time=2:hls_list_size=30:hls_flags=delete_segments+append_list+omit_endlist]',
        hlsKeep: false,
        dash: false,
        vc: 'copy',
        ac: 'aac',
        acParam: ['-b:a', '128k', '-ar', '44100']
      }
    ]
  }
}

const nms = new NodeMediaServer(config)
nms.run()

console.log('RTMP server started on rtmp://127.0.0.1:1935/live')
console.log('HLS output to: D:/phpstudy_pro/WWW/douyin/php/public/hls')
