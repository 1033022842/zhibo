<template>
  <div class="CFDetailPage">
    <div class="bg-glow bg-glow--top"></div>
    <div class="bg-glow bg-glow--bottom"></div>

    <div class="top-bar">
      <button class="btn-back" @click="$router.back()">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
      </button>
      <span class="top-title">项目详情</span>
    </div>

    <div class="loading-zone" v-if="loading">
      <div class="spinner"></div>
      <p>加载中...</p>
    </div>

    <template v-else-if="project">
      <!-- 封面 -->
      <div class="hero-cover" v-anim>
        <img v-if="project.cover_url" :src="project.cover_url" alt="" />
        <div v-else class="hero-placeholder">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><circle cx="12" cy="8" r="4"/><path d="M4 20v-1a8 8 0 0 1 8-8"/></svg>
        </div>
        <div class="hero-overlay">
          <h1 class="hero-title">{{ project.title }}</h1>
          <p class="hero-persona">角色：{{ project.persona_name }}</p>
        </div>
        <div class="status-badge" :class="statusClass(project.status)">
          {{ statusText(project.status) }}
        </div>
      </div>

      <!-- 进度 -->
      <div class="progress-card" v-anim>
        <div class="progress-header">
          <div class="progress-amounts">
            <span class="raised">{{ fmtNum(project.raised_amount) }} 钻</span>
            <span class="target">目标 {{ fmtNum(project.target_amount) }} 钻</span>
          </div>
          <span class="progress-pct">{{ project.progress_percent }}%</span>
        </div>
        <div class="progress-bar">
          <div class="progress-fill" :style="{ width: project.progress_percent + '%' }"></div>
        </div>
        <div class="progress-meta">
          <span>{{ project.supporter_count }} 人支持</span>
          <span>截止 {{ formatDate(project.deadline) }}</span>
        </div>
      </div>

      <!-- 描述 -->
      <div class="desc-card" v-anim v-if="project.description">
        <h3>项目描述</h3>
        <div class="desc-body" v-html="project.description"></div>
      </div>

      <!-- 支持按钮 / 状态提示 -->
      <div class="action-zone" v-anim>
        <template v-if="project.status === 0">
          <div class="pledge-input-row">
            <input
              v-model.number="pledgeAmount"
              type="number"
              class="pledge-input"
              placeholder="输入支持金额（钻石）"
              min="1"
            />
            <span class="pledge-unit">钻</span>
          </div>
          <div class="quick-btns">
            <button v-for="n in quickAmounts" :key="n" class="quick-btn" :class="{ active: pledgeAmount === n }" @click="pledgeAmount = n">
              {{ n }}
            </button>
          </div>
          <button class="btn-pledge" @click="doPledge" :disabled="pledging || pledgeAmount <= 0">
            {{ pledging ? '处理中...' : '支持这个角色' }}
          </button>
          <p class="pledge-hint">支持后资金将冻结，达标后划转给商家，未达标全额退还</p>
          <p class="pledge-hint" style="margin-top:6px">
            没有钻石？
            <a class="btn-topup" @click="doTopup">获取 1000 测试钻石</a>
          </p>
        </template>

        <template v-else-if="project.status === 1">
          <div class="result-card result-card--success">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#00d4aa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <h2>众筹成功</h2>
            <p>已筹 {{ fmtNum(project.raised_amount) }} 钻，{{ project.supporter_count }} 人支持</p>
            <p class="result-sub">商家可创建角色</p>
          </div>
        </template>

        <template v-else>
          <div class="result-card result-card--failed">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#ff2d55" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            <h2>众筹未达标</h2>
            <p>已筹 {{ fmtNum(project.raised_amount) }} 钻 / 目标 {{ fmtNum(project.target_amount) }} 钻</p>
            <p class="result-sub">资金已退还支持者</p>
          </div>
        </template>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getCrowdfundingDetail, pledgeCrowdfunding, topupDiamonds, type CrowdfundingProject } from '@/api/crowdfunding'

const route = useRoute()
const router = useRouter()

const id = Number(route.params.id)
const loading = ref(true)
const project = ref<CrowdfundingProject | null>(null)
const pledgeAmount = ref(100)
const pledging = ref(false)
const quickAmounts = [10, 50, 100, 500, 1000]

function fmtNum(n: number): string {
  return Number(n).toLocaleString()
}
function formatDate(d: string): string {
  if (!d) return ''
  return new Date(d).toLocaleDateString('zh-CN')
}
function statusText(s: number): string {
  return ['进行中', '已成功', '已失败'][s] || ''
}
function statusClass(s: number): string {
  return ['active', 'success', 'failed'][s] || ''
}

async function doPledge() {
  if (pledging.value || pledgeAmount.value <= 0) return
  pledging.value = true
  try {
    const res: any = await pledgeCrowdfunding(id, pledgeAmount.value)
    const respData = res?.data
    if (respData && respData.code && respData.code !== '00000') {
      alert(respData.msg || '支持失败，请稍后重试')
      pledging.value = false
      return
    }
    alert('支持成功！')
    // 刷新数据
    const detailRes = await getCrowdfundingDetail(id)
    project.value = (detailRes.data as any)?.data || detailRes.data
  } catch (e: any) {
    alert(e?.message || e?.msg || '支持失败，请稍后重试')
  }
  pledging.value = false
}

onMounted(async () => {
  try {
    const res = await getCrowdfundingDetail(id)
    project.value = (res.data as any)?.data || res.data
  } catch { /* ignore */ }
  loading.value = false
})

async function doTopup() {
  try {
    const res: any = await topupDiamonds(1000)
    const data = res?.data
    if (data && data.balance_after !== undefined) {
      alert(`已添加 1000 测试钻石！当前余额: ${data.balance_after} 钻`)
    }
  } catch { /* ignore */ }
}
</script>

<style scoped>
.CFDetailPage {
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
  top: -120px;
  left: -80px;
  width: 320px;
  height: 320px;
  background: radial-gradient(circle, #6366f1, transparent);
}
.bg-glow--bottom {
  bottom: -120px;
  right: -80px;
  width: 320px;
  height: 320px;
  background: radial-gradient(circle, #a855f7, transparent);
}
.top-bar {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 16px;
}
.btn-back {
  background: none;
  border: none;
  color: #fff;
  cursor: pointer;
  padding: 4px;
  display: flex;
}
.top-title { font-size: 18px; font-weight: 700; }
.loading-zone {
  position: relative; z-index: 1;
  display: flex; flex-direction: column; align-items: center;
  padding: 60px 16px; color: rgba(255,255,255,0.45); gap: 12px;
}
.spinner {
  width: 28px; height: 28px;
  border: 3px solid rgba(255,255,255,0.1);
  border-top-color: #6366f1;
  border-radius: 50%;
  animation: spin .7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.hero-cover {
  position: relative; z-index: 1;
  margin: 0 16px;
  border-radius: 16px;
  overflow: hidden;
  aspect-ratio: 16/9;
  background: rgba(255,255,255,0.05);
}
.hero-cover img {
  width: 100%; height: 100%; object-fit: cover;
}
.hero-placeholder {
  width: 100%; height: 100%;
  display: flex; align-items: center; justify-content: center;
  color: rgba(255,255,255,0.15);
}
.hero-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, transparent 60%);
  display: flex; flex-direction: column; justify-content: flex-end;
  padding: 16px;
}
.hero-title { font-size: 18px; font-weight: 700; }
.hero-persona { font-size: 13px; color: rgba(255,255,255,0.6); margin-top: 4px; }
.status-badge {
  position: absolute; top: 10px; right: 10px;
  padding: 4px 12px; border-radius: 12px;
  font-size: 12px; font-weight: 600;
}
.status-badge.active { background: rgba(99,102,241,.3); color: #a5b4fc; }
.status-badge.success { background: rgba(0,212,170,.25); color: #00d4aa; }
.status-badge.failed { background: rgba(255,45,85,.25); color: #ff2d55; }

.progress-card {
  position: relative; z-index: 1;
  margin: 16px;
  padding: 16px;
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 12px;
}
.progress-header {
  display: flex; justify-content: space-between; align-items: flex-end;
  margin-bottom: 10px;
}
.progress-amounts { display: flex; flex-direction: column; gap: 2px; }
.raised { font-size: 22px; font-weight: 700; color: #a5b4fc; }
.target { font-size: 12px; color: rgba(255,255,255,0.4); }
.progress-pct { font-size: 24px; font-weight: 700; color: rgba(255,255,255,0.7); }
.progress-bar {
  height: 6px; background: rgba(255,255,255,0.08);
  border-radius: 3px; overflow: hidden; margin-bottom: 8px;
}
.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #6366f1, #a855f7);
  border-radius: 3px; transition: width .5s;
}
.progress-meta {
  display: flex; justify-content: space-between;
  font-size: 12px; color: rgba(255,255,255,0.35);
}

.desc-card {
  position: relative; z-index: 1;
  margin: 0 16px 16px;
  padding: 16px;
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 12px;
}
.desc-card h3 { font-size: 15px; font-weight: 600; margin-bottom: 8px; }
.desc-body { font-size: 13px; color: rgba(255,255,255,0.65); line-height: 1.7; }

.action-zone {
  position: relative; z-index: 1;
  margin: 0 16px;
}
.pledge-input-row {
  display: flex; align-items: center;
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 12px; padding: 4px 12px;
  margin-bottom: 12px;
}
.pledge-input {
  flex: 1;
  background: none; border: none;
  color: #fff; font-size: 20px; font-weight: 700;
  padding: 12px 0; outline: none;
}
.pledge-input::placeholder { color: rgba(255,255,255,0.25); }
.pledge-unit { font-size: 16px; color: rgba(255,255,255,0.4); margin-left: 4px; }
.quick-btns {
  display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap;
}
.quick-btn {
  padding: 8px 16px;
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 20px;
  background: rgba(255,255,255,0.04);
  color: rgba(255,255,255,0.6);
  font-size: 14px; cursor: pointer;
  transition: all .15s;
}
.quick-btn.active {
  background: rgba(99,102,241,0.25);
  border-color: rgba(99,102,241,0.5);
  color: #a5b4fc;
}
.btn-pledge {
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
.btn-pledge:disabled { opacity: 0.4; cursor: not-allowed; }
.btn-pledge:active:not(:disabled) { opacity: 0.85; }
.pledge-hint {
  text-align: center;
  font-size: 12px;
  color: rgba(255,255,255,0.3);
  margin-top: 10px;
  line-height: 1.5;
}

.result-card {
  text-align: center;
  padding: 40px 16px;
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 16px;
}
.result-card h2 { font-size: 18px; margin: 12px 0 8px; }
.result-card p { font-size: 14px; color: rgba(255,255,255,0.55); }
.result-card--success h2 { color: #00d4aa; }
.result-card--failed h2 { color: #ff2d55; }
.result-sub { font-size: 13px !important; margin-top: 6px; opacity: 0.5; }
.btn-topup {
  color: #a5b4fc;
  text-decoration: underline;
  cursor: pointer;
}
.btn-topup:hover { color: #c4b5fd; }
</style>
