import { defineStore } from 'pinia'
import { friends, panel } from '@/api/user'
import enums from '@/utils/enums'
import { request } from '@/utils/request'
import {
  setTokens,
  clearTokens,
  setStoredUserInfo,
  getStoredUserInfo,
  getRefreshToken,
  isLoggedIn,
  type UserInfo
} from '@/utils/auth'

interface LoginResult {
  access_token: string
  refresh_token: string
  expires_in: number
  user: UserInfo
}

interface RefreshResult {
  access_token: string
  refresh_token: string
  expires_in: number
}

export const useBaseStore = defineStore('base', {
  state: () => {
    return {
      bodyHeight: document.body.clientHeight,
      bodyWidth: document.body.clientWidth,
      maskDialog: false,
      maskDialogMode: 'dark',
      version: '17.1.0',
      excludeNames: ['LivePage'],
      judgeValue: 20,
      homeRefresh: 60,
      loading: false,
      routeData: null,
      users: [],
      userinfo: {
        nickname: '',
        desc: '',
        user_age: '',
        signature: '',
        unique_id: '',
        province: '',
        city: '',
        gender: '',
        school: {
          name: '',
          department: null,
          joinTime: null,
          education: null,
          displayType: enums.DISPLAY_TYPE.ALL
        },
        avatar_168x168: {
          url_list: []
        },
        avatar_300x300: {
          url_list: []
        },
        cover_url: [
          {
            url_list: []
          }
        ],
        white_cover_url: [
          {
            url_list: []
          }
        ]
      },
      friends: [] as any[],
      message: '',
      // 认证状态
      isAuthReady: false,
      authUserId: 0,
      authUserNo: '',
      authNickname: '',
      authAvatar: '',
      authLevel: 1,
      authGender: 0,
      authBio: '',
      authCertStatus: -1  // -1:未认证 0:审核中 1:已通过 2:已拒绝
    }
  },
  getters: {
    selectFriends() {
      return this.friends.all.filter((v) => v.select)
    }
  },
  actions: {
    async init() {
      this.restoreAuthFromStorage()
      const r = await panel()
      if (r.success) {
        this.userinfo = Object.assign(this.userinfo, r.data)
      }
      const r2 = await friends()
      if (r2.success) {
        this.users = r2.data
      }
    },

    restoreAuthFromStorage() {
      if (!isLoggedIn()) {
        this.isAuthReady = true
        return
      }
      // 始终先通过 API 验证 token 有效性再恢复用户状态
      // 不直接使用 localStorage 中的旧数据，避免过期 token 造成"假登录"
      this.fetchProfile().finally(() => {
        this.isAuthReady = true
      })
    },

    async doLogin(username: string, password: string): Promise<{ ok: boolean; msg: string }> {
      try {
        const res = await request<LoginResult>({
          url: '/api/live/login',
          method: 'POST',
          data: { username, password }
        })

        if (res.success && res.data) {
          const { access_token, refresh_token, user } = res.data
          setTokens(access_token, refresh_token)
          setStoredUserInfo(user)

          this.authUserId = user.id
          this.authUserNo = user.user_no
          this.authNickname = user.nickname
          this.authAvatar = user.avatar
          this.authLevel = (user as any).level || 1
          this.userinfo.nickname = user.nickname
          this.isAuthReady = true

          // 登录成功后拉取完整 profile（gender、bio、certStatus 等）
          this.fetchProfile()

          return { ok: true, msg: '登录成功' }
        }

        return { ok: false, msg: (res.data as any)?.message || '登录失败' }
      } catch (e: any) {
        return { ok: false, msg: e?.message || '网络错误' }
      }
    },

    async doRegisterFromAi(
      account: string,
      password: string
    ): Promise<{ ok: boolean; msg: string }> {
      try {
        // 根据账号格式自动推导 username 和 email
        const isEmail = account.includes('@')
        const username = isEmail ? account.split('@')[0] : account
        const email = isEmail ? account : `${account}@user.ai-live`

        const res = await request<LoginResult>({
          url: '/api/live/registerFromAi',
          method: 'POST',
          data: { username, email, password }
        })

        if (res.success && res.data) {
          const { access_token, refresh_token, user } = res.data
          setTokens(access_token, refresh_token)
          if (user) setStoredUserInfo(user)

          this.authUserId = user?.id || 0
          this.authUserNo = user?.user_no || ''
          this.authNickname = user?.nickname || ''
          this.authAvatar = user?.avatar || ''
          this.authLevel = (user as any)?.level || 1
          this.isAuthReady = true

          this.fetchProfile()
          return { ok: true, msg: '注册成功' }
        }

        return { ok: false, msg: (res.data as any)?.message || '注册失败' }
      } catch (e: any) {
        return { ok: false, msg: e?.message || '网络错误' }
      }
    },

    async doRegister(
      email: string,
      password: string,
      nickname: string,
      code: string
    ): Promise<{ ok: boolean; msg: string }> {
      try {
        const res = await request<any>({
          url: '/api/live/register',
          method: 'POST',
          data: { email, password, nickname, code }
        })

        if (res.success || res.data?.code === 200) {
          return { ok: true, msg: '注册成功' }
        }

        return { ok: false, msg: (res.data as any)?.message || '注册失败' }
      } catch (e: any) {
        return { ok: false, msg: e?.message || '网络错误' }
      }
    },

    doLogout(): void {
      // 即使 API 调用失败也要清除本地状态
      const rt = getRefreshToken()
      fetch('/api/live/logout', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ refresh_token: rt || '' })
      }).catch(() => {})
      clearTokens()
      this.resetAuthState()
    },

    async fetchProfile(): Promise<void> {
      if (!isLoggedIn()) return
      try {
        const res = await request<UserInfo>({
          url: '/api/live/profile',
          method: 'GET'
        })
        if (res.success && res.data) {
          const user = res.data as any
          setStoredUserInfo(user)
          this.authUserId = user.id
          this.authUserNo = user.user_no
          this.authNickname = user.nickname
          this.authAvatar = user.avatar
          this.authLevel = user.level || 1
          this.authGender = user.gender || 0
          this.authBio = user.bio || ''
          this.authCertStatus = (user as any).cert_status ?? -1
          this.userinfo.nickname = user.nickname
        }
      } catch {
        // 静默失败
      }
    },

    resetAuthState() {
      this.authUserId = 0
      this.authUserNo = ''
      this.authNickname = ''
      this.authAvatar = ''
      this.authLevel = 1
      this.authGender = 0
      this.authBio = ''
      this.authCertStatus = -1
      this.userinfo.nickname = ''
      this.isAuthReady = true
    },

    setUserinfo(val) {
      this.userinfo = val
    },
    setMaskDialog(val) {
      this.maskDialog = val.state
      if (val.mode) {
        this.maskDialogMode = val.mode
      }
    },
    updateExcludeNames(val) {
      if (val.type === 'add') {
        if (!this.excludeNames.find((v) => v === val.value)) {
          this.excludeNames.push(val.value)
        }
      } else {
        const resIndex = this.excludeNames.findIndex((v) => v === val.value)
        if (resIndex !== -1) {
          this.excludeNames.splice(resIndex, 1)
        }
      }
      // console.log('store.excludeNames', store.excludeNames,val)
    }
  }
})
