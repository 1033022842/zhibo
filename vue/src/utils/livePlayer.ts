import Hls from 'hls.js'

export type LivePlaybackMode = 'webrtc' | 'hls' | 'preview'

interface LivePlaybackOptions {
  videoEl: HTMLVideoElement
  webrtcUrl?: string
  hlsUrl?: string
  previewUrl?: string
  muted?: boolean
  onModeChange?: (mode: LivePlaybackMode) => void
}

interface LivePlaybackController {
  play: () => Promise<LivePlaybackMode>
  destroy: () => void
}

interface SrsRtcPlayer {
  stream: MediaStream
  pc: RTCPeerConnection
  play: (url: string) => Promise<void>
  close: () => void
}

function canUseRtcPlayer() {
  return typeof window !== 'undefined' && typeof RTCPeerConnection !== 'undefined'
}

function createSrsRtcPlayer(): SrsRtcPlayer {
  const stream = new MediaStream()
  const pc = new RTCPeerConnection(null)

  pc.ontrack = (event) => {
    stream.addTrack(event.track)
  }

  return {
    stream,
    pc,
    async play(url: string) {
      pc.addTransceiver('audio', { direction: 'recvonly' })
      pc.addTransceiver('video', { direction: 'recvonly' })

      const offer = await pc.createOffer()
      await pc.setLocalDescription(offer)

      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/sdp'
        },
        body: offer.sdp ?? ''
      })

      if (!response.ok) {
        throw new Error(`WHEP play failed: ${response.status}`)
      }

      const answer = await response.text()
      await pc.setRemoteDescription(
        new RTCSessionDescription({
          type: 'answer',
          sdp: answer
        })
      )
    },
    close() {
      stream.getTracks().forEach((track) => track.stop())
      pc.close()
    }
  }
}

function resetVideoElement(videoEl: HTMLVideoElement) {
  videoEl.pause()
  videoEl.loop = false
  videoEl.removeAttribute('src')
  // 不要清除 srcObject，hls.js attachMedia 会自行替换
  // 不要调用 load()，让 hls.js 完全接管 video 元素
  delete videoEl.dataset.hlsReady
}

async function playPreview(videoEl: HTMLVideoElement, previewUrl: string) {
  resetVideoElement(videoEl)
  videoEl.loop = true
  videoEl.src = previewUrl
  videoEl.autoplay = true
  videoEl.load()
  await videoEl.play()
  return 'preview' as const
}

function playHls(videoEl: HTMLVideoElement, hlsUrl: string): Promise<boolean> {
  return new Promise((resolve) => {
    // HTTPS 页面加载 HTTP 的 CDN 资源会被浏览器拦截（mixed content）
    // 此时回退到页面同域路径（源站直连），牺牲 CDN 加速但保证能播
    try {
      const u = new URL(hlsUrl)
      if (window.location.protocol === 'https:' && u.protocol === 'http:') {
        console.warn('[HLS] https page + http cdn url, fallback to same-origin:', u.pathname)
        hlsUrl = window.location.origin + u.pathname + u.search
      }
    } catch (_) { /* keep original */ }
    console.warn('[HLS] playHls, url:', hlsUrl)

    if (!Hls.isSupported()) {
      console.warn('[HLS] Hls.isSupported=false, giving up')
      resolve(false)
      return
    }

    resetVideoElement(videoEl)
    const hls = new Hls({
      enableWorker: false,
      debug: false,
      maxBufferLength: 12,
      maxMaxBufferLength: 30,
      // 贴近直播边缘（2 个分片 ≈ 6.4s），降低送礼视频的可见延迟
      liveSyncDurationCount: 2,
      maxBufferSize: 30 * 1000 * 1000, // 30MB
      maxBufferHole: 0.5,
    })
    let resolved = false
    let firstFragLoading = false

    hls.attachMedia(videoEl)

    hls.on(Hls.Events.MEDIA_ATTACHED, () => {
      videoEl.dataset.hlsReady = '1'
      hls.loadSource(hlsUrl)
    })

    hls.on(Hls.Events.MANIFEST_PARSED, (_event, data) => {
      const levels = data.levels?.length ?? 'media'
      const duration = data.firstLevel?.details?.totalduration
      console.warn('[HLS] manifest parsed, levels:', levels, 'duration:', Math.round(duration || 0))
    })

    // Log all fragment loading events
    hls.on(Hls.Events.FRAG_LOADING, (_event, data) => {
      firstFragLoading = true
      const fullUrl = data.frag?.url || ''
      console.warn('[HLS] loading frag:', data.frag?.sn, 'url:', fullUrl)
    })

    hls.on(Hls.Events.FRAG_LOADED, (_event, data) => {
      console.warn('[HLS] frag loaded:', data.frag?.sn, 'size:', data.payload?.byteLength, 'loadTime:', Math.round(data.stats?.loading?.ms || 0), 'ms')
    })

    hls.on(Hls.Events.FRAG_LOAD_PROGRESS, (_event, data) => {
      // 每收到进度事件都打印（大文件下载时帮助判断是否在进行中）
      if (data.stats?.loaded && data.stats?.total) {
        const pct = Math.round(data.stats.loaded / data.stats.total * 100)
        console.warn('[HLS] frag progress:', data.frag?.sn, Math.round(data.stats.loaded/1024), '/', Math.round(data.stats.total/1024), 'KB (', pct, '%)')
      }
    })

    hls.on(Hls.Events.FRAG_PARSED, (_event, data) => {
      console.warn('[HLS] frag parsed:', data.frag?.sn)
    })

    hls.on(Hls.Events.FRAG_BUFFERED, () => {
      if (!resolved) {
        resolved = true
        console.warn('[HLS] playback ready (first frag buffered)')
        videoEl.play().catch((e) => console.warn('[HLS] play rejected:', e.name))
        resolve(true)
      }
    })

    // Log ALL errors, not just fatal
    hls.on(Hls.Events.ERROR, (_event, data) => {
      console.error('[HLS] error:', data.type, data.details, data.fatal ? 'FATAL' : 'non-fatal')
      if (data.fatal) {
        hls.destroy()
        if (!resolved) { resolved = true; resolve(false) }
      }
    })

    setTimeout(() => {
      if (!resolved) {
        console.warn('[HLS] timeout (120s), firstFragLoading:', firstFragLoading)
        hls.destroy()
        resolved = true
        resolve(false)
      }
    }, 120000)
  })
}

export function createLivePlaybackController(options: LivePlaybackOptions): LivePlaybackController {
  const { videoEl, webrtcUrl, hlsUrl, previewUrl, muted = true, onModeChange } = options
  const teardownList: Array<() => void> = []
  let rtcPlayer: SrsRtcPlayer | null = null
  let reconnectTimer: ReturnType<typeof setTimeout> | null = null
  let destroyed = false

  videoEl.muted = muted
  videoEl.playsInline = true
  videoEl.autoplay = true

  async function attemptWebrtc(): Promise<boolean> {
    if (!webrtcUrl || !canUseRtcPlayer()) return false

    try {
      rtcPlayer?.close()
      resetVideoElement(videoEl)
      rtcPlayer = createSrsRtcPlayer()
      videoEl.srcObject = rtcPlayer.stream
      await rtcPlayer.play(webrtcUrl)
      await videoEl.play()
      onModeChange?.('webrtc')
      return true
    } catch (error) {
      console.warn('WebRTC play failed', error)
      rtcPlayer?.close()
      rtcPlayer = null
      return false
    }
  }

  function startReconnectLoop() {
    let retries = 0
    const maxRetries = 5

    async function tryReconnect() {
      if (destroyed) return

      const ok = await attemptWebrtc()
      if (ok) {
        // 重连成功，监听下一次断连
        watchConnection()
        return
      }

      retries++
      if (retries < maxRetries) {
        console.warn(`WebRTC reconnect attempt ${retries}/${maxRetries}, retrying in 1.5s...`)
        reconnectTimer = setTimeout(tryReconnect, 1500)
      } else {
        console.warn('WebRTC reconnect exhausted, falling back')
        if (hlsUrl && !destroyed) {
          playHls(videoEl, hlsUrl).then((ok) => {
            if (ok) onModeChange?.('hls')
            else if (previewUrl) {
              playPreview(videoEl, previewUrl).then((mode) => onModeChange?.(mode))
            }
          })
        } else if (previewUrl && !destroyed) {
          playPreview(videoEl, previewUrl).then((mode) => {
            onModeChange?.(mode)
          })
        }
      }
    }

    tryReconnect()
  }

  function watchConnection() {
    if (!rtcPlayer) return
    const pc = rtcPlayer.pc

    const handler = () => {
      if (destroyed) return
      const state = pc.iceConnectionState
      if (state === 'disconnected' || state === 'failed') {
        console.warn(`WebRTC ${state}, starting reconnect...`)
        pc.removeEventListener('iceconnectionstatechange', handler)
        startReconnectLoop()
      }
    }

    pc.addEventListener('iceconnectionstatechange', handler)
    teardownList.push(() => pc.removeEventListener('iceconnectionstatechange', handler))
  }

  return {
    async play() {
      // HLS 优先（更稳定，兼容性好）
      if (hlsUrl) {
        const ok = await playHls(videoEl, hlsUrl)
        if (ok) {
          onModeChange?.('hls')
          return 'hls'
        }
      }

      // WebRTC 备选
      const ok2 = await attemptWebrtc()
      if (ok2) {
        watchConnection()
        return 'webrtc'
      }

      // 最后兜底：预览视频
      if (previewUrl) {
        const mode = await playPreview(videoEl, previewUrl)
        onModeChange?.(mode)
        return mode
      }

      throw new Error('No playable source found')
    },
    destroy() {
      destroyed = true
      if (reconnectTimer) {
        clearTimeout(reconnectTimer)
        reconnectTimer = null
      }
      teardownList.splice(0).forEach((teardown) => teardown())
      rtcPlayer?.close()
      rtcPlayer = null
      resetVideoElement(videoEl)
    }
  }
}
