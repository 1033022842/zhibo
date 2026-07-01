<template>
  <div class="EditProfile">
    <BaseHeader>
      <template #center>
        <span class="header-title">编辑资料</span>
      </template>
    </BaseHeader>

    <div class="main">
      <div class="card">
        <div class="row row--avatar">
          <span class="row-label">头像</span>
          <div class="avatar-box">
            <img :src="store.authAvatar || defaultAvatar" alt="avatar" class="avatar-img" />
          </div>
        </div>

        <div class="row">
          <span class="row-label">用户编号</span>
          <span class="row-value mono">{{ store.authUserNo }}</span>
        </div>

        <div class="row">
          <span class="row-label">等级</span>
          <span class="row-value">
            <span class="badge badge--level">Lv.{{ store.authLevel }}</span>
          </span>
        </div>
      </div>

      <div class="card">
        <div class="row row--input">
          <span class="row-label">昵称</span>
          <div class="input-wrap">
            <input
              v-model="form.nickname"
              type="text"
              maxlength="50"
              placeholder="输入昵称"
              class="field"
            />
            <span class="char-count">{{ form.nickname.length }}/50</span>
          </div>
        </div>

        <div class="row row--gender">
          <span class="row-label">性别</span>
          <div class="gender-tabs">
            <button
              v-for="opt in genderOptions"
              :key="opt.value"
              class="gender-tab"
              :class="{ active: form.gender === opt.value }"
              @click="form.gender = opt.value"
            >
              <span class="gender-tab-icon">{{ opt.icon }}</span>
              <span>{{ opt.label }}</span>
            </button>
          </div>
        </div>

        <div class="row row--textarea">
          <span class="row-label">简介</span>
          <div class="input-wrap">
            <textarea
              v-model="form.bio"
              maxlength="200"
              placeholder="介绍一下自己吧..."
              rows="3"
              class="field field--area"
            ></textarea>
            <span class="char-count">{{ form.bio.length }}/200</span>
          </div>
        </div>
      </div>

      <div class="submit-zone">
        <button class="btn-save" :disabled="submitting" @click="handleSubmit">
          <span v-if="!submitting">保存修改</span>
          <span v-else class="saving-dots">
            <i></i><i></i><i></i>
          </span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import BaseHeader from '@/components/BaseHeader.vue'
import { useBaseStore } from '@/store/pinia'
import { getProfile, updateProfile } from '@/api/live'

defineOptions({ name: 'EditProfile' })

const router = useRouter()
const store = useBaseStore()

const defaultAvatar = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><rect fill="%231f2534" width="100" height="100" rx="50"/><circle cx="50" cy="38" r="16" fill="%23555"/><ellipse cx="50" cy="82" rx="28" ry="18" fill="%23555"/></svg>')

const form = reactive({ nickname: '', gender: 0, bio: '' })
const submitting = ref(false)

const genderOptions = [
  { value: 0, label: '未设置', icon: '⚬' },
  { value: 1, label: '男', icon: '♂' },
  { value: 2, label: '女', icon: '♀' }
]

onMounted(async () => {
  await store.fetchProfile()
  form.nickname = store.authNickname
  form.gender = store.authGender
  form.bio = store.authBio
})

async function handleSubmit() {
  if (!form.nickname.trim()) { alert('昵称不能为空'); return }
  submitting.value = true
  try {
    const res = await updateProfile({
      nickname: form.nickname.trim(),
      gender: form.gender,
      bio: form.bio.trim()
    })
    if (res.success) { await store.fetchProfile(); router.back() }
    else { alert((res as any).message || '保存失败') }
  } catch (e: any) { alert(e?.message || '保存失败') }
  finally { submitting.value = false }
}
</script>

<style scoped lang="less">
@import '@/assets/less/index';

@accent: #ff2d55;
@accent-glow: rgba(255, 45, 85, 0.3);
@surface: rgba(255, 255, 255, 0.04);
@border: rgba(255, 255, 255, 0.06);
@input-bg: rgba(255, 255, 255, 0.05);

@keyframes fade-up {
  from { opacity: 0; transform: translateY(12rem); }
  to { opacity: 1; transform: translateY(0); }
}

@keyframes saving-bounce {
  0%, 80%, 100% { transform: scale(0); opacity: 0.4; }
  40% { transform: scale(1); opacity: 1; }
}

.EditProfile {
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

.row {
  display: flex;
  align-items: center;
  padding: 14rem var(--page-padding);

  &:not(:last-child) {
    border-bottom: 1px solid @border;
  }

  &--avatar { justify-content: space-between; }
  &--input, &--textarea { flex-direction: column; align-items: stretch; gap: 10rem; }
  &--gender { justify-content: space-between; }
}

.row-label {
  font-size: 14rem;
  color: rgba(255, 255, 255, 0.5);
  letter-spacing: 0.3rem;
  flex-shrink: 0;
}

.row-value {
  font-size: 14rem;
  color: rgba(255, 255, 255, 0.65);

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

.avatar-box {
  .avatar-img {
    width: 52rem;
    height: 52rem;
    border-radius: 50%;
    object-fit: cover;
    border: 2rem solid rgba(255, 255, 255, 0.1);
    background: linear-gradient(135deg, #1a1d2e, #252840);
  }
}

.input-wrap {
  position: relative;
}

.field {
  width: 100%;
  padding: 12rem 14rem;
  background: @input-bg;
  border: 1px solid @border;
  border-radius: 10rem;
  color: #fff;
  font-size: 15rem;
  outline: none;
  transition: border-color 0.2s, background 0.2s;
  box-sizing: border-box;

  &::placeholder {
    color: rgba(255, 255, 255, 0.2);
  }

  &:focus {
    border-color: rgba(255, 45, 85, 0.4);
    background: rgba(255, 255, 255, 0.07);
  }

  &--area {
    resize: vertical;
    min-height: 80rem;
    font-family: inherit;
    line-height: 1.6;
  }
}

.char-count {
  position: absolute;
  right: 10rem;
  bottom: 8rem;
  font-size: 11rem;
  color: rgba(255, 255, 255, 0.2);
  pointer-events: none;
}

.gender-tabs {
  display: flex;
  gap: 6rem;
}

.gender-tab {
  display: flex;
  align-items: center;
  gap: 4rem;
  padding: 8rem 14rem;
  border: 1px solid @border;
  border-radius: 10rem;
  background: rgba(255, 255, 255, 0.03);
  color: rgba(255, 255, 255, 0.4);
  font-size: 13rem;
  cursor: pointer;
  transition: all 0.25s;

  &.active {
    background: linear-gradient(135deg, rgba(255, 45, 85, 0.2), rgba(255, 45, 85, 0.08));
    border-color: rgba(255, 45, 85, 0.4);
    color: @accent;
    box-shadow: 0 0 16px rgba(255, 45, 85, 0.1);
  }
}

.gender-tab-icon {
  font-size: 14rem;
}

.submit-zone {
  margin-top: 30rem;
  animation: fade-up 0.5s ease-out 200ms forwards;
  opacity: 0;
}

.btn-save {
  width: 100%;
  padding: 14rem;
  border: none;
  border-radius: 14rem;
  font-size: 16rem;
  font-weight: 700;
  color: #fff;
  cursor: pointer;
  position: relative;
  background: linear-gradient(135deg, @accent, #d42148);
  box-shadow: 0 4px 24px rgba(255, 45, 85, 0.3);
  transition: transform 0.2s, box-shadow 0.2s, opacity 0.2s;

  &::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 14rem;
    background: linear-gradient(135deg, rgba(255,255,255,0.2), transparent);
    pointer-events: none;
  }

  &:active {
    transform: scale(0.97);
    box-shadow: 0 2px 12px rgba(255, 45, 85, 0.2);
  }

  &:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
}

.saving-dots {
  display: inline-flex;
  gap: 6rem;
  align-items: center;
  justify-content: center;

  i {
    width: 8rem;
    height: 8rem;
    border-radius: 50%;
    background: #fff;
    display: inline-block;
    animation: saving-bounce 1.2s ease-in-out infinite;

    &:nth-child(2) { animation-delay: 0.2s; }
    &:nth-child(3) { animation-delay: 0.4s; }
  }
}
</style>
