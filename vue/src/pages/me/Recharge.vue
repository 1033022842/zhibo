<template>
  <div class="RechargePage">
    <div class="bg-glow bg-glow--top"></div>
    <div class="bg-glow bg-glow--bottom"></div>

    <div class="top-bar">
      <button class="btn-back" @click="$router.back()">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
      </button>
      <span class="top-title">钻石充值</span>
    </div>

    <!-- Loading -->
    <div class="loading-zone" v-if="loading">
      <div class="spinner"></div><p>加载中...</p>
    </div>

    <!-- 选择渠道 -->
    <template v-else-if="step === 'channel'">
      <div class="section" v-anim>
        <h3 class="section-title">选择充值渠道</h3>
        <div class="channel-list">
          <div
            v-for="ch in channels"
            :key="ch.id"
            class="channel-card"
            :class="{ active: selectedChannel?.id === ch.id }"
            @click="selectChannel(ch)"
          >
            <div class="channel-name">{{ ch.name }}</div>
            <div class="channel-rate">1 USDT = {{ ch.diamond_rate }} 钻</div>
            <div class="channel-min">最低 {{ ch.min_amount }} USDT</div>
          </div>
        </div>
      </div>
    </template>

    <!-- 填写金额 + 查看地址 -->
    <template v-else-if="step === 'pay'">
      <div class="section" v-anim>
        <h3 class="section-title">{{ selectedChannel?.name }}</h3>

        <!-- 二维码 -->
        <div class="qr-zone" v-if="selectedChannel?.qr_code_url">
          <img :src="selectedChannel.qr_code_url" class="qr-img" alt="收款二维码" />
        </div>

        <!-- 收款地址 -->
        <div class="address-zone">
          <div class="address-label">收款地址</div>
          <div class="address-box">
            <span class="address-text">{{ selectedChannel?.address }}</span>
            <button class="btn-copy" @click="copyAddress">复制</button>
          </div>
        </div>

        <!-- 金额输入 -->
        <div class="amount-section">
          <div class="amount-label">充值金额 (USDT)</div>
          <div class="amount-input-row">
            <input v-model.number="amount" type="number" class="amount-input" placeholder="最低 {{ selectedChannel?.min_amount }}" :min="selectedChannel?.min_amount" />
            <span class="amount-unit">USDT</span>
          </div>
          <div class="amount-estimate">预计到账 <strong>{{ estimatedDiamonds }}</strong> 钻</div>
        </div>

        <!-- 上传凭证（仅人工审核渠道） -->
        <div class="proof-section" v-if="!isAutoChannel">
          <div class="proof-label">上传支付截图（可选）</div>
          <div class="proof-upload" @click="triggerUpload">
            <img v-if="proofUrl" :src="proofUrl" class="proof-preview" />
            <div v-else class="proof-placeholder">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
              <span>点击上传支付凭证</span>
            </div>
          </div>
          <input ref="fileInput" type="file" accept="image/*" style="display:none" @change="onFileChange" />
        </div>

        <button class="btn-switch" @click="step = 'channel'">切换渠道</button>
        <button class="btn-submit" @click="doSubmit" :disabled="submitting || amount <= 0">
          {{ submitting ? '提交中...' : (isAutoChannel ? '生成付款订单' : '我已支付，提交审核') }}
        </button>
        <p class="submit-hint" v-if="!isAutoChannel">提交后需等待管理员审核，审核通过后钻石自动到账</p>
        <p class="submit-hint" v-else>生成订单后按精确金额转账，链上确认后自动到账</p>
      </div>
    </template>

    <!-- 等待链上确认（自动模式） -->
    <template v-else-if="step === 'waiting'">
      <div class="section waiting-section" v-anim>
        <div class="wait-head">
          <div class="wait-spinner"></div>
          <h3 class="section-title-wait">等待链上确认</h3>
          <p class="wait-tip">请向以下地址转入 <strong>精确金额</strong>，确认后自动到账（约1-3分钟）</p>
        </div>

        <div class="qr-zone">
          <img v-if="qrDataUrl" :src="qrDataUrl" class="qr-img" alt="收款二维码" />
          <div v-else class="qr-loading"></div>
        </div>

        <div class="pay-amount-zone">
          <div class="pay-amount-label">应付金额（请务必精确转账，含小数尾号）</div>
          <div class="pay-amount-value" @click="copyText(lastOrder?.pay_amount, '应付金额已复制，请精确转账')">
            {{ lastOrder?.pay_amount }} USDT
            <span class="copy-mini">复制</span>
          </div>
        </div>

        <div class="address-zone">
          <div class="address-label">收款地址 (TRC20)</div>
          <div class="address-box">
            <span class="address-text">{{ selectedChannel?.address }}</span>
            <button class="btn-copy" @click="copyAddress">复制</button>
          </div>
        </div>

        <div class="wait-meta">
          <span>订单号 {{ lastOrder?.order_no }}</span>
          <span>预计到账 <strong>{{ lastOrder?.diamond_amount }}</strong> 钻</span>
          <span>剩余时间 <strong :class="{ warn: countdownSec <= 300 }">{{ countdownText }}</strong></span>
        </div>

        <button class="btn-done" @click="goOrders">查看充值记录</button>
        <button class="btn-back-channel" @click="cancelWaiting">返回重新下单</button>
      </div>
    </template>

    <!-- 到账成功（自动确认） -->
    <template v-else-if="step === 'success'">
      <div class="result-card result-card--success" v-anim>
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#00d4aa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        <h2>充值成功</h2>
        <p>{{ lastOrder?.pay_amount }} USDT → <strong class="hl">{{ lastOrder?.diamond_amount }} 钻</strong></p>
        <p class="result-sub">链上已确认，钻石已到账</p>
        <button class="btn-done" @click="goOrders">查看充值记录</button>
        <button class="btn-back-channel" @click="step = 'channel'">继续充值</button>
      </div>
    </template>

    <!-- 提交成功 -->
    <template v-else-if="step === 'done'">
      <div class="result-card result-card--success" v-anim>
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#00d4aa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        <h2>充值申请已提交</h2>
        <p>订单号: {{ lastOrder?.order_no }}</p>
        <p class="result-sub">请等待管理员审核，审核通过后钻石自动到账</p>
        <button class="btn-done" @click="goOrders">查看充值记录</button>
      </div>
    </template>

    <!-- 充值记录 -->
    <template v-else-if="step === 'orders'">
      <div class="section" v-anim>
        <h3 class="section-title">充值记录</h3>
        <div class="empty-zone" v-if="orders.length === 0"><p>暂无充值记录</p></div>
        <div class="order-list" v-else>
          <div class="order-row" v-for="o in orders" :key="o.id">
            <div class="order-info">
              <span class="order-no">{{ o.order_no }}</span>
              <span class="order-time">{{ o.created_at?.slice(0, 16) }}</span>
            </div>
            <div class="order-amount">
              <span>{{ o.pay_amount }} USDT → {{ o.diamond_amount }} 钻</span>
            </div>
            <div class="order-status">
              <span class="status-tag" :class="statusClass(o.status)">{{ o.status_text }}</span>
            </div>
          </div>
        </div>
        <button class="btn-back-channel" @click="step = 'channel'">返回充值</button>
      </div>
    </template>
  
    <!-- 复制成功 toast -->
    <transition name="toast-fade">
      <div v-if="copyToast.show" class="copy-toast">{{ copyToast.text }}</div>
    </transition>
</div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import QRCode from 'qrcode'
import { useRouter, useRoute } from 'vue-router'
import {
  getRechargeChannels, submitRecharge, getRechargeOrders, uploadProofImage, getRechargeStatus,
  type RechargeChannel, type RechargeOrder
} from '@/api/recharge'
import { setTokens, getAccessToken } from '@/utils/auth'

const router = useRouter()
const route = useRoute()

const step = ref('channel')
const loading = ref(true)
const submitting = ref(false)
const channels = ref<RechargeChannel[]>([])
const selectedChannel = ref<RechargeChannel | null>(null)
const amount = ref(0)
const proofUrl = ref('')
const fileInput = ref<HTMLInputElement>()
const orders = ref<RechargeOrder[]>([])
const lastOrder = ref<any>(null)
const qrDataUrl = ref('')
const pollTimer = ref<number | null>(null)
const countdownTimer = ref<number | null>(null)
const countdownSec = ref(0)

const isAutoChannel = computed(() => (selectedChannel.value as any)?.confirm_mode !== 'manual')

const countdownText = computed(() => {
  const m = Math.floor(countdownSec.value / 60)
  const s = countdownSec.value % 60
  return `${m}:${String(s).padStart(2, '0')}`
})

const estimatedDiamonds = computed(() => {
  if (!selectedChannel.value || amount.value <= 0) return 0
  return Math.floor(amount.value * selectedChannel.value.diamond_rate)
})

function statusText(s: number) { return ['待审核', '已通过', '已拒绝', '已关闭'][s] || '' }
function statusClass(s: number) { return ['pending', 'passed', 'rejected', 'closed'][s] || '' }

async function selectChannel(ch: RechargeChannel) {
  selectedChannel.value = ch
  step.value = 'pay'
}

function copyAddress() {
  if (selectedChannel.value?.address) {
    copyText(selectedChannel.value.address, '收款地址已复制')
  }
}

function triggerUpload() { fileInput.value?.click() }

async function onFileChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  try {
    const res: any = await uploadProofImage(file)
    const resp = res?.data
    proofUrl.value = (resp?.data || resp)?.url || ''
  } catch { alert('上传失败') }
}

async function doSubmit() {
  if (!selectedChannel.value || amount.value <= 0) return
  submitting.value = true
  try {
    const res: any = await submitRecharge(selectedChannel.value.id, amount.value, proofUrl.value)
    const resp = res?.data
    lastOrder.value = (resp?.data || resp)
    if (isAutoChannel.value) {
      startWaiting()
    } else {
      step.value = 'done'
    }
  } catch (e: any) { alert(e?.message || e?.msg || '提交失败') }
  submitting.value = false
}

function startWaiting() {
  step.value = 'waiting'
  // 生成收款地址二维码
  const addr = selectedChannel.value?.address || ''
  if (addr) {
    QRCode.toDataURL(addr, { width: 360, margin: 2, color: { dark: '#111111', light: '#ffffff' } })
      .then((url: string) => { qrDataUrl.value = url })
      .catch(() => { qrDataUrl.value = '' })
  }
  // 倒计时（expire_at）
  const expire = new Date(String(lastOrder.value?.expire_at || '').replace(/-/g, '/')).getTime()
  countdownSec.value = Math.max(0, Math.floor((expire - Date.now()) / 1000))
  if (countdownTimer.value) clearInterval(countdownTimer.value)
  countdownTimer.value = window.setInterval(() => {
    countdownSec.value = Math.max(0, countdownSec.value - 1)
    if (countdownSec.value === 0) stopPoll()
  }, 1000)
  // 轮询订单状态
  if (pollTimer.value) clearInterval(pollTimer.value)
  pollTimer.value = window.setInterval(pollStatus, 5000)
}

async function pollStatus() {
  const orderNo = lastOrder.value?.order_no
  if (!orderNo) return
  try {
    const res: any = await getRechargeStatus(orderNo)
    const resp = res?.data?.data || res?.data
    const st = Number(resp?.status ?? -1)
    if (st === 1) {
      lastOrder.value = { ...lastOrder.value, ...resp }
      stopPoll()
      step.value = 'success'
    } else if (st === 3) {
      stopPoll()
      showToast('订单已超时未支付，请重新下单')
      step.value = 'channel'
    }
  } catch { /* 网络抖动继续轮询 */ }
}

function stopPoll() {
  if (pollTimer.value) { clearInterval(pollTimer.value); pollTimer.value = null }
  if (countdownTimer.value) { clearInterval(countdownTimer.value); countdownTimer.value = null }
}

function cancelWaiting() {
  stopPoll()
  step.value = 'channel'
}

const copyToast = ref<{ show: boolean; text: string }>({ show: false, text: '' })
let copyToastTimer: number | null = null

function showToast(text: string) {
  copyToast.value = { show: true, text }
  if (copyToastTimer) window.clearTimeout(copyToastTimer)
  copyToastTimer = window.setTimeout(() => (copyToast.value.show = false), 1600)
}

async function copyText(t?: string | number, tip = '已复制') {
  if (t === undefined || t === null || t === '') return
  const text = String(t)
  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text)
    } else {
      // http 环境降级：临时 textarea + execCommand
      const ta = document.createElement('textarea')
      ta.value = text
      ta.style.position = 'fixed'
      ta.style.opacity = '0'
      document.body.appendChild(ta)
      ta.select()
      document.execCommand('copy')
      document.body.removeChild(ta)
    }
    showToast(tip)
  } catch {
    showToast('复制失败，请长按手动复制')
  }
}

async function goOrders() {
  loading.value = true
  step.value = 'orders'
  try {
    const res: any = await getRechargeOrders()
    const resp = res?.data
    orders.value = (resp?.data || resp)?.list || []
  } catch {}
  loading.value = false
}

onUnmounted(() => { stopPoll() })

onMounted(async () => {
  // 处理从 AI 女友端跳转过来的 token（跨域 localhost:8003 → localhost:3000）
  const hashToken = route.hash?.replace('#token=', '')
  if (hashToken && !getAccessToken()) {
    setTokens(hashToken, '')
    // 清除 URL 中的 token，避免泄露
    router.replace({ hash: '' })
  }
  try {
    const res: any = await getRechargeChannels()
    const resp = res?.data
    channels.value = (resp?.data || resp) || []
  } catch {}
  loading.value = false
})
</script>

<style scoped>
.RechargePage { height:100vh; background:#0a0a14; color:#fff; padding-bottom:40px; position:relative; overflow-x:hidden; overflow-y:auto; -webkit-overflow-scrolling:touch }
.bg-glow{position:fixed;border-radius:50%;filter:blur(120px);opacity:.1;pointer-events:none;z-index:0}
.bg-glow--top{top:-120px;left:-80px;width:320px;height:320px;background:radial-gradient(circle,#f59e0b,transparent)}
.bg-glow--bottom{bottom:-120px;right:-80px;width:320px;height:320px;background:radial-gradient(circle,#d97706,transparent)}
.top-bar{position:relative;z-index:1;display:flex;align-items:center;gap:8px;padding:16px}
.btn-back{background:none;border:none;color:#fff;cursor:pointer;padding:4px;display:flex}
.top-title{font-size:18px;font-weight:700}
.loading-zone,.empty-zone{position:relative;z-index:1;display:flex;flex-direction:column;align-items:center;padding:40px 16px;color:rgba(255,255,255,.45);gap:8px}
.spinner{width:28px;height:28px;border:3px solid rgba(255,255,255,.1);border-top-color:#f59e0b;border-radius:50%;animation:spin .7s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}

.section{position:relative;z-index:1;margin:0 16px 20px}
.section-title{font-size:14px;font-weight:600;color:rgba(255,255,255,.55);margin-bottom:12px}

.channel-list{display:flex;flex-direction:column;gap:10px}
.channel-card{padding:16px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:12px;cursor:pointer;transition:all .15s}
.channel-card.active{background:rgba(245,158,11,.1);border-color:rgba(245,158,11,.4)}
.channel-name{font-size:15px;font-weight:600;margin-bottom:4px}
.channel-rate{font-size:13px;color:#f59e0b;margin-bottom:2px}
.channel-min{font-size:12px;color:rgba(255,255,255,.35)}

.qr-zone{text-align:center;margin-bottom:16px}
.qr-img{width:180px;height:180px;border-radius:12px;object-fit:contain;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1)}

.address-zone{margin-bottom:20px}
.address-label{font-size:13px;color:rgba(255,255,255,.5);margin-bottom:6px}
.address-box{display:flex;align-items:center;gap:8px;padding:12px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:10px}
.address-text{flex:1;font-size:13px;font-family:monospace;word-break:break-all;color:rgba(255,255,255,.8)}
.btn-copy{padding:6px 16px;background:rgba(245,158,11,.15);border:1px solid rgba(245,158,11,.3);border-radius:8px;color:#f59e0b;font-size:13px;cursor:pointer;white-space:nowrap}

.amount-section{margin-bottom:20px}
.amount-label{font-size:13px;color:rgba(255,255,255,.5);margin-bottom:6px}
.amount-input-row{display:flex;align-items:center;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:10px;padding:4px 14px}
.amount-input{flex:1;background:none;border:none;color:#fff;font-size:22px;font-weight:700;padding:12px 0;outline:none}
.amount-input::placeholder{color:rgba(255,255,255,.2)}
.amount-unit{font-size:16px;color:rgba(255,255,255,.4);margin-left:6px}
.amount-estimate{font-size:13px;color:rgba(255,255,255,.4);margin-top:6px}
.amount-estimate strong{color:#f59e0b}

.proof-section{margin-bottom:20px}
.proof-label{font-size:13px;color:rgba(255,255,255,.5);margin-bottom:6px}
.proof-upload{width:100%;aspect-ratio:16/9;border:2px dashed rgba(255,255,255,.15);border-radius:10px;cursor:pointer;overflow:hidden;transition:border-color .2s}
.proof-upload:hover{border-color:rgba(255,255,255,.3)}
.proof-preview{width:100%;height:100%;object-fit:contain;background:rgba(0,0,0,.3)}
.proof-placeholder{width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;color:rgba(255,255,255,.25);gap:6px;font-size:13px}

.btn-switch{width:100%;padding:12px;border:1px solid rgba(255,255,255,.15);border-radius:12px;background:rgba(255,255,255,.04);color:rgba(255,255,255,.6);font-size:14px;cursor:pointer;margin-bottom:12px;transition:all .15s}
.btn-switch:hover{background:rgba(255,255,255,.08);color:#fff}
.btn-submit{width:100%;padding:14px;border:none;border-radius:12px;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;font-size:16px;font-weight:700;cursor:pointer;transition:opacity .15s}
.btn-submit:disabled{opacity:.4;cursor:not-allowed}
.submit-hint{text-align:center;font-size:12px;color:rgba(255,255,255,.3);margin-top:10px}

.btn-done{width:100%;padding:12px;border:1px solid rgba(0,212,170,.4);border-radius:10px;background:rgba(0,212,170,.1);color:#00d4aa;font-size:15px;font-weight:600;cursor:pointer;margin-top:12px}
.btn-back-channel{width:100%;padding:12px;border:1px solid rgba(255,255,255,.1);border-radius:10px;background:rgba(255,255,255,.04);color:rgba(255,255,255,.6);font-size:14px;cursor:pointer;margin-top:12px}

.result-card{position:relative;z-index:1;text-align:center;padding:40px 16px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);border-radius:16px;margin:0 16px}
.result-card h2{font-size:18px;margin:12px 0 8px}
.result-card p{font-size:14px;color:rgba(255,255,255,.55)}
.result-card--success h2{color:#00d4aa}
.result-sub{font-size:13px!important;opacity:.5;margin-top:6px}

.order-list{margin-bottom:16px}
.order-row{display:flex;justify-content:space-between;align-items:center;padding:14px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);border-radius:10px;margin-bottom:8px}
.order-info{display:flex;flex-direction:column;gap:2px}
.order-no{font-size:13px;font-weight:500}
.order-time{font-size:11px;color:rgba(255,255,255,.3)}
.order-amount{font-size:12px;color:rgba(255,255,255,.55)}
.status-tag{padding:2px 10px;border-radius:10px;font-size:11px;font-weight:600}
.status-tag.pending{background:rgba(245,158,11,.15);color:#f59e0b}

.waiting-section{padding:24px 16px;background:rgba(255,255,255,.03);border:1px solid rgba(245,158,11,.2);border-radius:16px}
.wait-head{text-align:center;margin-bottom:16px}
.wait-spinner{width:36px;height:36px;margin:0 auto 10px;border:3px solid rgba(245,158,11,.15);border-top-color:#f59e0b;border-radius:50%;animation:spin .9s linear infinite}
.section-title-wait{font-size:16px;font-weight:700;margin-bottom:6px}
.wait-tip{font-size:12px;color:rgba(255,255,255,.4)}
.wait-tip strong{color:#f59e0b}
.qr-loading{width:180px;height:180px;margin:0 auto;border-radius:12px;background:rgba(255,255,255,.05)}
.pay-amount-zone{margin-bottom:16px;padding:14px;background:rgba(245,158,11,.08);border:1px dashed rgba(245,158,11,.4);border-radius:12px;text-align:center}
.pay-amount-label{font-size:11px;color:rgba(255,255,255,.4);margin-bottom:6px}
.pay-amount-value{font-size:22px;font-weight:800;color:#f59e0b;cursor:pointer}
.copy-mini{font-size:11px;font-weight:400;color:rgba(255,255,255,.4);margin-left:8px}
.wait-meta{display:flex;flex-direction:column;gap:6px;font-size:12px;color:rgba(255,255,255,.4);margin-bottom:16px;padding:0 4px}
.wait-meta strong{color:rgba(255,255,255,.75)}
.wait-meta strong.warn{color:#ff6b4a}
.hl{color:#00d4aa}
.status-tag.passed{background:rgba(0,212,170,.15);color:#00d4aa}
.status-tag.rejected{background:rgba(255,45,85,.15);color:#ff2d55}

/* 复制 toast */
.copy-toast {
  position: fixed;
  left: 50%;
  bottom: 18vh;
  transform: translateX(-50%);
  background: rgba(20, 20, 28, 0.92);
  color: #fff;
  padding: 10px 22px;
  border-radius: 999px;
  font-size: 13px;
  letter-spacing: 0.5px;
  box-shadow: 0 6px 24px rgba(0, 0, 0, 0.35);
  z-index: 9999;
  pointer-events: none;
}
.toast-fade-enter-active,
.toast-fade-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}
.toast-fade-enter-from,
.toast-fade-leave-to {
  opacity: 0;
  transform: translateX(-50%) translateY(8px);
}
</style>
