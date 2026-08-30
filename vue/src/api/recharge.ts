import { request } from '@/utils/request'

export interface RechargeChannel {
  id: number
  name: string
  type: string
  qr_code_url: string
  address: string
  diamond_rate: number
  min_amount: number
  sort: number
  status: number
}

export interface RechargeOrder {
  id: number
  order_no: string
  user_id: number
  pay_channel: string
  channel_id: number
  pay_amount: number
  diamond_amount: number
  proof_image: string
  status: number
  status_text: string
  admin_remark: string
  created_at: string
  channel?: RechargeChannel
}

// 可用渠道
export function getRechargeChannels() {
  return request<RechargeChannel[]>({
    url: '/api/v1/recharge/channels',
    method: 'get'
  })
}

// 提交充值订单
export function submitRecharge(channelId: number, amount: number, proofImage?: string) {
  return request<RechargeOrder>({
    url: '/api/v1/recharge/submit',
    method: 'post',
    data: { channel_id: channelId, amount, proof_image: proofImage || '' }
  })
}

// 充值记录
export function getRechargeStatus(orderNo: string) {
  return request({
    url: `/api/v1/recharge/status?order_no=${encodeURIComponent(orderNo)}`,
    method: 'get'
  })
}

export function getRechargeOrders(page = 1) {
  return request<{ list: RechargeOrder[]; total: number }>({
    url: '/api/v1/recharge/orders',
    method: 'get',
    params: { page }
  })
}

// 上传凭证
export function uploadProofImage(file: File) {
  const fd = new FormData()
  fd.append('file', file)
  return request<{ url: string }>({
    url: '/api/v1/recharge/upload',
    method: 'post',
    data: fd,
    headers: { 'Content-Type': 'multipart/form-data' }
  })
}
