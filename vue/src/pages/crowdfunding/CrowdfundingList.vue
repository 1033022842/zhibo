<template>
  <div class="CFListPage">
    <div class="bg-glow bg-glow--top"></div>
    <div class="bg-glow bg-glow--bottom"></div>

    <div class="top-bar">
      <button class="btn-back" @click="$router.back()">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
      </button>
      <span class="top-title">角色众筹</span>
    </div>

    <!-- Tab 切换 -->
    <div class="tabs" v-anim>
      <button
        v-for="t in tabs" :key="t.key"
        class="tab-btn"
        :class="{ active: activeTab === t.key }"
        @click="activeTab = t.key"
      >{{ t.label }}</button>
    </div>

    <!-- 发现项目 -->
    <template v-if="activeTab === 'discover'">
      <div class="section-header" v-anim>
        <h2>发现创意角色</h2>
        <p>支持你喜欢的角色创意，帮助它们成为现实</p>
      </div>

      <div class="loading-zone" v-if="loading">
        <div class="spinner"></div>
        <p>加载中...</p>
      </div>

      <div class="empty-zone" v-else-if="projects.length === 0">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        <p>暂无进行中的众筹项目</p>
        <p class="empty-sub">成为第一个发起众筹的商家吧！</p>
      </div>

      <div class="project-grid" v-else v-anim>
        <div
          class="project-card"
          v-for="p in projects" :key="p.id"
          @click="goDetail(p.id)"
        >
          <div class="card-cover">
            <img v-if="p.cover_url" :src="p.cover_url" alt="" />
            <div v-else class="card-cover-placeholder">
              <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="4"/><path d="M4 20v-1a8 8 0 0 1 8-8"/></svg>
            </div>
            <div class="card-deadline-tag">{{ formatDeadline(p.deadline) }}</div>
          </div>
          <div class="card-body">
            <h3 class="card-title">{{ p.title }}</h3>
            <p class="card-persona">角色：{{ p.persona_name }}</p>
            <div class="card-progress">
              <div class="progress-bar">
                <div class="progress-fill" :style="{ width: p.progress_percent + '%' }"></div>
              </div>
              <div class="progress-stats">
                <span class="progress-raised">{{ fmtNum(p.raised_amount) }} 钻</span>
                <span class="progress-target">/ {{ fmtNum(p.target_amount) }} 钻</span>
                <span class="progress-pct">{{ p.progress_percent }}%</span>
              </div>
            </div>
            <div class="card-meta">
              <span>{{ p.supporter_count }} 人支持</span>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- 我的支持 -->
    <template v-if="activeTab === 'pledges'">
      <div class="section-header" v-anim>
        <h2>我支持的项目</h2>
      </div>

      <div class="loading-zone" v-if="loadingPledges">
        <div class="spinner"></div>
        <p>加载中...</p>
      </div>

      <div class="empty-zone" v-else-if="myPledges.length === 0">
        <p>还没有支持过任何项目</p>
      </div>

      <div class="pledge-list" v-else v-anim>
        <div class="pledge-row" v-for="p in myPledges" :key="p.id" @click="goDetail(p.project_id)">
          <div class="pledge-info">
            <span class="pledge-project">{{ p.project?.title || '项目 #' + p.project_id }}</span>
            <span class="pledge-amount">{{ fmtNum(p.amount) }} 钻</span>
          </div>
          <div class="pledge-status">
            <span class="status-tag" :class="pledgeStatusClass(p.status)">{{ pledgeStatusText(p.status) }}</span>
            <span class="pledge-time">{{ p.created_at?.slice(0, 10) }}</span>
          </div>
        </div>
      </div>
    </template>

    <!-- 我的发起 -->
    <template v-if="activeTab === 'mine'">
      <div class="section-header" v-anim>
        <h2>我发起的众筹</h2>
        <button class="btn-create" @click="goCreate" v-if="!hasActive">发起众筹</button>
      </div>

      <div class="loading-zone" v-if="loadingMine">
        <div class="spinner"></div>
        <p>加载中...</p>
      </div>

      <div class="empty-zone" v-else-if="myProjects.length === 0">
        <p>还没有发起过众筹</p>
        <button class="btn-create btn-create--empty" @click="goCreate">发起众筹创建角色</button>
      </div>

      <div class="pledge-list" v-else v-anim>
        <div class="pledge-row" v-for="p in myProjects" :key="p.id" @click="goDetail(p.id)">
          <div class="pledge-info">
            <span class="pledge-project">{{ p.title }}</span>
            <span class="pledge-amount">
              {{ fmtNum(p.raised_amount) }} / {{ fmtNum(p.target_amount) }} 钻
              <span class="pledge-pct">({{ p.progress_percent || calcProgress(p) }}%)</span>
            </span>
          </div>
          <div class="pledge-status">
            <span class="status-tag" :class="projectStatusClass(p.status)">{{ projectStatusText(p.status) }}</span>
            <span class="pledge-time">{{ p.created_at?.slice(0, 10) }}</span>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  getCrowdfundingList,
  getMyProjects,
  getMyPledges,
  checkActiveCrowdfunding,
  type CrowdfundingProject,
  type CrowdfundingPledge
} from '@/api/crowdfunding'

const router = useRouter()

const activeTab = ref('discover')
const tabs = [
  { key: 'discover', label: '发现项目' },
  { key: 'pledges', label: '我的支持' },
  { key: 'mine', label: '我的发起' },
]

const loading = ref(true)
const loadingMine = ref(false)
const loadingPledges = ref(false)

const projects = ref<CrowdfundingProject[]>([])
const myProjects = ref<CrowdfundingProject[]>([])
const myPledges = ref<CrowdfundingPledge[]>([])
const hasActive = ref(false)

function fmtNum(n: number): string {
  return Number(n).toLocaleString()
}

function formatDeadline(date: string): string {
  if (!date) return ''
  const d = new Date(date)
  const now = Date.now()
  const diff = d.getTime() - now
  const days = Math.ceil(diff / 86400000)
  if (days <= 0) return '即将截止'
  if (days === 1) return '剩1天'
  if (days <= 7) return `剩${days}天`
  return d.toLocaleDateString('zh-CN', { month: 'numeric', day: 'numeric' }) + '截止'
}

function calcProgress(p: CrowdfundingProject): number {
  if (p.target_amount <= 0) return 0
  return Math.min(100, Math.round((p.raised_amount / p.target_amount) * 1000) / 10)
}

function projectStatusText(s: number): string {
  return ['进行中', '已成功', '已失败'][s] || '未知'
}
function projectStatusClass(s: number): string {
  return ['active', 'success', 'failed'][s] || ''
}
function pledgeStatusText(s: number): string {
  return ['冻结中', '已划转', '已退款'][s] || '未知'
}
function pledgeStatusClass(s: number): string {
  return ['active', 'success', 'failed'][s] || ''
}

function goDetail(id: number) {
  router.push('/crowdfunding/detail/' + id)
}
function goCreate() {
  router.push('/crowdfunding/create')
}

async function loadDiscover() {
  loading.value = true
  try {
    const res = await getCrowdfundingList()
    const data: any = (res.data as any)?.data || res.data
    projects.value = data?.list || []
  } catch { /* ignore */ }
  loading.value = false
}

async function loadMine() {
  loadingMine.value = true
  try {
    const [projRes, activeRes] = await Promise.all([getMyProjects(), checkActiveCrowdfunding()])
    const projData: any = (projRes.data as any)?.data || projRes.data
    myProjects.value = Array.isArray(projData) ? projData : []
    const activeData: any = (activeRes.data as any)?.data || activeRes.data
    hasActive.value = activeData?.has_active || false
  } catch { /* ignore */ }
  loadingMine.value = false
}

async function loadPledges() {
  loadingPledges.value = true
  try {
    const res = await getMyPledges()
    const data: any = (res.data as any)?.data || res.data
    myPledges.value = Array.isArray(data) ? data : []
  } catch { /* ignore */ }
  loadingPledges.value = false
}

watch(activeTab, (tab) => {
  if (tab === 'mine') loadMine()
  if (tab === 'pledges') loadPledges()
})

onMounted(() => {
  loadDiscover()
})
</script>

<style scoped>
.CFListPage {
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
.top-title {
  font-size: 18px;
  font-weight: 700;
}
.tabs {
  position: relative;
  z-index: 1;
  display: flex;
  gap: 4px;
  margin: 0 16px 16px;
  background: rgba(255,255,255,0.06);
  border-radius: 10px;
  padding: 4px;
}
.tab-btn {
  flex: 1;
  padding: 8px 0;
  border: none;
  background: none;
  color: rgba(255,255,255,0.55);
  font-size: 14px;
  font-weight: 500;
  border-radius: 8px;
  cursor: pointer;
  transition: all .2s;
}
.tab-btn.active {
  background: rgba(99,102,241,0.3);
  color: #fff;
}
.section-header {
  position: relative;
  z-index: 1;
  padding: 0 16px 12px;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
}
.section-header h2 {
  font-size: 16px;
  font-weight: 600;
}
.section-header p {
  font-size: 12px;
  color: rgba(255,255,255,0.45);
  margin-top: 4px;
}
.btn-create {
  padding: 6px 16px;
  border: 1px solid rgba(99,102,241,0.6);
  border-radius: 20px;
  background: rgba(99,102,241,0.15);
  color: #a5b4fc;
  font-size: 13px;
  cursor: pointer;
  transition: all .2s;
}
.btn-create:hover { background: rgba(99,102,241,0.3); }
.btn-create--empty {
  margin-top: 12px;
  font-size: 14px;
  padding: 10px 24px;
}
.loading-zone, .empty-zone {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 40px 16px;
  color: rgba(255,255,255,0.45);
  gap: 8px;
}
.empty-sub { font-size: 13px; opacity: 0.6; }
.spinner {
  width: 28px; height: 28px;
  border: 3px solid rgba(255,255,255,0.1);
  border-top-color: #6366f1;
  border-radius: 50%;
  animation: spin .7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.project-grid {
  position: relative;
  z-index: 1;
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  padding: 0 16px;
}
.project-card {
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 12px;
  overflow: hidden;
  cursor: pointer;
  transition: transform .15s, border-color .15s;
}
.project-card:active { transform: scale(0.98); }
.card-cover {
  position: relative;
  aspect-ratio: 1;
  background: rgba(255,255,255,0.05);
  overflow: hidden;
}
.card-cover img {
  width: 100%; height: 100%;
  object-fit: cover;
}
.card-cover-placeholder {
  width: 100%; height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: rgba(255,255,255,0.2);
}
.card-deadline-tag {
  position: absolute;
  bottom: 6px; right: 6px;
  background: rgba(0,0,0,0.7);
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 11px;
  color: rgba(255,255,255,0.8);
}
.card-body { padding: 10px; }
.card-title {
  font-size: 13px;
  font-weight: 600;
  line-height: 1.3;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  margin-bottom: 4px;
}
.card-persona {
  font-size: 11px;
  color: rgba(255,255,255,0.45);
  margin-bottom: 8px;
}
.card-progress { margin-bottom: 6px; }
.progress-bar {
  height: 4px;
  background: rgba(255,255,255,0.08);
  border-radius: 2px;
  overflow: hidden;
  margin-bottom: 4px;
}
.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #6366f1, #a855f7);
  border-radius: 2px;
  transition: width .4s;
}
.progress-stats {
  display: flex;
  gap: 4px;
  font-size: 11px;
}
.progress-raised { color: #a5b4fc; font-weight: 600; }
.progress-target { color: rgba(255,255,255,0.35); }
.progress-pct { color: rgba(255,255,255,0.45); margin-left: auto; }
.card-meta {
  font-size: 11px;
  color: rgba(255,255,255,0.35);
}

.pledge-list {
  position: relative;
  z-index: 1;
  margin: 0 16px;
}
.pledge-row {
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 10px;
  padding: 14px;
  margin-bottom: 8px;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  align-items: center;
  transition: background .15s;
}
.pledge-row:active { background: rgba(255,255,255,0.08); }
.pledge-info { display: flex; flex-direction: column; gap: 4px; }
.pledge-project { font-size: 14px; font-weight: 500; }
.pledge-amount { font-size: 13px; color: #a5b4fc; }
.pledge-pct { color: rgba(255,255,255,0.45); font-size: 12px; }
.pledge-status { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
.pledge-time { font-size: 11px; color: rgba(255,255,255,0.3); }
.status-tag {
  padding: 2px 10px;
  border-radius: 10px;
  font-size: 11px;
  font-weight: 600;
}
.status-tag.active { background: rgba(99,102,241,.2); color: #a5b4fc; }
.status-tag.success { background: rgba(0,212,170,.15); color: #00d4aa; }
.status-tag.failed { background: rgba(255,45,85,.15); color: #ff2d55; }
</style>
