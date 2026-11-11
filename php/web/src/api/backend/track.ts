import createAxios from '/@/utils/axios'

export const url = '/admin/track.Analysis/'

/** 总览漏斗：卡片 + 按日趋势 + 域名/渠道下拉选项 */
export function overview(params: TTrackQuery = {}) {
    return createAxios({ url: url + 'overview', method: 'get', params })
}

/** 页面分析：PV/UV/平均停留/退出率 */
export function pageStats(params: TTrackQuery & { keyword?: string; sort?: string } = {}) {
    return createAxios({ url: url + 'page', method: 'get', params })
}

/** 功能使用：事件次数/触达访客/触达用户 */
export function featureStats(params: TTrackQuery & { keyword?: string } = {}) {
    return createAxios({ url: url + 'feature', method: 'get', params })
}

/** 登录分析：次数分布 + 多次登录用户 + 登录明细 */
export function loginStats(params: TTrackQuery & { keyword?: string; success?: string; multi_page?: number; detail_page?: number } = {}) {
    return createAxios({ url: url + 'login', method: 'get', params })
}

/** 行为明细：按 vid / user_no / 用户ID 还原访问路径 */
export function behaviorDetail(params: { keyword?: string; sid?: string } = {}) {
    return createAxios({ url: url + 'detail', method: 'get', params })
}

/** 付费用户画像：谁付了钱、付了多少、钱花在哪 */
export function paidUsers(params: TTrackQuery & { keyword?: string } = {}) {
    return createAxios({ url: url + 'paid', method: 'get', params })
}

/** 单用户流水明细：充值 + 每一笔消费 */
export function userWallet(params: { keyword?: string; user_id?: number; page?: number; limit?: number } = {}) {
    return createAxios({ url: url + 'wallet', method: 'get', params })
}

type TTrackQuery = {
    start?: string
    end?: string
    domain?: string
    channel?: string
    page?: number
    limit?: number
}
