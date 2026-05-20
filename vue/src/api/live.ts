import { request } from '@/utils/request'

export interface LivePersona {
  id: number
  name: string
  tags: string[]
}

export interface LiveDisplayInfo {
  badge_text: string
  online_text: string
  like_text: string
}

export interface LiveStateInfo {
  mode: string
  privilege_active: boolean
  privilege_expire_at: number
  room_group_code?: string
}

export interface LivePlayInfo {
  stream_alias: string
  webrtc_url: string
  hls_url: string
  play_token: string
  expire_at: number
}

export interface LiveInteractionInfo {
  allow_chat: boolean
  allow_like: boolean
  allow_gift: boolean
}

export interface LiveGiftInfo {
  gift_id: number
  name: string
  price: number
  trigger_mode?: string
  trigger_duration_sec?: number
}

export interface LiveGiftPanel {
  currency_name: string
  quick_gifts: LiveGiftInfo[]
}

export interface LiveRoom {
  room_id: number
  room_no: string
  title: string
  subtitle: string
  status: string
  sort_score: number
  cover_url: string
  preview_video_url: string
  persona: LivePersona
  display: LiveDisplayInfo
  state: LiveStateInfo
  play?: LivePlayInfo
  interaction: LiveInteractionInfo
  gift_panel: LiveGiftPanel
  room_tags: string[]
  binding?: Record<string, unknown>
}

export interface LiveFeedResult {
  list: LiveRoom[]
  cursor: string | null
  has_more: boolean
}

export function feedLive(params?: { cursor?: string; limit?: number }) {
  return request<LiveFeedResult>({
    url: '/api/v1/feed/live',
    method: 'get',
    params
  })
}

export function liveRoomDetail(id: number) {
  return request<LiveRoom>({
    url: `/api/v1/rooms/${id}`,
    method: 'get'
  })
}
