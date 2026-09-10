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
      <div class="section-label">基础信息</div>

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

      <!-- 角色描述 -->
      <div class="form-group">
        <label class="form-label">角色描述 <span class="required">*</span></label>
        <textarea
          v-model="form.description"
          class="form-textarea"
          placeholder="详细介绍角色：背景故事、性格人设、外观与穿搭、说话方式、日常互动场景、适合的玩法与受众…"
          rows="9"
          maxlength="5000"
        ></textarea>
        <p class="form-hint" :class="descOk ? 'hint-ok' : 'hint-warn'">
          {{ descLen }} / 200 字{{ descOk ? '' : '（不少于200字）' }}
        </p>
      </div>

      <div class="section-label">角色设定</div>

      <!-- 标签 -->
      <div class="form-group">
        <label class="form-label">标签 <span class="required">*</span></label>
        <input v-model="form.tags" class="form-input" placeholder="逗号分隔，例如：御姐,甜美,高冷,粘人" />
        <p class="form-hint">最多 10 个标签，中英文逗号均可</p>
      </div>

      <!-- 风格 / 性别 -->
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">风格 <span class="required">*</span></label>
          <select v-model="form.style" class="form-input">
            <option value="">请选择风格</option>
            <option v-for="o in styleOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">角色性别</label>
          <select v-model="form.gender" class="form-input">
            <option value="">不限</option>
            <option v-for="o in genderOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
      </div>

      <!-- 年龄段 / 语言 -->
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">年龄段</label>
          <select v-model="form.age_range" class="form-input">
            <option value="">不限</option>
            <option v-for="o in ageOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">语言</label>
          <select v-model="form.language" class="form-input">
            <option value="">不限</option>
            <option v-for="o in languageOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
      </div>

      <!-- 性格特点 -->
      <div class="form-group">
        <label class="form-label">性格特点</label>
        <input v-model="form.personality" class="form-input" placeholder="逗号分隔，例如：温柔,幽默,小傲娇" />
      </div>

      <!-- 语音风格 -->
      <div class="form-group">
        <label class="form-label">语音风格</label>
        <select v-model="form.voice_style" class="form-input">
          <option value="">不限</option>
          <option v-for="o in voiceOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
        </select>
      </div>

      <div class="section-label">交付与合规</div>

      <!-- 交付内容 -->
      <div class="form-group">
        <label class="form-label">交付内容 <span class="required">*</span></label>
        <div class="chips">
          <button
            v-for="o in deliverOptions" :key="o.value"
            type="button"
            class="chip"
            :class="{ on: form.deliverables.includes(o.value) }"
            @click="toggleDeliver(o.value)"
          >{{ o.label }}</button>
        </div>
        <p class="form-hint">可多选，至少选择一项</p>
      </div>

      <!-- 18+ -->
      <div class="form-group">
        <label class="form-label">是否为 18+ 内容 <span class="required">*</span></label>
        <div class="chips">
          <button type="button" class="chip chip--wide" :class="{ on: form.is_adult === 0 }" @click="form.is_adult = 0">否 · 全年龄</button>
          <button type="button" class="chip chip--wide" :class="{ on: form.is_adult === 1 }" @click="form.is_adult = 1">是 · 18+</button>
        </div>
        <p class="form-hint">18+ 内容仅对成年用户开放展示</p>
      </div>

      <!-- 项目亮点 -->
      <div class="form-group">
        <label class="form-label">项目亮点</label>
        <textarea v-model="form.highlights" class="form-textarea" placeholder="一句话卖点或差异化优势，可分行" rows="3" maxlength="500"></textarea>
      </div>

      <!-- 参考链接 -->
      <div class="form-group">
        <label class="form-label">参考链接</label>
        <input v-model="form.reference_url" class="form-input" placeholder="https://... （可选，风格参考/作品集）" />
      </div>

      <div class="section-label">众筹设置</div>

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
          <li>18+ 内容需符合平台内容规范</li>
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
  tags: '',
  style: '',
  gender: '',
  age_range: '',
  language: '',
  personality: '',
  voice_style: '',
  deliverables: [] as string[],
  is_adult: 0,
  highlights: '',
  reference_url: '',
  target_amount: 0,
  deadline: '',
})

const styleOptions = [
  { value: 'realistic', label: '写实' },
  { value: 'anime', label: '二次元' },
  { value: '3d', label: '3D' },
  { value: 'cyberpunk', label: '赛博朋克' },
  { value: 'chinese', label: '古风' },
  { value: 'korean', label: '韩系' },
  { value: 'western', label: '欧美' },
]
const genderOptions = [
  { value: 'female', label: '女性' },
  { value: 'male', label: '男性' },
  { value: 'other', label: '其他' },
]
const ageOptions = [
  { value: '18-22', label: '18-22 岁' },
  { value: '23-27', label: '23-27 岁' },
  { value: '28-35', label: '28-35 岁' },
  { value: '36-45', label: '36-45 岁' },
  { value: '45+', label: '45 岁以上' },
]
const languageOptions = [
  { value: 'zh-CN', label: '中文' },
  { value: 'en-US', label: '英文' },
  { value: 'ja-JP', label: '日文' },
  { value: 'ms-MY', label: '马来语' },
  { value: 'multi', label: '多语言' },
]
const voiceOptions = [
  { value: 'sweet', label: '甜美' },
  { value: 'mature', label: '御姐' },
  { value: 'magnetic', label: '磁性' },
  { value: 'loli', label: '萝莉' },
  { value: 'cold', label: '冷艳' },
  { value: 'gentle', label: '温柔' },
  { value: 'none', label: '不涉及语音' },
]
const deliverOptions = [
  { value: 'portrait', label: '立绘' },
  { value: 'voice', label: '语音' },
  { value: 'video', label: '短视频' },
  { value: 'live', label: '直播' },
  { value: 'chat', label: 'AI 聊天' },
]

const descLen = computed(() => form.value.description.trim().length)
const descOk = computed(() => descLen.value >= 200)

function toggleDeliver(v: string) {
  const i = form.value.deliverables.indexOf(v)
  if (i === -1) form.value.deliverables.push(v)
  else form.value.deliverables.splice(i, 1)
}

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

// 中英文逗号分隔 -> 去重数组
function splitList(v: string): string[] {
  return String(v || '')
    .replace(/，/g, ',')
    .split(',')
    .map((s) => s.trim())
    .filter((s) => s !== '')
}

async function doSubmit() {
  if (!form.value.persona_name.trim()) { alert('请输入角色名称'); return }
  if (!form.value.title.trim()) { alert('请输入项目标题'); return }
  if (descLen.value < 200) { alert('角色描述不能少于200字（当前 ' + descLen.value + ' 字）'); return }

  const tags = splitList(form.value.tags)
  if (tags.length === 0) { alert('请至少填写一个标签'); return }
  if (tags.length > 10) { alert('标签最多填写10个'); return }
  if (!form.value.style) { alert('请选择角色风格'); return }
  if (form.value.deliverables.length === 0) { alert('请至少选择一项交付内容'); return }
  if (form.value.reference_url && !/^https?:\/\//i.test(form.value.reference_url)) {
    alert('参考链接需以 http(s):// 开头')
    return
  }
  if (!form.value.target_amount || form.value.target_amount <= 0) { alert('请输入有效的目标金额'); return }
  if (!form.value.deadline) { alert('请选择截止时间'); return }

  submitting.value = true
  try {
    const res: any = await initiateCrowdfunding({
      title: form.value.title.trim(),
      persona_name: form.value.persona_name.trim(),
      description: form.value.description.trim(),
      tags: tags.join(','),
      style: form.value.style,
      gender: form.value.gender,
      age_range: form.value.age_range,
      language: form.value.language,
      personality: splitList(form.value.personality).join(','),
      voice_style: form.value.voice_style,
      deliverables: form.value.deliverables,
      is_adult: form.value.is_adult,
      highlights: form.value.highlights.trim(),
      reference_url: form.value.reference_url.trim(),
      cover_url: form.value.cover_url,
      target_amount: Number(form.value.target_amount),
      deadline: form.value.deadline,
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
  height: 100vh;
  background: #0a0a14;
  color: #fff;
  padding-bottom: 40px;
  position: relative;
  overflow-x: hidden;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
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
.hint-ok { color: #00d4aa; }
.hint-warn { color: #f59e0b; }
.section-label {
  font-size: 12px;
  font-weight: 700;
  color: rgba(255,255,255,0.35);
  letter-spacing: 0.5px;
  margin: 4px 0 14px;
  padding-top: 14px;
  border-top: 1px solid rgba(255,255,255,0.07);
}
.section-label:first-child { border-top: none; padding-top: 0; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0 12px; }
select.form-input { appearance: none; -webkit-appearance: none; }
.chips { display: flex; flex-wrap: wrap; gap: 8px; }
.chip {
  padding: 9px 14px;
  border-radius: 999px;
  border: 1px solid rgba(255,255,255,0.12);
  background: rgba(255,255,255,0.04);
  color: rgba(255,255,255,0.7);
  font-size: 13px;
  font-family: inherit;
  cursor: pointer;
  transition: border-color .15s, background .15s, color .15s;
}
.chip.on {
  border-color: rgba(99,102,241,0.65);
  background: rgba(99,102,241,0.18);
  color: #a5b4fc;
}
.chip--wide { flex: 1; text-align: center; }

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
