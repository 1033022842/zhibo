<template>
  <div class="login">
    <BaseHeader mode="light" backMode="dark" backImg="close" title="登录" />

    <div class="content">
      <div class="welcome">
        <div class="welcome-title">欢迎来到 AI Live</div>
        <div class="welcome-sub">使用 AI 女友账号登录</div>
      </div>

      <div class="form">
        <div class="input-group">
          <div class="input-label">用户名 / 邮箱</div>
          <input
            v-model="data.username"
            class="input-field"
            placeholder="请输入用户名或邮箱"
            @keyup.enter="handleLogin"
          />
        </div>

        <div class="input-group">
          <div class="input-label">密码</div>
          <input
            v-model="data.password"
            class="input-field"
            type="password"
            placeholder="请输入密码"
            @keyup.enter="handleLogin"
          />
        </div>

        <div v-if="data.errorMsg" class="error-msg">{{ data.errorMsg }}</div>

        <dy-button
          type="primary"
          :loading="data.loading"
          :active="data.username !== '' && data.password !== ''"
          :loadingWithText="true"
          @click="handleLogin"
        >
          {{ data.loading ? '登录中...' : '登 录' }}
        </dy-button>
      </div>

      <div class="extra">
        <span class="link" @click="goRegister">还没有账号？去注册</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useBaseStore } from '@/store/pinia'
import { isLoggedIn } from '@/utils/auth'

const router = useRouter()

defineOptions({
  name: 'login'
})

const baseStore = useBaseStore()

const data = reactive({
  username: '',
  password: '',
  loading: false,
  errorMsg: ''
})

onMounted(() => {
  // 已登录直接跳首页
  if (isLoggedIn()) {
    router.push('/')
  }
})

async function handleLogin() {
  data.errorMsg = ''

  if (!data.username.trim()) {
    data.errorMsg = '请输入用户名或邮箱'
    return
  }
  if (!data.password) {
    data.errorMsg = '请输入密码'
    return
  }

  data.loading = true
  try {
    const result = await baseStore.doLogin(data.username.trim(), data.password)
    if (result.ok) {
      const redirect = router.currentRoute.value.query.redirect as string
      router.push(redirect || '/')
    } else {
      data.errorMsg = result.msg || '登录失败'
    }
  } catch {
    data.errorMsg = '网络错误，请稍后重试'
  } finally {
    data.loading = false
  }
}

function goRegister() {
  window.location.href = 'http://127.0.0.1:8080/Login.html'
}
</script>

<style scoped lang="less">
@import '../../assets/less/index';

.login {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  top: 0;
  overflow: auto;
  background: #0f0f0f;
  color: #fff;

  .content {
    padding: 30rem 28rem;

    .welcome {
      margin-top: 40rem;
      margin-bottom: 50rem;
      text-align: center;

      .welcome-title {
        font-size: 24rem;
        font-weight: 600;
        margin-bottom: 10rem;
      }

      .welcome-sub {
        font-size: 14rem;
        color: #888;
      }
    }

    .form {
      .input-group {
        margin-bottom: 20rem;

        .input-label {
          font-size: 14rem;
          color: #ccc;
          margin-bottom: 8rem;
        }

        .input-field {
          width: 100%;
          height: 48rem;
          background: #1a1a1a;
          border: 1px solid #333;
          border-radius: 8rem;
          padding: 0 14rem;
          font-size: 15rem;
          color: #fff;
          outline: none;
          box-sizing: border-box;

          &:focus {
            border-color: #e75275;
          }

          &::placeholder {
            color: #555;
          }
        }
      }

      .error-msg {
        color: #ff4d4f;
        font-size: 13rem;
        margin-bottom: 12rem;
        padding: 8rem 12rem;
        background: rgba(255, 77, 79, 0.1);
        border-radius: 6rem;
      }
    }

    .extra {
      margin-top: 30rem;
      text-align: center;

      .link {
        color: #e75275;
        font-size: 14rem;
        cursor: pointer;
      }
    }
  }
}
</style>
