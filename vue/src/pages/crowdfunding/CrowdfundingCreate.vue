<template>
  <div class="CFCreatePage">
    <div class="bg-glow bg-glow--top"></div>
    <div class="bg-glow bg-glow--bottom"></div>

    <div class="top-bar">
      <button class="btn-back" @click="$router.back()">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
      </button>
      <span class="top-title">发起角色众筹</span>
    </div>

    <form class="form" v-anim @submit.prevent="doSubmit">
      <!-- 封面图 -->
      <div class="form-group">
        <label class="form-label">封面图</label>
        <div class="cover-upload" @click="triggerUpload">
          <img v-if="form.cover_url" :src="form.cover_url" class="cover-preview" />
          <div v-else class="cover-placeholder">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            <span>点击上传封面</span>
          </div>
        </div>
        <input ref="fileInput" type="file" accept="image/*" style="display:none" @change="onFileChange" />
      </div>

      <!-- 角色名称 -->
      <div class="form-group">
        <label class="form-label">角色名称 <span class="required">*</span></label>
        <input v-model="form.persona_name" class="form-input" placeholder="给你的角色起个名字" maxlength="50" />
      </div>

      <!-- 项目标题 -->
      <div class="form-group">
        <label class="form-label">项目标题 <span class="required">*</span></label>
        <input v-model="form.title" class="form-input" placeholder="一句话描述你的角色创意" maxlength="100" />
      </div>

      <!-- 详细描述 -->
      <div class="form-group">
        <label class="form-label">详细描述</label>
        <textarea v-model="form.description" class="form-textarea" placeholder="描述你的角色人设、风格、创意理念…" rows="5"></textarea>
      </div>

      <!-- 目标金额 -->
      <div class="form-group">
        <label class="form-label">目标金额（钻石）<span class="required">*</span></label>
        <div class="amount-row">
          <input v-model.number="form.target_amount" type="number" class="form-input" placeholder="例如：10000" min="1" />
          <span class="amount-unit">钻</span>
        </div>
        <p class="form-hint">设置一个合理的目标金额，这将决定你的角色能否创建</p>
      </div>

      <!-- 截止时间 -->
      <div class="form-group">
        <label class="form-label">截止时间 <span class="required">*</span></label>
        <input v-model="form.deadline" type="datetime-local" class="form-input" :min="minDeadline" />
        <p class="form-hint">超过截止时间未达标将自动退款给支持者</p>
      </div>

      <!-- 协议提示 -->
      <div class="agreement">
        <p>发起即表示同意：</p>
        <ul>
          <li>无法达标将全额退还支持者</li>
          <li>达标后需手动创建角色并关联</li>
          <li>每个商家同时只能有一个进行中的众筹</li>
        </ul>
      </div>

      <!-- 提交 -->
      <button class="btn-submit" type="submit" :disabled="submitting">
        {{ submitting ? '提交中...' : '发起众筹' }}
      </button>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { initiateCrowdfunding } from '@/api/crowdfunding'
import { uploadCertificationImage } from '@/api/merchant'

const router = useRouter()
const submitting = ref(false)
const fileInput = ref<HTMLInputElement>()

const form = ref({
  cover_url: '',
  persona_name: '',
  title: '',
  description: '',
  target_amount: 0,
  deadline: '',
})

const minDeadline = computed(() => {
  const d = new Date()
  d.setMinutes(d.getMinutes() - d.getTimezoneOffset())
  return d.toISOString().slice(0, 16)
})

function triggerUpload() {
  fileInput.value?.click()
}

async function onFileChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  try {
    const res = await uploadCertificationImage(file)
    const urlData: any = (res.data as any)?.data || res.data
    form.value.cover_url = urlData?.url || urlData || ''
  } catch {
    alert('上传失败')
  }
}

async function doSubmit() {
  if (!form.value.title.trim()) { alert('请输入项目标题'); return }
  if (!form.value.persona_name.trim()) { alert('请输入角色名称'); return }
  if (!form.value.target_amount || form.value.target_amount <= 0) { alert('请输入有效的目标金额'); return }
  if (!form.value.deadline) { alert('请选择截止时间'); return }

  submitting.value = true
  try {
    const res: any = await initiateCrowdfunding({
      ...form.value,
      target_amount: Number(form.value.target_amount),
    })
    const respData = res?.data
    // 检查后端返回的错误码
    if (respData && respData.code && respData.code !== '00000') {
      alert(respData.msg || '发起失败')
      submitting.value = false
      return
    }
    const projectData = (respData as any)?.data || respData
    if (projectData && projectData.id) {
      alert('众筹项目已发起！')
      router.replace('/crowdfunding/list')
      return
    }
    // 没有明确的成功标记时也弹成功（兼容一些返回格式）
    alert('众筹项目已发起！')
    router.replace('/crowdfunding/list')
  } catch (e: any) {
    alert(e?.message || e?.msg || '发起失败')
  }
  submitting.value = false
}
</script>

<style scoped>
.CFCreatePage {
  min-height: 100vh;
  background: #0a0a14;
  color: #fff;
  padding-bottom: 40px;
  position: relative;
  overflow: hidden;
}
.bg-glow {
  position: fixed;
  border-radius: 50%;
  filter: blur(120px);
  opacity: 0.12;
  pointer-events: none;
  z-index: 0;
}
.bg-glow--top {
  top: -120px; left: -80px;
  width: 320px; height: 320px;
  background: radial-gradient(circle, #6366f1, transparent);
}
.bg-glow--bottom {
  bottom: -120px; right: -80px;
  width: 320px; height: 320px;
  background: radial-gradient(circle, #a855f7, transparent);
}
.top-bar {
  position: relative; z-index: 1;
  display: flex; align-items: center; gap: 8px;
  padding: 16px;
}
.btn-back {
  background: none; border: none;
  color: #fff; cursor: pointer;
  padding: 4px; display: flex;
}
.top-title { font-size: 18px; font-weight: 700; }

.form {
  position: relative; z-index: 1;
  padding: 0 16px;
}
.form-group { margin-bottom: 20px; }
.form-label {
  display: block;
  font-size: 13px; font-weight: 600;
  color: rgba(255,255,255,0.7);
  margin-bottom: 8px;
}
.required { color: #ff2d55; }
.form-input {
  width: 100%;
  padding: 12px;
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 10px;
  color: #fff;
  font-size: 15px;
  outline: none;
  transition: border-color .2s;
  box-sizing: border-box;
}
.form-input:focus { border-color: rgba(99,102,241,0.5); }
.form-input::placeholder { color: rgba(255,255,255,0.25); }
.form-textarea {
  width: 100%;
  padding: 12px;
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 10px;
  color: #fff;
  font-size: 14px;
  outline: none;
  resize: vertical;
  font-family: inherit;
  box-sizing: border-box;
}
.form-textarea:focus { border-color: rgba(99,102,241,0.5); }
.form-textarea::placeholder { color: rgba(255,255,255,0.25); }
.amount-row { display: flex; align-items: center; gap: 8px; }
.amount-unit { font-size: 15px; color: rgba(255,255,255,0.4); }
.form-hint {
  font-size: 11px; color: rgba(255,255,255,0.3);
  margin-top: 6px;
}

.cover-upload {
  width: 100%;
  aspect-ratio: 16/9;
  border: 2px dashed rgba(255,255,255,0.15);
  border-radius: 12px;
  overflow: hidden;
  cursor: pointer;
  transition: border-color .2s;
}
.cover-upload:hover { border-color: rgba(99,102,241,0.4); }
.cover-preview {
  width: 100%; height: 100%;
  object-fit: cover;
}
.cover-placeholder {
  width: 100%; height: 100%;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  gap: 8px;
  color: rgba(255,255,255,0.25);
  font-size: 13px;
}

.agreement {
  padding: 16px;
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 10px;
  margin-bottom: 20px;
  font-size: 12px;
  color: rgba(255,255,255,0.4);
  line-height: 1.8;
}
.agreement p { margin-bottom: 4px; }
.agreement ul { padding-left: 16px; }

.btn-submit {
  width: 100%;
  padding: 14px;
  border: none;
  border-radius: 12px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  font-size: 16px;
  font-weight: 700;
  cursor: pointer;
  transition: opacity .15s;
}
.btn-submit:disabled { opacity: 0.4; cursor: not-allowed; }
.btn-submit:active:not(:disabled) { opacity: 0.85; }
</style>
