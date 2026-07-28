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
        v-if="!isRegister"
        autofocus
        type="email"
        v-model="email"
        placeholder="请输入邮箱"
      />
      <LoginInput
        v-if="isRegister"
        autofocus
        type="text"
        v-model="account"
        placeholder="请输入用户名或邮箱"
      />
      <LoginInput
        class="mt1r"
        type="password"
        v-model="password"
        placeholder="请输入密码"
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
          <span class="link" style="margin-left: 12rem" @click="goToLogin">
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
      account: '',
      password: '',
      notice: '',
      isRegister: false
    }
  },
  computed: {
    disabled() {
      if (this.isRegister) {
        return !(this.account && this.password)
      }
      return !(this.email && this.password)
    }
  },
  created() {
    if (this.$route.query.mode === 'register') {
      this.isRegister = true
    }
  },
  methods: {
    goUserAgreement() {
      this.$router.push('/service-protocol', { type: '\u201c抖音\u201d用户服务协议' })
    },
    goPrivacyPolicy() {
      this.$router.push('/service-protocol', { type: '\u201c抖音\u201d隐私政策' })
    },
    goToLogin() {
      if (this.isRegister) {
        this.$router.push('/login?redirect=/me')
      } else {
        this.toggleMode()
      }
    },
    toggleMode() {
      this.isRegister = !this.isRegister
      this.notice = ''
    },
    async submit() {
      const ok = await this.check()
      if (!ok) return

      this.loading = true
      this.notice = ''

      const store = useBaseStore()

      if (this.isRegister) {
        const res = await store.doRegisterFromAi(
          this.account.trim(),
          this.password
        )
        this.loading = false
        if (res.ok) {
          this.$router.replace('/home')
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
