<template>
  <div class="CertPage">
    <div class="bg-glow bg-glow--top"></div>
    <div class="bg-glow bg-glow--bottom"></div>

    <!-- 顶部返回栏 -->
    <div class="top-bar" v-if="!loading">
      <button class="btn-back" @click="goBack">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
      </button>
      <span class="top-title">商家认证</span>
    </div>

    <!-- 加载中 -->
    <div class="loading-zone" v-if="loading">
      <div class="spinner"></div>
      <p>加载中...</p>
    </div>

    <!-- 已通过 -->
    <template v-else-if="certStatus === 1">
      <div class="result-card result-card--success" v-anim>
        <div class="result-icon">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#00d4aa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <h2 class="result-title">商家认证已通过</h2>
        <p class="result-desc">店铺名称：{{ shopName }}</p>
        <p class="result-sub">认证邮箱：{{ certEmail }}</p>
        <button class="btn-detail" @click="viewDetail">查看认证详情</button>
      </div>
    </template>

    <!-- 审核中 -->
    <template v-else-if="certStatus === 0">
      <div class="result-card result-card--pending" v-anim>
        <div class="result-icon">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <h2 class="result-title">认证审核中</h2>
        <p class="result-desc">您的认证申请正在审核，请耐心等待</p>
        <p class="result-sub">提交时间：{{ createdAt }}</p>
      </div>
    </template>

    <!-- 已拒绝 -->
    <template v-else-if="certStatus === 2">
      <div class="result-card result-card--rejected" v-anim>
        <div class="result-icon">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#ff2d55" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        </div>
        <h2 class="result-title">认证未通过</h2>
        <p class="result-desc reject-reason" v-if="rejectReason">拒绝原因：{{ rejectReason }}</p>
        <p class="result-sub">您可以重新提交认证申请</p>
        <button class="btn-retry" @click="showForm">重新提交</button>
      </div>
    </template>

    <!-- 未认证 / 重新提交表单 -->
    <template v-if="showSubmitForm">
      <form class="cert-form" @submit.prevent="handleSubmit" v-anim>
        <h2 class="form-title">{{ certStatus === 2 ? '重新提交认证' : '商家认证' }}</h2>
        <p class="form-desc">请填写真实信息并上传有效证件，提交后将进入审核流程</p>

        <!-- 身份信息 -->
        <div class="section-title">身份信息</div>

        <div class="field">
          <label>真实姓名 <span class="required">*</span></label>
          <input v-model="form.real_name" placeholder="请输入真实姓名" maxlength="50" />
        </div>

        <div class="field">
          <label>身份证号 <span class="required">*</span></label>
          <input v-model="form.id_card_no" placeholder="请输入18位身份证号" maxlength="18" />
        </div>

        <div class="field">
          <label>手机号 <span class="required">*</span></label>
          <input v-model="form.phone" type="tel" placeholder="请输入手机号" maxlength="11" />
        </div>

        <div class="field">
          <label>认证邮箱 <span class="required">*</span></label>
          <input v-model="form.email" type="email" placeholder="请输入认证邮箱" maxlength="255" />
        </div>

        <!-- 店铺信息 -->
        <div class="section-title">店铺信息</div>

        <div class="field">
          <label>店铺名称 <span class="required">*</span></label>
          <input v-model="form.shop_name" placeholder="请输入店铺名称" maxlength="100" />
        </div>

        <div class="field">
          <label>经营类目 <span class="required">*</span></label>
          <div class="select-wrap">
            <select v-model="form.shop_type">
              <option value="" disabled>请选择经营类目</option>
              <option v-for="t in shopTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
            <svg class="select-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
          </div>
        </div>

        <div class="field">
          <label>店铺简介</label>
          <textarea v-model="form.shop_description" placeholder="请输入店铺简介（选填）" maxlength="500" rows="3"></textarea>
        </div>

        <!-- 证件上传 -->
        <div class="section-title">证件上传</div>

        <div class="field">
          <label>身份证正面 <span class="required">*</span></label>
          <div class="upload-zone" @click="triggerUpload('id_card_front')">
            <img v-if="form.id_card_front" :src="form.id_card_front" class="upload-preview" />
            <div v-else class="upload-placeholder">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
              <span>点击上传身份证正面</span>
            </div>
          </div>
          <input ref="inputFront" type="file" accept="image/*" style="display:none" @change="onFileChange('id_card_front', $event)" />
        </div>

        <div class="field">
          <label>身份证背面 <span class="required">*</span></label>
          <div class="upload-zone" @click="triggerUpload('id_card_back')">
            <img v-if="form.id_card_back" :src="form.id_card_back" class="upload-preview" />
            <div v-else class="upload-placeholder">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
              <span>点击上传身份证背面</span>
            </div>
          </div>
          <input ref="inputBack" type="file" accept="image/*" style="display:none" @change="onFileChange('id_card_back', $event)" />
        </div>

        <div class="field">
          <label>营业执照 <span class="required">*</span></label>
          <div class="upload-zone" @click="triggerUpload('business_license')">
            <img v-if="form.business_license" :src="form.business_license" class="upload-preview" />
            <div v-else class="upload-placeholder">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
              <span>点击上传营业执照</span>
            </div>
          </div>
          <input ref="inputBizLic" type="file" accept="image/*" style="display:none" @change="onFileChange('business_license', $event)" />
        </div>

        <button class="btn-submit" type="submit" :disabled="submitting">
          {{ submitting ? '提交中...' : '提交认证' }}
        </button>
      </form>
    </template>

    <!-- 认证详情弹窗 -->
    <div class="mask-dialog" v-if="showDetail" @click.self="showDetail = false">
      <div class="detail-card">
        <h3>认证详情</h3>
        <div class="detail-item" v-if="detailData">
          <span>真实姓名</span><span>{{ detailData.real_name }}</span>
        </div>
        <div class="detail-item" v-if="detailData">
          <span>身份证号</span><span>{{ detailData.id_card_no }}</span>
        </div>
        <div class="detail-item" v-if="detailData">
          <span>手机号</span><span>{{ detailData.phone }}</span>
        </div>
        <div class="detail-item" v-if="detailData">
          <span>认证邮箱</span><span>{{ detailData.email }}</span>
        </div>
        <div class="detail-item" v-if="detailData">
          <span>店铺名称</span><span>{{ detailData.shop_name }}</span>
        </div>
        <div class="detail-item" v-if="detailData">
          <span>经营类目</span><span>{{ detailData.shop_type }}</span>
        </div>
        <div class="detail-item" v-if="detailData && detailData.shop_description">
          <span>店铺简介</span><span>{{ detailData.shop_description }}</span>
        </div>
        <div class="detail-item" v-if="detailData">
          <span>身份证正面</span>
          <img class="detail-img" :src="detailData.id_card_front" />
        </div>
        <div class="detail-item" v-if="detailData">
          <span>身份证背面</span>
          <img class="detail-img" :src="detailData.id_card_back" />
        </div>
        <div class="detail-item" v-if="detailData">
          <span>营业执照</span>
          <img class="detail-img" :src="detailData.business_license" />
        </div>
        <div class="detail-item" v-if="detailData">
          <span>状态</span><span class="status-tag" :class="'status-' + detailData.status">{{ detailData.status_text }}</span>
        </div>
        <div class="detail-item" v-if="detailData && detailData.reject_reason">
          <span>拒绝原因</span><span class="reject-reason-text">{{ detailData.reject_reason }}</span>
        </div>
        <button class="btn-close" @click="showDetail = false">关闭</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  getCertificationStatus,
  getCertificationDetail,
  getShopTypes,
  submitCertification,
  uploadCertificationImage,
  type CertificationDetail,
  type ShopTypeOption
} from '@/api/merchant'

defineOptions({ name: 'MerchantCertification' })

const router = useRouter()

function goBack() { router.back() }

const loading = ref(true)
const submitting = ref(false)
const certStatus = ref(-1)
const certEmail = ref('')
const shopName = ref('')
const rejectReason = ref('')
const createdAt = ref('')
const showSubmitForm = ref(false)
const showDetail = ref(false)
const detailData = ref<CertificationDetail | null>(null)
const shopTypes = ref<ShopTypeOption[]>([])

const form = ref({
  real_name: '',
  id_card_no: '',
  phone: '',
  email: '',
  shop_name: '',
  shop_type: '',
  shop_description: '',
  id_card_front: '',
  id_card_back: '',
  business_license: ''
})

const inputFront = ref<HTMLInputElement | null>(null)
const inputBack = ref<HTMLInputElement | null>(null)
const inputBizLic = ref<HTMLInputElement | null>(null)

onMounted(async () => {
  await Promise.all([loadStatus(), loadShopTypes()])
})

async function loadShopTypes() {
  try {
    const res = await getShopTypes()
    if (res.success && res.data) {
      shopTypes.value = res.data
    }
  } catch { /* ignore */ }
}

async function loadStatus() {
  loading.value = true
  try {
    const res = await getCertificationStatus()
    if (res.success && res.data) {
      certStatus.value = res.data.cert_status
      certEmail.value = res.data.email || ''
      shopName.value = res.data.shop_name || ''
      rejectReason.value = res.data.reject_reason || ''
      createdAt.value = res.data.created_at || ''
      if (certStatus.value === -1 || certStatus.value === 2) {
        showSubmitForm.value = true
      }
    }
  } catch {
    // 静默失败
  } finally {
    loading.value = false
  }
}

function showForm() {
  showSubmitForm.value = true
}

async function viewDetail() {
  try {
    const res = await getCertificationDetail()
    if (res.success && res.data) {
      detailData.value = res.data
      showDetail.value = true
    }
  } catch { /* ignore */ }
}

function triggerUpload(field: string) {
  if (field === 'id_card_front') inputFront.value?.click()
  if (field === 'id_card_back') inputBack.value?.click()
  if (field === 'business_license') inputBizLic.value?.click()
}

async function onFileChange(field: string, e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  try {
    const res = await uploadCertificationImage(file)
    if (res.success && res.data) {
      form.value[field as keyof typeof form.value] = res.data.url
    }
  } catch { /* ignore */ }
}

async function handleSubmit() {
  if (submitting.value) return

  if (!form.value.real_name.trim()) return alert('请输入真实姓名')
  if (!/^\d{17}[\dXx]$/.test(form.value.id_card_no.trim())) return alert('请输入正确的18位身份证号')
  if (!/^1[3-9]\d{9}$/.test(form.value.phone.trim())) return alert('请输入正确的手机号')
  if (!form.value.email.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email)) return alert('请输入正确的邮箱地址')
  if (!form.value.shop_name.trim()) return alert('请输入店铺名称')
  if (!form.value.shop_type) return alert('请选择经营类目')
  if (!form.value.id_card_front) return alert('请上传身份证正面')
  if (!form.value.id_card_back) return alert('请上传身份证背面')
  if (!form.value.business_license) return alert('请上传营业执照')

  submitting.value = true
  try {
    const res = await submitCertification({
      real_name: form.value.real_name.trim(),
      id_card_no: form.value.id_card_no.trim(),
      phone: form.value.phone.trim(),
      email: form.value.email.trim(),
      shop_name: form.value.shop_name.trim(),
      shop_type: form.value.shop_type,
      shop_description: form.value.shop_description.trim(),
      id_card_front: form.value.id_card_front,
      id_card_back: form.value.id_card_back,
      business_license: form.value.business_license
    })
    if (res.success) {
      alert('提交成功')
      showSubmitForm.value = false
      await loadStatus()
    } else {
      alert((res as any).data?.message || '提交失败')
    }
  } catch (e: any) {
    alert(e?.message || '提交失败')
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped lang="less">
@import '@/assets/less/index';

@accent: #ff2d55;
@accent-glow: rgba(255, 45, 85, 0.35);
@cyan: #00d4aa;
@cyan-glow: rgba(0, 212, 170, 0.3);
@amber: #f59e0b;
@surface: rgba(255, 255, 255, 0.04);
@border: rgba(255, 255, 255, 0.06);

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

@keyframes spin {
  to { transform: rotate(360deg); }
}

.CertPage {
  position: relative;
  min-height: 100vh;
  background: linear-gradient(180deg, #0c0e18 0%, #11131f 40%, #0d0f1a 100%);
  color: #fff;
  overflow-x: hidden;
  overflow-y: auto;
  padding: 0 0 60rem;
}

.top-bar {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
  padding: 14rem var(--page-padding);
  gap: 10rem;
}

.btn-back {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36rem;
  height: 36rem;
  border-radius: 50%;
  border: none;
  background: rgba(255,255,255,0.06);
  color: rgba(255,255,255,0.6);
  cursor: pointer;
  flex-shrink: 0;
  transition: background 0.2s;

  &:hover { background: rgba(255,255,255,0.12); }
  &:active { background: rgba(255,255,255,0.18); }
}

.top-title {
  font-size: 17rem;
  font-weight: 600;
  letter-spacing: 0.5rem;
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

.loading-zone {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 60vh;
  color: rgba(255,255,255,0.4);
  font-size: 14rem;

  .spinner {
    width: 32rem;
    height: 32rem;
    border: 3rem solid rgba(255,255,255,0.1);
    border-top-color: @accent;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin-bottom: 16rem;
  }
}

// ======= Result Cards =======
.result-card {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 60rem 30rem 40rem;
  text-align: center;
  animation: fade-slide-up 0.5s ease-out forwards;

  .result-icon { margin-bottom: 20rem; }

  .result-title {
    font-size: 20rem;
    font-weight: 700;
    margin: 0 0 10rem;
    letter-spacing: 0.5rem;
  }

  .result-desc {
    font-size: 14rem;
    color: rgba(255,255,255,0.5);
    margin: 0 0 8rem;
    line-height: 1.6;

    &.reject-reason { color: @accent; opacity: 0.85; }
  }

  .result-sub {
    font-size: 12rem;
    color: rgba(255,255,255,0.3);
    margin: 0 0 24rem;
  }
}

.result-card--success .result-title { color: @cyan; }
.result-card--pending .result-title { color: @amber; }
.result-card--rejected .result-title { color: @accent; }

.btn-detail, .btn-retry {
  display: inline-flex;
  align-items: center;
  padding: 12rem 32rem;
  border: none;
  border-radius: 30rem;
  font-size: 14rem;
  font-weight: 600;
  color: #fff;
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s;

  &:active { transform: scale(0.96); }
}

.btn-detail {
  background: linear-gradient(135deg, rgba(0,212,170,0.3), rgba(0,212,170,0.1));
  border: 1px solid rgba(0,212,170,0.25);
  color: @cyan;
}

.btn-retry {
  background: linear-gradient(135deg, @accent, #d42148);
  box-shadow: 0 4px 20px rgba(255, 45, 85, 0.3);
}

// ======= Form =======
.cert-form {
  position: relative;
  z-index: 1;
  padding: 0 var(--page-padding) 40rem;
  animation: fade-slide-up 0.5s ease-out forwards;

  .form-title {
    font-size: 20rem;
    font-weight: 700;
    margin: 0 0 8rem;
    text-align: center;
  }

  .form-desc {
    font-size: 13rem;
    color: rgba(255,255,255,0.4);
    text-align: center;
    margin: 0 0 30rem;
    line-height: 1.6;
  }
}

.section-title {
  font-size: 14rem;
  font-weight: 700;
  color: rgba(255,255,255,0.8);
  margin: 24rem 0 16rem;
  padding-bottom: 8rem;
  border-bottom: 1px solid rgba(255,255,255,0.06);
  letter-spacing: 0.3rem;
}

.field {
  margin-bottom: 20rem;

  label {
    display: block;
    font-size: 13rem;
    font-weight: 600;
    color: rgba(255,255,255,0.6);
    margin-bottom: 8rem;
    letter-spacing: 0.3rem;
  }

  .required {
    color: @accent;
  }

  input[type="email"],
  input[type="tel"],
  input:not([type]) {
    width: 100%;
    padding: 13rem 16rem;
    border: 1px solid @border;
    border-radius: 12rem;
    background: @surface;
    color: #fff;
    font-size: 15rem;
    outline: none;
    transition: border-color 0.2s;
    box-sizing: border-box;

    &:focus { border-color: rgba(255, 45, 85, 0.5); }
    &::placeholder { color: rgba(255,255,255,0.2); }
  }

  textarea {
    width: 100%;
    padding: 13rem 16rem;
    border: 1px solid @border;
    border-radius: 12rem;
    background: @surface;
    color: #fff;
    font-size: 15rem;
    outline: none;
    transition: border-color 0.2s;
    box-sizing: border-box;
    resize: vertical;
    min-height: 80rem;
    font-family: inherit;

    &:focus { border-color: rgba(255, 45, 85, 0.5); }
    &::placeholder { color: rgba(255,255,255,0.2); }
  }
}

.select-wrap {
  position: relative;

  select {
    width: 100%;
    padding: 13rem 36rem 13rem 16rem;
    border: 1px solid @border;
    border-radius: 12rem;
    background: @surface;
    color: #fff;
    font-size: 15rem;
    outline: none;
    appearance: none;
    cursor: pointer;
    transition: border-color 0.2s;
    box-sizing: border-box;

    &:focus { border-color: rgba(255, 45, 85, 0.5); }

    option {
      background: #1a1d2e;
      color: #fff;
    }
  }

  .select-arrow {
    position: absolute;
    right: 12rem;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    color: rgba(255,255,255,0.3);
  }
}

.upload-zone {
  width: 100%;
  min-height: 100rem;
  border: 2px dashed rgba(255,255,255,0.12);
  border-radius: 12rem;
  background: @surface;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  overflow: hidden;
  transition: border-color 0.2s;

  &:hover { border-color: rgba(255,255,255,0.25); }

  .upload-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8rem;
    color: rgba(255,255,255,0.3);
    font-size: 13rem;
    svg { opacity: 0.5; }
  }

  .upload-preview {
    width: 100%;
    max-height: 200rem;
    object-fit: contain;
    background: rgba(0,0,0,0.3);
  }
}

.btn-submit {
  width: 100%;
  padding: 15rem;
  border: none;
  border-radius: 30rem;
  font-size: 16rem;
  font-weight: 700;
  color: #fff;
  cursor: pointer;
  background: linear-gradient(135deg, @accent, #d42148);
  box-shadow: 0 4px 20px rgba(255, 45, 85, 0.3);
  transition: transform 0.2s, box-shadow 0.2s, opacity 0.2s;
  margin-top: 16rem;

  &:active:not(:disabled) { transform: scale(0.98); }
  &:disabled { opacity: 0.5; cursor: not-allowed; }
}

// ======= Detail Dialog =======
.mask-dialog {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
  padding: 30rem;
}

.detail-card {
  background: #1a1d2e;
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 20rem;
  padding: 30rem 24rem 24rem;
  width: 100%;
  max-width: 360rem;
  max-height: 80vh;
  overflow-y: auto;

  h3 {
    margin: 0 0 20rem;
    font-size: 18rem;
    font-weight: 700;
    text-align: center;
  }
}

.detail-item {
  display: flex;
  flex-direction: column;
  padding: 12rem 0;
  border-bottom: 1px solid rgba(255,255,255,0.06);
  font-size: 14rem;

  span:first-child {
    color: rgba(255,255,255,0.45);
    margin-bottom: 8rem;
  }

  span:last-child {
    color: rgba(255,255,255,0.85);
    word-break: break-all;
  }

  .reject-reason-text {
    color: @accent !important;
  }

  .status-tag {
    display: inline-block;
    padding: 4rem 12rem;
    border-radius: 12rem;
    font-size: 12rem;
    font-weight: 600;
    width: fit-content;
  }

  .status-0 { background: rgba(245,158,11,0.15); color: @amber; }
  .status-1 { background: rgba(0,212,170,0.15); color: @cyan; }
  .status-2 { background: rgba(255,45,85,0.15); color: @accent; }

  .detail-img {
    width: 100%;
    max-height: 160rem;
    object-fit: contain;
    border-radius: 8rem;
    background: rgba(0,0,0,0.3);
    margin-top: 4rem;
  }
}

.btn-close {
  width: 100%;
  padding: 12rem;
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 14rem;
  background: rgba(255,255,255,0.05);
  color: #fff;
  font-size: 15rem;
  cursor: pointer;
  margin-top: 20rem;

  &:active { background: rgba(255,255,255,0.1); }
}
</style>
