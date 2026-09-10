import { request } from '@/utils/request'

export interface CrowdfundingProject {
  id: number
  user_id: number
  title: string
  persona_name: string
  description: string
  tags: string[]
  style: string
  gender: string
  age_range: string
  language: string
  personality: string[]
  voice_style: string
  deliverables: string[]
  is_adult: number
  highlights: string
  reference_url: string
  cover_url: string
  target_amount: number
  raised_amount: number
  supporter_count: number
  deadline: string
  status: number       // 0进行中 1已成功 2已失败
  persona_id: number | null
  created_at: string
  progress_percent?: number
}

export interface CrowdfundingPledge {
  id: number
  project_id: number
  amount: number
  status: number       // 0冻结中 1已划转 2已退款
  created_at: string
  project?: {
    id: number
    title: string
    cover_url: string
    status: number
    deadline: string
    target_amount: number
    raised_amount: number
    supporter_count: number
  }
}

export interface CrowdfundingListResponse {
  list: CrowdfundingProject[]
  total: number
}

export interface InitiateParams {
  title: string
  persona_name: string
  description: string
  tags: string
  style: string
  gender?: string
  age_range?: string
  language?: string
  personality?: string
  voice_style?: string
  deliverables: string[]
  is_adult: number
  highlights?: string
  reference_url?: string
  cover_url: string
  target_amount: number
  deadline: string
}

// 众筹项目列表（进行中，公开）
export function getCrowdfundingList(page = 1, pageSize = 15) {
  return request<CrowdfundingListResponse>({
    url: '/api/v1/crowdfunding/list',
    method: 'get',
    params: { page, page_size: pageSize }
  })
}

// 众筹详情
export function getCrowdfundingDetail(id: number) {
  return request<CrowdfundingProject>({
    url: '/api/v1/crowdfunding/detail',
    method: 'get',
    params: { id }
  })
}

// 发起众筹（需商家认证）
export function initiateCrowdfunding(data: InitiateParams) {
  return request<CrowdfundingProject>({
    url: '/api/v1/crowdfunding/initiate',
    method: 'post',
    data
  })
}

// 支持众筹
export function pledgeCrowdfunding(projectId: number, amount: number) {
  return request<void>({
    url: '/api/v1/crowdfunding/pledge',
    method: 'post',
    data: { project_id: projectId, amount }
  })
}

// 我的发起
export function getMyProjects() {
  return request<CrowdfundingProject[]>({
    url: '/api/v1/crowdfunding/my-projects',
    method: 'get'
  })
}

// 我的支持
export function getMyPledges() {
  return request<CrowdfundingPledge[]>({
    url: '/api/v1/crowdfunding/my-pledges',
    method: 'get'
  })
}

// 关联角色
export function linkPersona(projectId: number, personaId: number) {
  return request<void>({
    url: '/api/v1/crowdfunding/link-persona',
    method: 'post',
    data: { project_id: projectId, persona_id: personaId }
  })
}

// 检查是否有进行中的众筹
export function checkActiveCrowdfunding() {
  return request<{ has_active: boolean }>({
    url: '/api/v1/crowdfunding/check-active',
    method: 'get'
  })
}

// 测试充值钻石
export function topupDiamonds(amount = 1000) {
  return request<{ amount: number; balance_before: number; balance_after: number }>({
    url: '/api/v1/crowdfunding/topup',
    method: 'post',
    data: { amount }
  })
}

/** 获取钻石余额 */
export function getCrowdfundingBalance() {
  return request<{ balance: number }>({
    url: '/api/v1/crowdfunding/balance',
    method: 'get'
  })
}
