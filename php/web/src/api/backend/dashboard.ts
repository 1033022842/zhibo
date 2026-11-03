import createAxios from '/@/utils/axios'

export const url = '/admin/Dashboard/'

export function index(params: { from?: string; to?: string } = {}) {
    return createAxios({
        url: url + 'index',
        method: 'get',
        params,
    })
}
