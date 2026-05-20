import Hls from 'hls.js'

export type LivePlaybackMode = 'webrtc' | 'hls' | 'native-hls' | 'preview'

interface LivePlaybackOptions {
  videoEl: HTMLVideoElement
  webrtcUrl?: string
  hlsUrl?: string
  previewUrl?: string
  muted?: boolean
  preferHls?: boolean
  onModeChange?: (mode: LivePlaybackMode) => void
}

interface LivePlaybackController {
  play: () => Promise<LivePlaybackMode>
  destroy: () => void
}

interface SrsRtcPlayer {
  stream: MediaStream
  play: (url: string) => Promise<void>
  close: () => void
}

function canUseRtcPlayer() {
  return typeof window !== 'undefined' && typeof RTCPeerConnection !== 'undefined'
}

function shouldSkipRtcPlayer(url: string) {
  try {
    const normalizedUrl = url.replace('webrtc://', 'http://').replace('rtc://', 'http://')
    const parsed = new URL(normalizedUrl)
    const currentPort = window.location.port
    const devPorts = new Set(['3000', '5173', '4173'])

    return (
      devPorts.has(parsed.port || currentPort) &&
      parsed.hostname === window.location.hostname &&
      !url.includes('/whep/') &&
      !url.includes('/whip-play/')
    )
  } catch (_error) {
    return false
  }
}

function convertWebRtcToWhepUrl(url: string) {
  if (url.includes('/whep/') || url.includes('/whip-play/')) {
    return url
  }

  const normalizedUrl = url.replace('webrtc://', 'http://').replace('rtc://', 'http://')
  const parsed = new URL(normalizedUrl)
  const schema = window.location.protocol === 'https:' ? 'https:' : 'http:'
  const defaultPort = schema === 'https:' ? '443' : '1985'
  const pathname = parsed.pathname.replace(/^\/+/, '')
  const lastSlashIndex = pathname.lastIndexOf('/')
  const app = pathname.slice(0, lastSlashIndex)
  const stream = pathname.slice(lastSlashIndex + 1)
  const params = new URLSearchParams(parsed.search)

  const whepUrl = new URL(`${schema}//${parsed.hostname}:${parsed.port || defaultPort}/rtc/v1/whep/`)
  whepUrl.searchParams.set('app', app)
  whepUrl.searchParams.set('stream', stream)
  params.forEach((value, key) => {
    whepUrl.searchParams.set(key, value)
  })

  return whepUrl.toString()
}

function createSrsRtcPlayer(): SrsRtcPlayer {
  const stream = new MediaStream()
  const pc = new RTCPeerConnection(null)

  pc.ontrack = (event) => {
    stream.addTrack(event.track)
  }

  return {
    stream,
    async play(url: string) {
      const playUrl = convertWebRtcToWhepUrl(url)
      pc.addTransceiver('audio', { direction: 'recvonly' })
      pc.addTransceiver('video', { direction: 'recvonly' })

      const offer = await pc.createOffer()
      await pc.setLocalDescription(offer)

      const response = await fetch(playUrl, {
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
  videoEl.srcObject = null
  videoEl.load()
}

async function playPreview(videoEl: HTMLVideoElement, previewUrl: string) {
  resetVideoElement(videoEl)
  videoEl.loop = true
  videoEl.src = previewUrl
  videoEl.load()
  await videoEl.play()
  return 'preview' as const
}

async function playHls(
  videoEl: HTMLVideoElement,
  hlsUrl: string,
  teardownList: Array<() => void>
): Promise<LivePlaybackMode> {
  resetVideoElement(videoEl)
  if (videoEl.canPlayType('application/vnd.apple.mpegurl')) {
    videoEl.src = hlsUrl
    videoEl.load()
    await videoEl.play()
    return 'native-hls'
  }

  if (!Hls.isSupported()) {
    throw new Error('HLS is not supported')
  }

  const hls = new Hls({
    enableWorker: true,
    lowLatencyMode: true
  })
  teardownList.push(() => hls.destroy())
  hls.loadSource(hlsUrl)
  hls.attachMedia(videoEl)

  await new Promise<void>((resolve, reject) => {
    const cleanup = () => {
      hls.off(Hls.Events.MANIFEST_PARSED, handleParsed)
      hls.off(Hls.Events.ERROR, handleError)
    }
    const handleParsed = async () => {
      cleanup()
      try {
        await videoEl.play()
        resolve()
      } catch (error) {
        reject(error)
      }
    }
    const handleError = (_event: string, data: { fatal?: boolean }) => {
      if (!data?.fatal) return
      cleanup()
      reject(new Error('Fatal HLS error'))
    }

    hls.on(Hls.Events.MANIFEST_PARSED, handleParsed)
    hls.on(Hls.Events.ERROR, handleError)
  })

  return 'hls'
}

export function createLivePlaybackController(options: LivePlaybackOptions): LivePlaybackController {
  const { videoEl, webrtcUrl, hlsUrl, previewUrl, muted = true, preferHls = true, onModeChange } = options
  const teardownList: Array<() => void> = []
  let rtcPlayer: SrsRtcPlayer | null = null

  videoEl.muted = muted
  videoEl.playsInline = true
  videoEl.autoplay = true

  return {
    async play() {
      const attemptPlayHls = async () => {
        if (!hlsUrl) return null
        const mode = await playHls(videoEl, hlsUrl, teardownList)
        onModeChange?.(mode)
        return mode
      }

      const attemptPlayWebrtc = async () => {
        if (!webrtcUrl || !canUseRtcPlayer()) return null
        if (shouldSkipRtcPlayer(webrtcUrl)) {
          throw new Error('Skip WebRTC on local dev server')
        }

        try {
          resetVideoElement(videoEl)
          rtcPlayer = createSrsRtcPlayer()
          teardownList.push(() => rtcPlayer?.close())
          videoEl.srcObject = rtcPlayer.stream
          await rtcPlayer.play(webrtcUrl)
          await videoEl.play()
          onModeChange?.('webrtc')
          return 'webrtc'
        } catch (error) {
          console.warn('webrtc play failed, fallback to next source', error)
          rtcPlayer?.close()
          rtcPlayer = null
          throw error
        }
      }

      const attemptPlayPreview = async () => {
        if (!previewUrl) return null
        const mode = await playPreview(videoEl, previewUrl)
        onModeChange?.(mode)
        return mode
      }

      const playAttempts = preferHls
        ? [attemptPlayHls, attemptPlayWebrtc, attemptPlayPreview]
        : [attemptPlayWebrtc, attemptPlayHls, attemptPlayPreview]

      let lastError: unknown = null
      for (const attempt of playAttempts) {
        try {
          const mode = await attempt()
          if (mode) return mode
        } catch (error) {
          lastError = error
        }
      }

      throw lastError || new Error('No playable source found')
    },
    destroy() {
      teardownList.splice(0).forEach((teardown) => teardown())
      rtcPlayer = null
      resetVideoElement(videoEl)
    }
  }
}
