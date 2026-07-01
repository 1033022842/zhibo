<template>
  <div class="Setting">
    <BaseHeader>
      <template #center>
        <span class="header-title">{{ isAbout ? '关于平台' : '设置' }}</span>
      </template>
    </BaseHeader>

    <div class="main">
      <template v-if="!isAbout">
        <div class="card">
          <div class="section-label">账号信息</div>
          <div class="info-item">
            <span class="info-key">用户编号</span>
            <span class="info-val mono">{{ store.authUserNo }}</span>
          </div>
          <div class="info-item">
            <span class="info-key">昵称</span>
            <span class="info-val">{{ store.authNickname }}</span>
          </div>
          <div class="info-item">
            <span class="info-key">等级</span>
            <span class="info-val">
              <span class="badge badge--level">Lv.{{ store.authLevel }}</span>
            </span>
          </div>
          <div class="info-item">
            <span class="info-key">登录方式</span>
            <span class="info-val">邮箱登录</span>
          </div>
        </div>

        <div class="card">
          <button class="nav-row" @click="isAbout = true">
            <span class="nav-icon nav-icon--cyan">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            </span>
            <span class="nav-label">关于平台</span>
            <svg class="nav-chevron" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
          </button>
        </div>
      </template>

      <template v-if="isAbout">
        <div class="card">
          <div class="section-label">平台信息</div>
          <div class="info-item">
            <span class="info-key">名称</span>
            <span class="info-val">虚拟直播平台</span>
          </div>
          <div class="info-item">
            <span class="info-key">版本号</span>
            <span class="info-val mono">{{ store.version }}</span>
          </div>
          <div class="info-item info-item--bio">
            <span class="info-key">简介</span>
            <span class="info-val">基于 AI 驱动的虚拟人直播互动平台，提供沉浸式直播体验</span>
          </div>
        </div>
      </template>

      <div class="logout-card">
        <button class="btn-logout" @click="handleLogout">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          <span>退出登录</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import BaseHeader from '@/components/BaseHeader.vue'
import { useBaseStore } from '@/store/pinia'

defineOptions({ name: 'Setting' })

const router = useRouter()
const route = useRoute()
const store = useBaseStore()
const isAbout = ref(false)

onMounted(() => {
  if (route.query.tab === 'about') isAbout.value = true
})

async function handleLogout() {
  if (!confirm('确定要退出登录吗？')) return
  await store.doLogout()
  router.replace('/home')
}
</script>

<style scoped lang="less">
@import '@/assets/less/index';

@accent: #ff2d55;
@accent-glow: rgba(255, 45, 85, 0.15);
@cyan: #00d4aa;
@surface: rgba(255, 255, 255, 0.04);
@border: rgba(255, 255, 255, 0.06);

@keyframes fade-up {
  from { opacity: 0; transform: translateY(12rem); }
  to { opacity: 1; transform: translateY(0); }
}

.Setting {
  min-height: 100vh;
  background: linear-gradient(180deg, #0c0e18, #11131f);
  color: #fff;
  padding-top: var(--common-header-height);
}

.header-title {
  font-size: 16rem;
  font-weight: 700;
  letter-spacing: 1rem;
  background: linear-gradient(135deg, #fff, #c0c0d4);
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
}

.main {
  padding: var(--page-padding);
}

.card {
  background: linear-gradient(135deg, @surface, rgba(255,255,255,0.02));
  border: 1px solid @border;
  border-radius: 16rem;
  margin-bottom: 14rem;
  overflow: hidden;
  backdrop-filter: blur(20px);
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);

  &:nth-child(1) { animation: fade-up 0.5s ease-out 0ms forwards; opacity: 0; }
  &:nth-child(2) { animation: fade-up 0.5s ease-out 80ms forwards; opacity: 0; }
}

.section-label {
  padding: 14rem var(--page-padding) 8rem;
  font-size: 12rem;
  color: rgba(255, 255, 255, 0.3);
  letter-spacing: 1rem;
  text-transform: uppercase;
}

.info-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14rem var(--page-padding);

  &:not(:last-child) {
    border-bottom: 1px solid @border;
  }

  &--bio .info-val {
    max-width: 180rem;
    text-align: right;
    line-height: 1.5;
  }
}

.info-key {
  font-size: 14rem;
  color: rgba(255, 255, 255, 0.5);
  flex-shrink: 0;
}

.info-val {
  font-size: 14rem;
  color: rgba(255, 255, 255, 0.7);

  &.mono {
    font-family: 'SF Mono', 'Cascadia Code', monospace;
    font-size: 13rem;
    letter-spacing: 0.5rem;
    color: rgba(255, 255, 255, 0.4);
  }
}

.badge {
  display: inline-block;
  font-size: 12rem;
  padding: 3rem 10rem;
  border-radius: 10rem;

  &--level {
    background: linear-gradient(135deg, rgba(255, 45, 85, 0.15), rgba(255, 45, 85, 0.05));
    color: @accent;
    border: 1px solid rgba(255, 45, 85, 0.2);
  }
}

.nav-row {
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

  &:hover { background: rgba(255, 255, 255, 0.05); }
  &:active { background: rgba(255, 255, 255, 0.1); }
}

.nav-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36rem;
  height: 36rem;
  border-radius: 10rem;
  margin-right: 14rem;

  &--cyan {
    background: linear-gradient(135deg, rgba(0, 212, 170, 0.15), rgba(0, 212, 170, 0.05));
    color: @cyan;
  }
}

.nav-label {
  flex: 1;
  text-align: left;
  font-weight: 500;
}

.nav-chevron {
  width: 20rem;
  height: 20rem;
  color: rgba(255, 255, 255, 0.2);
  flex-shrink: 0;
}

.logout-card {
  margin-top: 8rem;
  animation: fade-up 0.5s ease-out 200ms forwards;
  opacity: 0;
}

.btn-logout {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8rem;
  width: 100%;
  padding: 16rem;
  border: 1px solid rgba(255, 45, 85, 0.08);
  border-radius: 16rem;
  background: rgba(255, 45, 85, 0.04);
  color: rgba(255, 45, 85, 0.55);
  font-size: 15rem;
  cursor: pointer;
  transition: all 0.2s;

  &:hover {
    background: rgba(255, 45, 85, 0.08);
    color: rgba(255, 45, 85, 0.8);
  }

  &:active {
    background: rgba(255, 45, 85, 0.12);
  }

  svg {
    width: 18rem;
    height: 18rem;
  }
}
</style>
