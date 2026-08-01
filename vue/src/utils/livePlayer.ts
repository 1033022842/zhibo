export type LivePlaybackMode = 'webrtc' | 'preview'

interface LivePlaybackOptions {
  videoEl: HTMLVideoElement
  webrtcUrl?: string
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

export function createLivePlaybackController(options: LivePlaybackOptions): LivePlaybackController {
  const { videoEl, webrtcUrl, previewUrl, muted = true, onModeChange } = options
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
        console.warn('WebRTC reconnect exhausted, falling back to preview')
        if (previewUrl && !destroyed) {
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
      const ok = await attemptWebrtc()
      if (ok) {
        watchConnection()
        return 'webrtc'
      }

      // 兜底：预览视频
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
