import axios, {
  type AxiosError,
  type AxiosRequestConfig,
  type AxiosResponse,
  type InternalAxiosRequestConfig
} from 'axios'
import config from '@/config'
import { _notice } from './index'
import { getAccessToken, setTokens, clearTokens, getRefreshToken } from './auth'

export const axiosInstance = axios.create({
  baseURL: config.baseUrl,
  timeout: 60000
})

// request拦截器
axiosInstance.interceptors.request.use(
  (reqConfig: InternalAxiosRequestConfig) => {
    if (!reqConfig.headers['Content-Type']) {
      reqConfig.headers['Content-Type'] = 'application/json'
    }
    if (typeof reqConfig.url === 'string' && reqConfig.url.startsWith('/api/')) {
      reqConfig.params = {
        ...(reqConfig.params || {}),
        server: 1
      }
    }
    const token = getAccessToken()
    if (token) {
      reqConfig.headers['Authorization'] = `Bearer ${token}`
    }
    return reqConfig
  },
  (error) => {
    return Promise.reject(error)
  }
)

/*
 * 响应拦截器，无论失败或者成功都会返回{ success: boolean, data: xxx }这种类型的数据，没有reject和抛error。
 * 如果有问题，拦截器里会进行提示。在then里面总是会接收到返回值
 * */
axiosInstance.interceptors.response.use(
  (response: AxiosResponse) => {
    // console.log('response',response)
    /*
     * 响应成功的拦截器，主要是对data作处理，如果没有返回data，那么会添加一个data字段，并把response.data的内容合并到data里面，然后返回
     * */
    const { data } = response
    // console.log(response)
    if (data === undefined || data === null || data === '') {
      _notice('请求失败，请稍后重试！')
      return { success: false, code: 500, data: [] }
    } else if (typeof data === 'string') {
      return { success: true, code: 200, data }
    } else {
      if (data.data === undefined || data.data === null) {
        data.data = { ...data }
      }
      let resCode = data.code
      if (resCode) {
        try {
          resCode = Number(resCode)
        } catch (e) {
          data.code = resCode = 500
          data.success = false
        }
        if (resCode === 0) {
          data.code = resCode = 200
          data.success = true
        }
        if (resCode !== 200) {
          _notice(response.data.message || '请求失败，请稍后重试！')
        } else {
          data.success = true
        }
      } else {
        data.code = 200
        data.success = true
      }
      return data
    }
  },
  (error: AxiosError) => {
    console.log('error', error)
    // console.log(error.response)
    // console.log(error.response.status)
    if (error.response === undefined) {
      _notice('服务器响应超时')
      return { success: false, code: 500, msg: '服务器响应超时', data: [] }
    }
    if (error.response.status >= 500) {
      _notice('服务器出现错误')
      return { success: false, code: 500, msg: '服务器出现错误', data: [] }
    }
    if (error.response.status === 404) {
      _notice('接口不存在')
      return { success: false, code: 404, msg: '接口不存在', data: [] }
    }
    if (error.response.status === 400) {
      _notice('接口报错')
      return { success: false, code: 400, msg: '接口报错', data: [] }
    }
    if (error.response.status === 401) {
      return { success: false, code: 401, msg: '用户名或密码不正确', data: [] }
    } else {
      const data: any = error.response.data
      if (data === null || data === undefined) {
        _notice('请求失败，请稍后重试！')
        return { success: true, code: 200, data: [] }
      } else {
        const resCode = data.code
        if (data.data === undefined || data.data === null) {
          data.data = { ...data }
        }
        if (resCode && typeof resCode == 'number' && resCode !== 200) {
          _notice('请求失败，请稍后重试！')
        } else {
          data.code = 200
          data.success = true
        }
        return data
      }
    }
  }
)

// Token 刷新逻辑 — 注册在已有拦截器之后，因此响应时最先执行
let isRefreshing = false
let refreshSubscribers: Array<(token: string | null) => void> = []

function onRefreshed(token: string | null) {
  refreshSubscribers.forEach((cb) => cb(token))
  refreshSubscribers = []
}

async function doRefreshToken(): Promise<string | null> {
  try {
    const refreshToken = getRefreshToken()
    if (!refreshToken) return null

    const rawAxios = axios.create({ baseURL: config.baseUrl, timeout: 30000 })
    const resp = await rawAxios.post('/api/live/refresh_token', {
      refresh_token: refreshToken
    })

    const body = resp.data || {}
    if (body.code === 0 && body.data?.access_token) {
      setTokens(body.data.access_token, body.data.refresh_token)
      return body.data.access_token
    }
    return null
  } catch {
    return null
  }
}

axiosInstance.interceptors.response.use(
  (response) => response, // 成功直接透传
  async (error: AxiosError) => {
    const reqConfig = error.config as InternalAxiosRequestConfig & { _retry?: boolean; _queueId?: number }
    if (!reqConfig) return Promise.reject(error)

    if (error.response?.status !== 401) return Promise.reject(error)
    if (reqConfig._retry) return Promise.reject(error)

    const url = reqConfig.url || ''
    if (url.includes('/login') || url.includes('/register') || url.includes('/refresh_token')) {
      return Promise.reject(error)
    }

    if (isRefreshing) {
      return new Promise((resolve) => {
        refreshSubscribers.push((token: string | null) => {
          if (token) {
            const headers = reqConfig.headers as Record<string, string>
            headers['Authorization'] = `Bearer ${token}`
            reqConfig._retry = true
            resolve(axiosInstance(reqConfig))
          } else {
            resolve(Promise.reject(error))
          }
        })
      })
    }

    isRefreshing = true

    try {
      const newToken = await doRefreshToken()
      if (newToken) {
        onRefreshed(newToken)
        isRefreshing = false
        reqConfig._retry = true
        const headers = reqConfig.headers as Record<string, string>
        headers['Authorization'] = `Bearer ${newToken}`
        return axiosInstance(reqConfig)
      }
      onRefreshed(null)
    } catch {
      onRefreshed(null)
    }

    isRefreshing = false
    clearTokens()
    window.location.href = '/login'
    return Promise.reject(error)
  }
)

export interface ApiResponse<T = any> {
  data: T
  success: boolean
}

export async function request<T = any>(config: AxiosRequestConfig): Promise<ApiResponse<T>> {
  /*
   *  then和catch里面返回的数据必须加as const，否则调用方无法推断出类型
   * */
  return axiosInstance
    .request<T>(config)
    .then(({ data }) => {
      return { success: true, data } as const
    })
    .catch((err) => {
      return { success: false, data: err } as const
    })
}
