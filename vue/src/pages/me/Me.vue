<template>
  <div class="Me">
    <div class="bg-glow bg-glow--top"></div>
    <div class="bg-glow bg-glow--bottom"></div>

    <div class="hero" v-if="loggedIn" v-anim>
      <div class="avatar-stage">
        <div class="avatar-ring"></div>
        <div class="avatar-inner">
          <img class="avatar-img" :src="avatarUrl || defaultAvatar" alt="avatar" />
        </div>
        <div class="avatar-sparkle sparkle--1"></div>
        <div class="avatar-sparkle sparkle--2"></div>
        <div class="avatar-sparkle sparkle--3"></div>
      </div>

      <div class="identity" v-anim>
        <h1 class="nickname">{{ nickname }}</h1>
        <p class="userno">ID: {{ userNo }}</p>
        <div class="chips">
          <span class="chip chip--level">
            <span class="chip-dot"></span>
            Lv.{{ level }}
          </span>
          <span class="chip chip--gender">{{ genderIcon }} {{ genderLabel }}</span>
        </div>
      </div>

      <p class="bio-text" v-if="bio" v-anim>{{ bio }}</p>
      <p class="bio-text bio-text--empty" v-else v-anim>写一句签名，让大家认识你</p>
    </div>

    <div v-if="loggedIn" class="actions" v-anim>
      <button class="btn-edit" @click="goEdit">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
        <span>编辑资料</span>
      </button>
    </div>

    <nav v-if="loggedIn" class="menu" v-anim>
      <div class="menu-group">
        <button class="menu-row" @click="goSetting">
          <span class="menu-icon menu-icon--violet">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          </span>
          <span class="menu-label">账号安全</span>
          <svg class="menu-chevron" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
        <button class="menu-row" @click="goAbout">
          <span class="menu-icon menu-icon--cyan">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          </span>
          <span class="menu-label">关于平台</span>
          <svg class="menu-chevron" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
      </div>
    </nav>

    <div v-if="loggedIn" class="logout-zone" v-anim>
      <button class="btn-logout" @click="handleLogout">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        <span>退出登录</span>
      </button>
    </div>

    <!-- 未登录状态 -->
    <div v-if="!loggedIn" class="login-prompt">
      <div class="login-prompt-icon">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"/><path d="M3 21v-2a7 7 0 0 1 7-7h4a7 7 0 0 1 7 7v2"/></svg>
      </div>
      <p class="login-prompt-text">登录后查看个人主页</p>
      <button class="btn-login" @click="goLogin">登 录</button>
    </div>

    <BaseFooter />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import BaseFooter from '@/components/BaseFooter.vue'
import { useBaseStore } from '@/store/pinia'

defineOptions({ name: 'Me' })

const router = useRouter()
const store = useBaseStore()
const loggedIn = computed(() => store.authUserId > 0)

const defaultAvatar = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><rect fill="%231f2534" width="100" height="100" rx="50"/><circle cx="50" cy="38" r="16" fill="%23555"/><ellipse cx="50" cy="82" rx="28" ry="18" fill="%23555"/></svg>')

const nickname = computed(() => store.authNickname || '未命名用户')
const userNo = computed(() => store.authUserNo || '')
const level = computed(() => store.authLevel)
const avatarUrl = computed(() => store.authAvatar || '')
const bio = computed(() => store.authBio)
const gender = computed(() => store.authGender)

const genderIcon = computed(() => {
  if (gender.value === 1) return '♂'
  if (gender.value === 2) return '♀'
  return '⚬'
})
const genderLabel = computed(() => {
  if (gender.value === 1) return '男'
  if (gender.value === 2) return '女'
  return '未设置'
})

onMounted(async () => {
  await store.fetchProfile()
})

function goEdit() { router.push('/me/edit') }
function goSetting() { router.push('/me/setting') }
function goAbout() { router.push('/me/setting?tab=about') }
function goLogin() { router.push('/login') }

async function handleLogout() {
  if (!confirm('确定要退出登录吗？')) return
  await store.doLogout()
  router.replace('/home')
}
</script>

<style scoped lang="less">
@import '@/assets/less/index';

@accent: #ff2d55;
@accent-glow: rgba(255, 45, 85, 0.35);
@cyan: #00d4aa;
@cyan-glow: rgba(0, 212, 170, 0.3);
@violet: #7c5cfc;
@violet-glow: rgba(124, 92, 252, 0.3);
@surface: rgba(255, 255, 255, 0.04);
@surface-hover: rgba(255, 255, 255, 0.07);
@border: rgba(255, 255, 255, 0.06);

@keyframes ring-pulse {
  0%, 100% { box-shadow: 0 0 0 0 @accent-glow, 0 0 0 0 rgba(0, 212, 170, 0.2), 0 0 20px 0 rgba(255, 45, 85, 0.15); }
  50% { box-shadow: 0 0 0 6px rgba(255, 45, 85, 0), 0 0 0 12px rgba(0, 212, 170, 0), 0 0 35px 0 rgba(255, 45, 85, 0.35); }
}

@keyframes sparkle-fade {
  0%, 100% { opacity: 0; transform: scale(0) rotate(0deg); }
  40% { opacity: 1; transform: scale(1) rotate(90deg); }
  80% { opacity: 0.6; transform: scale(0.6) rotate(180deg); }
}

@keyframes glow-drift-top {
  0%, 100% { transform: translate(-50%, -40%) scale(1); opacity: 0.5; }
  50% { transform: translate(-30%, -50%) scale(1.2); opacity: 0.8; }
}

@keyframes glow-drift-bottom {
  0%, 100% { transform: translate(30%, 50%) scale(1); opacity: 0.4; }
  50% { transform: translate(50%, 30%) scale(1.15); opacity: 0.7; }
}

@keyframes fade-slide-up {
  from { opacity: 0; transform: translateY(16rem); }
  to { opacity: 1; transform: translateY(0); }
}

@keyframes chip-appear {
  from { opacity: 0; transform: scale(0.8); }
  to { opacity: 1; transform: scale(1); }
}

.fade-slide(@delay: 0ms) {
  opacity: 0;
  animation: fade-slide-up 0.6s cubic-bezier(0.16, 1, 0.3, 1) @delay forwards;
}

.Me {
  position: relative;
  min-height: 100vh;
  background: linear-gradient(180deg, #0c0e18 0%, #11131f 40%, #0d0f1a 100%);
  padding: 0 0 calc(var(--footer-height) + 20rem);
  color: #fff;
  overflow: hidden;
}

.bg-glow {
  position: fixed;
  width: 70vw;
  height: 70vw;
  border-radius: 50%;
  filter: blur(80px);
  pointer-events: none;
  z-index: 0;

  &--top {
    top: -20%;
    left: 50%;
    transform: translate(-50%, -40%);
    background: radial-gradient(circle, @accent-glow 0%, transparent 70%);
    animation: glow-drift-top 8s ease-in-out infinite;
  }

  &--bottom {
    bottom: -25%;
    left: 30%;
    transform: translate(30%, 50%);
    background: radial-gradient(circle, @cyan-glow 0%, transparent 70%);
    animation: glow-drift-bottom 10s ease-in-out infinite;
  }
}

.hero {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 56rem 0 28rem;
}

.avatar-stage {
  position: relative;
  width: 90rem;
  height: 90rem;
  margin-bottom: 18rem;

  .avatar-ring {
    position: absolute;
    inset: -4rem;
    border-radius: 50%;
    background: conic-gradient(from 0deg, @accent, @cyan, @violet, @accent);
    animation: ring-pulse 3s ease-in-out infinite;
    mask: radial-gradient(farthest-side, transparent calc(100% - 3rem), #000 calc(100% - 2.5rem));
    -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 3rem), #000 calc(100% - 2.5rem));
  }

  .avatar-inner {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    overflow: hidden;
    border: 3rem solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(4px);

    .avatar-img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      background: linear-gradient(135deg, #1a1d2e, #252840);
    }
  }

  .avatar-sparkle {
    position: absolute;
    width: 8rem;
    height: 8rem;
    border-radius: 50%;
    background: @accent;
    filter: blur(1px);

    &.sparkle--1 { top: -2rem; right: 10rem; animation: sparkle-fade 2.5s ease-in-out 0s infinite; }
    &.sparkle--2 { bottom: 8rem; left: -2rem; animation: sparkle-fade 2.5s ease-in-out 0.8s infinite; background: @cyan; }
    &.sparkle--3 { top: 12rem; right: -4rem; animation: sparkle-fade 2.5s ease-in-out 1.6s infinite; background: @violet; }
  }
}

.identity {
  text-align: center;

  .nickname {
    font-size: 22rem;
    font-weight: 700;
    letter-spacing: 0.5rem;
    margin: 0 0 6rem;
    background: linear-gradient(135deg, #fff 0%, #d4d4e8 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
  }

  .userno {
    font-size: 12rem;
    color: rgba(255, 255, 255, 0.35);
    margin: 0 0 14rem;
    letter-spacing: 0.5rem;
    font-family: 'SF Mono', 'Cascadia Code', monospace;
  }
}

.chips {
  display: flex;
  gap: 8rem;
  justify-content: center;
}

.chip {
  display: inline-flex;
  align-items: center;
  gap: 5rem;
  font-size: 12rem;
  padding: 5rem 12rem;
  border-radius: 20rem;
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.08);

  .chip-dot {
    width: 6rem;
    height: 6rem;
    border-radius: 50%;
  }

  &--level {
    background: linear-gradient(135deg, rgba(255, 45, 85, 0.15), rgba(255, 45, 85, 0.05));
    color: @accent;
    border-color: rgba(255, 45, 85, 0.2);

    .chip-dot {
      background: @accent;
      box-shadow: 0 0 6px @accent-glow;
    }
  }

  &--gender {
    background: rgba(255, 255, 255, 0.05);
    color: rgba(255, 255, 255, 0.55);
  }
}

.bio-text {
  max-width: 260rem;
  text-align: center;
  font-size: 14rem;
  line-height: 1.65;
  color: rgba(255, 255, 255, 0.45);
  margin: 20rem 0 0;

  &--empty {
    opacity: 0.35;
    font-style: italic;
    font-size: 13rem;
  }
}

.actions {
  position: relative;
  z-index: 1;
  display: flex;
  justify-content: center;
  padding: 0 0 30rem;
}

.btn-edit {
  display: inline-flex;
  align-items: center;
  gap: 8rem;
  padding: 12rem 32rem;
  border: none;
  border-radius: 30rem;
  font-size: 14rem;
  font-weight: 600;
  color: #fff;
  cursor: pointer;
  position: relative;
  background: linear-gradient(135deg, @accent, #d42148);
  box-shadow: 0 4px 20px rgba(255, 45, 85, 0.3);
  transition: transform 0.2s, box-shadow 0.2s;

  &::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 30rem;
    background: linear-gradient(135deg, rgba(255,255,255,0.2), transparent);
    pointer-events: none;
  }

  &:active {
    transform: scale(0.96);
    box-shadow: 0 2px 10px rgba(255, 45, 85, 0.2);
  }

  svg {
    width: 16rem;
    height: 16rem;
  }
}

.menu {
  position: relative;
  z-index: 1;
  padding: 0 var(--page-padding);
  margin-bottom: 30rem;
}

.menu-group {
  background: linear-gradient(135deg, @surface, rgba(255,255,255,0.02));
  border: 1px solid @border;
  border-radius: 16rem;
  overflow: hidden;
  backdrop-filter: blur(20px);
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.menu-row {
  display: flex;
  align-items: center;
  width: 100%;
  padding: 16rem var(--page-padding);
  border: none;
  background: transparent;
  color: #fff;
  cursor: pointer;
  font-size: 15rem;
  transition: background 0.2s;

  &:not(:last-child) {
    border-bottom: 1px solid @border;
  }

  &:hover {
    background: @surface-hover;
  }

  &:active {
    background: rgba(255, 255, 255, 0.1);
  }
}

.menu-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36rem;
  height: 36rem;
  border-radius: 10rem;
  margin-right: 14rem;

  &--violet {
    background: linear-gradient(135deg, rgba(124, 92, 252, 0.15), rgba(124, 92, 252, 0.05));
    color: @violet;
  }

  &--cyan {
    background: linear-gradient(135deg, rgba(0, 212, 170, 0.15), rgba(0, 212, 170, 0.05));
    color: @cyan;
  }
}

.menu-label {
  flex: 1;
  text-align: left;
  font-weight: 500;
  letter-spacing: 0.3rem;
}

.menu-chevron {
  width: 20rem;
  height: 20rem;
  color: rgba(255, 255, 255, 0.25);
  flex-shrink: 0;
}

.logout-zone {
  position: relative;
  z-index: 1;
  display: flex;
  justify-content: center;
  padding: 0 var(--page-padding);
}

.btn-logout {
  display: inline-flex;
  align-items: center;
  gap: 8rem;
  padding: 12rem 0;
  border: none;
  background: none;
  color: rgba(255, 255, 255, 0.3);
  cursor: pointer;
  font-size: 14rem;
  transition: color 0.2s;

  &:hover {
    color: rgba(255, 45, 85, 0.7);
  }

  svg {
    width: 18rem;
    height: 18rem;
  }
}

.login-prompt {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 120rem 0 60rem;

  .login-prompt-icon {
    color: rgba(255, 255, 255, 0.15);
    margin-bottom: 20rem;
  }

  .login-prompt-text {
    font-size: 15rem;
    color: rgba(255, 255, 255, 0.35);
    margin: 0 0 32rem;
  }
}

.btn-login {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 200rem;
  padding: 14rem 48rem;
  border: none;
  border-radius: 30rem;
  font-size: 16rem;
  font-weight: 600;
  color: #fff;
  cursor: pointer;
  letter-spacing: 4rem;
  background: linear-gradient(135deg, @accent, #d42148);
  box-shadow: 0 4px 24px rgba(255, 45, 85, 0.35);
  transition: transform 0.2s, box-shadow 0.2s;

  &:active {
    transform: scale(0.96);
    box-shadow: 0 2px 12px rgba(255, 45, 85, 0.2);
  }
}

/* entrance animations — applied via v-anim directive */
.hero {
  .avatar-stage { .fade-slide(0ms); }
  .identity { .fade-slide(100ms); }
  .chips .chip {
    opacity: 0;
    &:nth-child(1) { animation: chip-appear 0.4s ease-out 150ms forwards; }
    &:nth-child(2) { animation: chip-appear 0.4s ease-out 200ms forwards; }
  }
  .bio-text { .fade-slide(250ms); }
}

.actions { .fade-slide(300ms); }
.menu { .fade-slide(400ms); }
.logout-zone { .fade-slide(500ms); }
.login-prompt { .fade-slide(100ms); }
</style>
