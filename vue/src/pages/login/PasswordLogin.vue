<template>
  <div class="PasswordLogin">
    <BaseHeader mode="light" backMode="dark" backImg="back">
      <template v-slot:right>
        <span class="f14" @click="$router.push('/login/help')">帮助与设置</span>
      </template>
    </BaseHeader>
    <div class="content">
      <div class="desc">
        <div class="title">{{ isRegister ? '注册新账号' : '账号密码登录' }}</div>
      </div>

      <LoginInput
        autofocus
        type="email"
        v-model="email"
        placeholder="请输入邮箱"
      />
      <template v-if="isRegister">
        <div class="code-row mt1r">
          <LoginInput
            type="text"
            v-model="code"
            placeholder="请输入验证码"
            class="code-input"
          />
          <span class="send-code-btn" :class="{ disabled: codeSending }" @click="sendCode">
            {{ codeBtnText }}
          </span>
        </div>
      </template>
      <LoginInput
        class="mt1r"
        type="password"
        v-model="password"
        placeholder="请输入密码"
      />
      <LoginInput
        v-if="isRegister"
        class="mt1r"
        type="text"
        v-model="nickname"
        placeholder="请输入昵称"
      />

      <div class="protocol" :class="showAnim ? 'anim-bounce' : ''">
        <Tooltip style="top: -150%; left: -10rem" v-model="showTooltip" />
        <div class="left">
          <Check v-model="isAgree" />
        </div>
        <div class="right">
          已阅读并同意
          <span
            class="link"
            @click="goUserAgreement"
            >用户协议</span
          >
          和
          <span
            class="link"
            @click="goPrivacyPolicy"
            >隐私政策</span
          >
          ，同时登录并使用抖音火山版（原"火山小视频"）和抖音
        </div>
      </div>

      <div class="notice" v-if="notice">
        {{ notice }}
      </div>

      <dy-button
        type="primary"
        :loading="loading"
        :active="false"
        :disabled="disabled"
        @click="submit"
      >
        {{ loading ? (isRegister ? '注册中' : '登录中') : (isRegister ? '注册' : '登录') }}
      </dy-button>

      <div class="options">
        <span>
          <span
            v-if="!isRegister"
            class="link"
            @click="$router.push('/login/retrieve-password')"
          >找回密码</span>
          <span class="link" style="margin-left: 12rem" @click="toggleMode">
            {{ isRegister ? '已有账号？去登录' : '没有账号？去注册' }}
          </span>
        </span>
      </div>
    </div>
  </div>
</template>
<script>
import Check from '../../components/Check'
import LoginInput from './components/LoginInput'
import Tooltip from './components/Tooltip'
import Base from './Base'
import { useBaseStore } from '@/store/pinia'
import { _notice } from '@/utils'
import { request } from '@/utils/request'

export default {
  name: 'PasswordLogin',
  extends: Base,
  components: {
    Check,
    Tooltip,
    LoginInput
  },
  data() {
    return {
      email: '',
      password: '',
      nickname: '',
      code: '',
      notice: '',
      isRegister: false,
      codeSending: false,
      countdown: 0,
      countdownTimer: null
    }
  },
  computed: {
    disabled() {
      if (this.isRegister) {
        return !(this.email && this.code && this.password && this.nickname)
      }
      return !(this.email && this.password)
    },
    codeBtnText() {
      if (this.codeSending) return '发送中...'
      if (this.countdown > 0) return `${this.countdown}s`
      return '发送验证码'
    }
  },
  beforeDestroy() {
    if (this.countdownTimer) clearInterval(this.countdownTimer)
  },
  methods: {
    goUserAgreement() {
      this.$router.push('/service-protocol', { type: '\u201c抖音\u201d用户服务协议' })
    },
    goPrivacyPolicy() {
      this.$router.push('/service-protocol', { type: '\u201c抖音\u201d隐私政策' })
    },
    toggleMode() {
      this.isRegister = !this.isRegister
      this.notice = ''
      this.code = ''
    },
    async sendCode() {
      if (this.codeSending || this.countdown > 0) return

      const email = this.email.trim()
      if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        this.notice = '请输入正确的邮箱地址'
        return
      }

      this.notice = ''
      this.codeSending = true

      try {
        const res = await request({
          url: '/api/live/user/send-code',
          method: 'POST',
          data: { email }
        })
        if (res.success) {
          this.notice = ''
          this.startCountdown()
        } else {
          this.notice = (res.data && res.data.message) || '发送失败'
        }
      } catch (e) {
        this.notice = e?.message || '发送失败'
      }

      this.codeSending = false
    },
    startCountdown() {
      this.countdown = 60
      this.countdownTimer = setInterval(() => {
        this.countdown--
        if (this.countdown <= 0) {
          clearInterval(this.countdownTimer)
          this.countdownTimer = null
        }
      }, 1000)
    },
    async submit() {
      const ok = await this.check()
      if (!ok) return

      this.loading = true
      this.notice = ''

      const store = useBaseStore()

      if (this.isRegister) {
        const res = await store.doRegister(
          this.email.trim(),
          this.password,
          this.nickname.trim(),
          this.code
        )
        this.loading = false
        if (res.ok) {
          _notice('注册成功，正在登录...')
          const loginRes = await store.doLogin(this.email.trim(), this.password)
          if (loginRes.ok) {
            this.$router.replace('/home')
          } else {
            this.notice = loginRes.msg || '自动登录失败'
          }
        } else {
          this.notice = res.msg || '注册失败'
        }
      } else {
        const res = await store.doLogin(this.email.trim(), this.password)
        this.loading = false
        if (res.ok) {
          this.$router.replace('/home')
        } else {
          this.notice = res.msg || '登录失败'
        }
      }
    }
  }
}
</script>

<style scoped lang="less">
@import '../../assets/less/index';
@import 'Base.less';

.PasswordLogin {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  top: 0;
  overflow: auto;
  color: black;
  font-size: 14rem;
  background: white;

  .content {
    padding: 60rem 30rem;

    .desc {
      margin-bottom: 60rem;
      margin-top: 120rem;
      display: flex;
      align-items: center;
      flex-direction: column;

      .title {
        margin-bottom: 20rem;
        font-size: 20rem;
      }

      .phone-number {
        letter-spacing: 3rem;
        font-size: 30rem;
        margin-bottom: 10rem;
      }

      .sub-title {
        font-size: 12rem;
        color: var(--second-text-color);
      }
    }

    .button {
      width: 100%;
      margin-bottom: 5rem;
    }

    .protocol {
      position: relative;
      color: gray;
      margin-top: 20rem;
      font-size: 12rem;
      display: flex;

      .left {
        padding-top: 1rem;
        margin-right: 5rem;
      }
    }
    .options {
      position: relative;
      font-size: 14rem;
      display: flex;
    }
    .notice {
      color: #ff4d4f;
      font-size: 13rem;
      margin-bottom: 10rem;
      text-align: center;
    }
    .code-row {
      display: flex;
      align-items: center;
      gap: 10rem;
      .code-input {
        flex: 1;
      }
      .send-code-btn {
        flex-shrink: 0;
        color: var(--primary-color, #fe2c55);
        font-size: 13rem;
        cursor: pointer;
        white-space: nowrap;
        min-width: 80rem;
        text-align: center;
        &.disabled {
          color: #ccc;
          pointer-events: none;
        }
      }
    }
  }
}
</style>
