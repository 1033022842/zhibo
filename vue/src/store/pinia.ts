import { defineStore } from 'pinia'
import { friends, panel } from '@/api/user'
import enums from '@/utils/enums'
import resource from '@/assets/data/resource'
import { request } from '@/utils/request'
import {
  setTokens,
  clearTokens,
  setStoredUserInfo,
  getStoredUserInfo,
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
      excludeNames: [],
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
      friends: resource.users,
      message: '',
      // 认证状态
      isAuthReady: false,
      authUserId: 0,
      authUserNo: '',
      authNickname: '',
      authAvatar: ''
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
      const stored = getStoredUserInfo()
      if (stored) {
        this.authUserId = stored.id
        this.authUserNo = stored.user_no
        this.authNickname = stored.nickname
        this.authAvatar = stored.avatar
        this.userinfo.nickname = stored.nickname
        this.isAuthReady = true
      } else {
        this.fetchProfile().finally(() => {
          this.isAuthReady = true
        })
      }
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
          this.userinfo.nickname = user.nickname
          this.isAuthReady = true

          return { ok: true, msg: '登录成功' }
        }

        return { ok: false, msg: (res.data as any)?.message || '登录失败' }
      } catch (e: any) {
        return { ok: false, msg: e?.message || '网络错误' }
      }
    },

    async doRegister(
      username: string,
      password: string,
      nickname: string
    ): Promise<{ ok: boolean; msg: string }> {
      try {
        const res = await request<any>({
          url: '/api/live/register',
          method: 'POST',
          data: { username, password, nickname }
        })

        if (res.success || res.data?.code === 200) {
          return { ok: true, msg: '注册成功' }
        }

        return { ok: false, msg: (res.data as any)?.message || '注册失败' }
      } catch (e: any) {
        return { ok: false, msg: e?.message || '网络错误' }
      }
    },

    async doLogout(): Promise<void> {
      try {
        await request({
          url: '/api/live/logout',
          method: 'POST'
        })
      } catch {
        // 即使请求失败也要清除本地状态
      }
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
          const user = res.data
          setStoredUserInfo(user)
          this.authUserId = user.id
          this.authUserNo = user.user_no
          this.authNickname = user.nickname
          this.authAvatar = user.avatar
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
