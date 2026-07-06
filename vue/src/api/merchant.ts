import { request } from '@/utils/request'

export interface CertificationStatus {
  has_cert: boolean
  cert_status: number   // -1:未认证 0:审核中 1:已通过 2:已拒绝
  status_text: string
  email?: string
  reject_reason?: string
  created_at?: string
}

export interface CertificationDetail {
  id: number
  user_id: number
  email: string
  id_card_front: string
  id_card_back: string
  status: number
  status_text: string
  reject_reason: string
  created_at: string
}

export interface SubmitCertificationData {
  email: string
  id_card_front: string
  id_card_back: string
}

export function getCertificationStatus() {
  return request<CertificationStatus>({
    url: '/api/v1/merchant/status',
    method: 'get'
  })
}

export function getCertificationDetail() {
  return request<CertificationDetail>({
    url: '/api/v1/merchant/detail',
    method: 'get'
  })
}

export function submitCertification(data: SubmitCertificationData) {
  return request<void>({
    url: '/api/v1/merchant/submit',
    method: 'post',
    data
  })
}

export function uploadCertificationImage(file: File) {
  const fd = new FormData()
  fd.append('file', file)
  return request<{ url: string }>({
    url: '/api/v1/merchant/upload',
    method: 'post',
    data: fd,
    headers: { 'Content-Type': 'multipart/form-data' }
  })
}
