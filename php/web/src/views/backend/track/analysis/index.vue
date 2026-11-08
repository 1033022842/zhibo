<template>
    <div class="default-main ta-box">
        <!-- 筛选栏 -->
        <el-form :inline="true" class="ta-filter">
            <el-form-item label="日期">
                <el-date-picker
                    v-model="dateRange"
                    type="daterange"
                    value-format="YYYY-MM-DD"
                    range-separator="-"
                    start-placeholder="开始"
                    end-placeholder="结束"
                    :clearable="false"
                    :disabled-date="disabledAfterToday"
                />
            </el-form-item>
            <el-form-item label="域名">
                <el-select v-model="domain" clearable filterable placeholder="全部" class="ta-select">
                    <el-option v-for="d in options.domains" :key="d" :label="d" :value="d" />
                </el-select>
            </el-form-item>
            <el-form-item label="渠道">
                <el-select v-model="channel" clearable filterable placeholder="全部" class="ta-select">
                    <el-option v-for="c in options.channels" :key="c" :label="c" :value="c" />
                </el-select>
            </el-form-item>
            <el-form-item>
                <el-button type="primary" @click="onSearch">查询</el-button>
            </el-form-item>
        </el-form>

        <el-tabs v-model="tab" @tab-change="onTabChange">
            <!-- ============ 总览 ============ -->
            <el-tab-pane label="总览漏斗" name="overview">
                <el-row :gutter="12">
                    <el-col v-for="c in overviewCards" :key="c.label" :xs="12" :sm="8" :md="6" :lg="3">
                        <el-card shadow="never" class="ta-card">
                            <div class="ta-card-label">{{ c.label }}</div>
                            <div class="ta-card-value">{{ c.value }}</div>
                            <div v-if="c.sub" class="ta-card-sub">{{ c.sub }}</div>
                        </el-card>
                    </el-col>
                </el-row>
                <el-card shadow="never" class="ta-chart-card">
                    <div ref="chartRef" class="ta-chart"></div>
                </el-card>
            </el-tab-pane>

            <!-- ============ 页面退出/停留 ============ -->
            <el-tab-pane label="页面退出/停留" name="page">
                <div class="ta-toolbar">
                    <el-select v-model="pageSort" class="ta-sort" @change="loadPage()">
                        <el-option label="按浏览量排序" value="pv" />
                        <el-option label="按平均停留排序" value="duration" />
                        <el-option label="按退出次数排序" value="exit" />
                    </el-select>
                    <el-input
                        v-model="pageKeyword"
                        placeholder="搜索页面路径，如 /Chat.html"
                        clearable
                        class="ta-keyword"
                        @keyup.enter="loadPage()"
                    />
                    <el-button @click="loadPage()">搜索</el-button>
                </div>
                <el-table v-loading="pageLoading" :data="pageList" border stripe>
                    <el-table-column prop="page" label="页面路径" min-width="260" show-overflow-tooltip />
                    <el-table-column prop="pv" label="浏览量 PV" width="100" align="center" sortable />
                    <el-table-column prop="uv" label="访客数 UV" width="100" align="center" />
                    <el-table-column label="平均停留" width="110" align="center" sortable :sort-by="'avg_duration'">
                        <template #default="{ row }">{{ fmtDuration(row.avg_duration) }}</template>
                    </el-table-column>
                    <el-table-column prop="exits" label="退出次数" width="100" align="center" />
                    <el-table-column label="退出率" width="100" align="center">
                        <template #default="{ row }">
                            <el-tag :type="row.exit_rate >= 60 ? 'danger' : row.exit_rate >= 35 ? 'warning' : 'success'" effect="plain">
                                {{ row.exit_rate }}%
                            </el-tag>
                        </template>
                    </el-table-column>
                    <template #empty>暂无数据，请确认站点已安装新版 tracking.js 并有访问流量</template>
                </el-table>
                <div class="ta-pager">
                    <el-pagination
                        v-model:current-page="pageNo.page"
                        :page-size="pageSize"
                        :total="pageTotal"
                        layout="total, prev, pager, next"
                        @current-change="loadPage()"
                    />
                </div>
            </el-tab-pane>

            <!-- ============ 功能使用 ============ -->
            <el-tab-pane label="功能使用" name="feature">
                <div class="ta-toolbar">
                    <el-input
                        v-model="featureKeyword"
                        placeholder="搜索事件名，如 chat_send / register"
                        clearable
                        class="ta-keyword"
                        @keyup.enter="loadFeature()"
                    />
                    <el-button @click="loadFeature()">搜索</el-button>
                </div>
                <el-table v-loading="featureLoading" :data="featureList" border stripe>
                    <el-table-column prop="event" label="功能事件" width="180">
                        <template #default="{ row }">
                            <el-tag effect="plain">{{ row.event }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="cnt" label="使用次数" width="110" align="center" />
                    <el-table-column prop="visitors" label="触达访客" width="110" align="center" />
                    <el-table-column prop="users" label="触达用户" width="110" align="center" />
                    <el-table-column prop="last_at" label="最近使用时间" min-width="170" align="center" />
                    <template #empty>暂无功能事件数据</template>
                </el-table>
            </el-tab-pane>

            <!-- ============ 登录分析 ============ -->
            <el-tab-pane label="登录分析" name="login">
                <el-row :gutter="12">
                    <el-col :xs="24" :md="8">
                        <el-card shadow="never" header="登录次数分布（按用户）">
                            <el-table :data="distributionList" size="small">
                                <el-table-column prop="name" label="登录次数" align="center" />
                                <el-table-column prop="value" label="用户数" align="center" />
                                <el-table-column label="占比" align="center">
                                    <template #default="{ row }">
                                        {{ distributionTotal > 0 ? ((row.value / distributionTotal) * 100).toFixed(1) : 0 }}%
                                    </template>
                                </el-table-column>
                            </el-table>
                        </el-card>
                    </el-col>
                    <el-col :xs="24" :md="16">
                        <el-card shadow="never" header="多次登录用户（登录 ≥ 2 次）">
                            <el-table v-loading="loginLoading" :data="multiList" size="small" border stripe>
                                <el-table-column prop="user_no" label="用户编号" width="150" />
                                <el-table-column prop="nickname" label="昵称" min-width="120" show-overflow-tooltip />
                                <el-table-column prop="login_count" label="登录次数" width="90" align="center" />
                                <el-table-column prop="last_at" label="最近登录" width="160" align="center" />
                                <el-table-column prop="reg_time" label="注册时间" width="160" align="center" />
                                <template #empty>暂无多次登录用户</template>
                            </el-table>
                            <div class="ta-pager">
                                <el-pagination
                                    v-model:current-page="multiPage"
                                    :page-size="pageSize"
                                    :total="multiTotal"
                                    layout="total, prev, pager, next"
                                    @current-change="loadLogin()"
                                />
                            </div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-card shadow="never" header="登录明细（含失败记录）" class="ta-login-detail">
                    <div class="ta-toolbar">
                        <el-input
                            v-model="loginKeyword"
                            placeholder="搜索账号/IP/vid/用户名"
                            clearable
                            class="ta-keyword"
                            @keyup.enter="loadLogin()"
                        />
                        <el-select v-model="loginSuccess" class="ta-sort" @change="loadLogin()">
                            <el-option label="全部结果" value="" />
                            <el-option label="仅成功" value="1" />
                            <el-option label="仅失败" value="0" />
                        </el-select>
                        <el-button @click="loadLogin()">搜索</el-button>
                    </div>
                    <el-table v-loading="loginLoading" :data="loginDetail" border stripe>
                        <el-table-column prop="created_at" label="时间" width="160" align="center" />
                        <el-table-column label="结果" width="80" align="center">
                            <template #default="{ row }">
                                <el-tag :type="row.success == 1 ? 'success' : 'danger'" effect="plain">
                                    {{ row.success == 1 ? '成功' : '失败' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="注册" width="70" align="center">
                            <template #default="{ row }">{{ row.is_register == 1 ? '是' : '-' }}</template>
                        </el-table-column>
                        <el-table-column prop="account" label="账号" min-width="150" show-overflow-tooltip />
                        <el-table-column prop="user_no" label="用户编号" width="150">
                            <template #default="{ row }">{{ row.user_no || '-' }}</template>
                        </el-table-column>
                        <el-table-column prop="ip" label="IP" width="130" />
                        <el-table-column prop="fail_reason" label="失败原因" min-width="120" show-overflow-tooltip />
                        <el-table-column prop="vid" label="vid" width="120" show-overflow-tooltip>
                            <template #default="{ row }">
                                <el-link v-if="row.vid" type="primary" @click="goDetail(row.vid)">{{ row.vid }}</el-link>
                                <span v-else>-</span>
                            </template>
                        </el-table-column>
                        <template #empty>暂无登录记录</template>
                    </el-table>
                    <div class="ta-pager">
                        <el-pagination
                            v-model:current-page="loginDetailPage"
                            :page-size="pageSize"
                            :total="loginDetailTotal"
                            layout="total, prev, pager, next"
                            @current-change="loadLogin()"
                        />
                    </div>
                </el-card>
            </el-tab-pane>

            <!-- ============ 行为明细 ============ -->
            <el-tab-pane label="行为明细" name="detail">
                <div class="ta-toolbar">
                    <el-input
                        v-model="detailKeyword"
                        placeholder="输入 vid（v_开头）/ 用户编号（U开头）/ 用户ID"
                        clearable
                        class="ta-keyword"
                        @keyup.enter="loadDetail()"
                    />
                    <el-button type="primary" @click="loadDetail()">查询路径</el-button>
                </div>

                <el-alert
                    v-if="detailUser"
                    :title="'用户 ' + (detailUser.user_no || detailUser.id) + '（' + (detailUser.nickname || '') + '），共 ' + sessionList.length + ' 个会话'"
                    type="info"
                    :closable="false"
                    class="ta-detail-user"
                />

                <el-table v-loading="detailLoading" :data="sessionList" border stripe highlight-current-row>
                    <el-table-column prop="started_at" label="会话开始" width="160" align="center" />
                    <el-table-column prop="ended_at" label="会话结束" width="160" align="center" />
                    <el-table-column prop="pv" label="页面浏览" width="90" align="center" />
                    <el-table-column prop="acts" label="功能动作" width="90" align="center" />
                    <el-table-column label="会话停留" width="110" align="center">
                        <template #default="{ row }">{{ fmtDuration(row.stay) }}</template>
                    </el-table-column>
                    <el-table-column label="操作" width="110" align="center">
                        <template #default="{ row }">
                            <el-button size="small" type="primary" link @click="loadTimeline(row.sid)">查看路径</el-button>
                        </template>
                    </el-table-column>
                    <template #empty>输入 vid 或用户编号后查询</template>
                </el-table>

                <el-card v-if="timelineList.length" shadow="never" class="ta-timeline-card">
                    <template #header>访问路径（会话 {{ timelineSid }}，按时间正序）</template>
                    <el-timeline>
                        <el-timeline-item
                            v-for="(item, idx) in timelineList"
                            :key="idx"
                            :timestamp="item.created_at"
                            :type="item.event === 'pageview' ? 'primary' : 'warning'"
                            :hollow="item.event !== 'pageview'"
                        >
                            <template v-if="item.event === 'pageview'">
                                浏览 <b>{{ item.page }}</b>
                                <span v-if="item.duration > 0" class="ta-stay">（停留 {{ fmtDuration(item.duration) }}）</span>
                            </template>
                            <template v-else>
                                动作 <el-tag size="small" effect="plain">{{ item.event }}</el-tag>
                                <span v-if="item.props" class="ta-props">{{ item.props }}</span>
                            </template>
                        </el-timeline-item>
                    </el-timeline>
                </el-card>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import * as echarts from 'echarts'
import { behaviorDetail, featureStats, loginStats, overview, pageStats } from '/@/api/backend/track'

defineOptions({
    name: 'track/analysis',
})

/* ---------------- 筛选 ---------------- */
const now = new Date()
const fmtDate = (d: Date) => d.toISOString().slice(0, 10)
const initStart = new Date(now.getTime() - 6 * 86400 * 1000)
const dateRange = ref([fmtDate(initStart), fmtDate(now)] as string[])
const domain = ref('')
const channel = ref('')
const options = reactive<{ domains: string[]; channels: string[] }>({ domains: [], channels: [] })
const tab = ref('overview')

const disabledAfterToday = (d: Date) => d.getTime() > now.getTime()

const queryBase = () => ({
    start: dateRange.value?.[0] || undefined,
    end: dateRange.value?.[1] || undefined,
    domain: domain.value || undefined,
    channel: channel.value || undefined,
})

/* ---------------- 总览 ---------------- */
const cards = reactive({
    pv: 0,
    uv: 0,
    sessions: 0,
    avg_duration: 0,
    registers: 0,
    register_rate: 0,
    login_users: 0,
    multi_users: 0,
    multi_rate: 0,
    login_count: 0,
})
const trend = ref<any[]>([])
const overviewCards = ref<any[]>([])
const chartRef = ref<HTMLDivElement>()
let chart: echarts.ECharts | null = null

const buildCards = () => {
    overviewCards.value = [
        { label: '浏览量 PV', value: cards.pv },
        { label: '访客数 UV', value: cards.uv },
        { label: '会话数', value: cards.sessions },
        { label: '平均停留', value: fmtDuration(cards.avg_duration) },
        { label: '注册数', value: cards.registers, sub: '注册转化率 ' + cards.register_rate + '%（按UV）' },
        { label: '登录用户数', value: cards.login_users, sub: '登录 ' + cards.login_count + ' 次' },
        { label: '多次登录用户', value: cards.multi_users, sub: '占比 ' + cards.multi_rate + '%' },
    ]
}

const renderChart = () => {
    if (!chartRef.value) return
    if (!chart) chart = echarts.init(chartRef.value)
    const dates = trend.value.map((r) => r.date)
    chart.setOption({
        tooltip: { trigger: 'axis' },
        legend: { data: ['PV', 'UV', '注册', '登录用户'] },
        grid: { left: 48, right: 24, top: 40, bottom: 32 },
        xAxis: { type: 'category', boundaryGap: false, data: dates },
        yAxis: { type: 'value', minInterval: 1 },
        series: [
            { name: 'PV', type: 'line', smooth: true, data: trend.value.map((r) => r.pv) },
            { name: 'UV', type: 'line', smooth: true, data: trend.value.map((r) => r.uv) },
            { name: '注册', type: 'line', smooth: true, data: trend.value.map((r) => r.register) },
            { name: '登录用户', type: 'line', smooth: true, data: trend.value.map((r) => r.login) },
        ],
    })
}

const loadOverview = async () => {
    try {
        const res = await overview(queryBase())
        const d = res.data
        Object.assign(cards, d.cards || {})
        options.domains = d.options?.domains || []
        options.channels = d.options?.channels || []
        trend.value = d.trend || []
        buildCards()
        await nextTick()
        renderChart()
    } catch (e) {
        console.error('overview 加载失败', e)
    }
}

/* ---------------- 页面分析 ---------------- */
const pageList = ref<any[]>([])
const pageTotal = ref(0)
const pageNo = reactive({ page: 1 })
const pageSize = 20
const pageLoading = ref(false)
const pageKeyword = ref('')
const pageSort = ref('pv')
const pageLoaded = ref(false)

const loadPage = async () => {
    pageLoading.value = true
    try {
        const res = await pageStats({ ...queryBase(), keyword: pageKeyword.value || undefined, sort: pageSort.value, page: pageNo.page, limit: pageSize })
        pageList.value = res.data.list || []
        pageTotal.value = res.data.total || 0
        pageLoaded.value = true
    } finally {
        pageLoading.value = false
    }
}

/* ---------------- 功能使用 ---------------- */
const featureList = ref<any[]>([])
const featureLoading = ref(false)
const featureKeyword = ref('')
const featureLoaded = ref(false)

const loadFeature = async () => {
    featureLoading.value = true
    try {
        const res = await featureStats({ ...queryBase(), keyword: featureKeyword.value || undefined })
        featureList.value = res.data.list || []
        featureLoaded.value = true
    } finally {
        featureLoading.value = false
    }
}

/* ---------------- 登录分析 ---------------- */
const distributionList = ref<any[]>([])
const distributionTotal = ref(0)
const multiList = ref<any[]>([])
const multiTotal = ref(0)
const multiPage = ref(1)
const loginDetail = ref<any[]>([])
const loginDetailTotal = ref(0)
const loginDetailPage = ref(1)
const loginKeyword = ref('')
const loginSuccess = ref('')
const loginLoading = ref(false)
const loginLoaded = ref(false)

const loadLogin = async () => {
    loginLoading.value = true
    try {
        const res = await loginStats({
            ...queryBase(),
            keyword: loginKeyword.value || undefined,
            success: loginSuccess.value || undefined,
            multi_page: multiPage.value,
            detail_page: loginDetailPage.value,
            limit: pageSize,
        })
        const d = res.data
        distributionList.value = Object.entries(d.distribution || {}).map(([name, value]) => ({ name, value }))
        distributionTotal.value = distributionList.value.reduce((s: number, r: any) => s + Number(r.value || 0), 0)
        multiList.value = d.multi?.list || []
        multiTotal.value = d.multi?.total || 0
        loginDetail.value = d.detail?.list || []
        loginDetailTotal.value = d.detail?.total || 0
        loginLoaded.value = true
    } finally {
        loginLoading.value = false
    }
}

/* ---------------- 行为明细 ---------------- */
const detailKeyword = ref('')
const detailLoading = ref(false)
const detailUser = ref<any>(null)
const sessionList = ref<any[]>([])
const timelineList = ref<any[]>([])
const timelineSid = ref('')
const detailLoaded = ref(false)

const loadDetail = async (kw?: string) => {
    const keyword = kw || detailKeyword.value
    if (!keyword) return
    detailLoading.value = true
    try {
        const res = await behaviorDetail({ keyword })
        const d = res.data
        detailUser.value = d.user || null
        sessionList.value = d.sessions || []
        timelineList.value = []
        timelineSid.value = ''
        if (d.timeline?.length) {
            timelineList.value = d.timeline
            timelineSid.value = d.sid || ''
        } else if (sessionList.value.length) {
            await loadTimeline(sessionList.value[0].sid)
        }
        detailLoaded.value = true
    } finally {
        detailLoading.value = false
    }
}

const loadTimeline = async (sid: string) => {
    if (!sid) return
    const res = await behaviorDetail({ keyword: detailKeyword.value || (detailUser.value?.user_no ?? ''), sid })
    timelineList.value = res.data.timeline || []
    timelineSid.value = sid
}

const goDetail = (vid: string) => {
    tab.value = 'detail'
    detailKeyword.value = vid
    loadDetail(vid)
}

/* ---------------- Tab 切换（懒加载） ---------------- */
const onTabChange = (name: string | number) => {
    if (name === 'page' && !pageLoaded.value) loadPage()
    if (name === 'feature' && !featureLoaded.value) loadFeature()
    if (name === 'login' && !loginLoaded.value) loadLogin()
}

const onSearch = () => {
    pageNo.page = 1
    multiPage.value = 1
    loginDetailPage.value = 1
    pageLoaded.value = false
    featureLoaded.value = false
    loginLoaded.value = false
    loadOverview()
    onTabChange(tab.value)
}

/* ---------------- 工具 ---------------- */
const fmtDuration = (sec: number | string) => {
    const s = Math.round(Number(sec) || 0)
    if (s <= 0) return '-'
    if (s < 60) return s + '秒'
    const m = Math.floor(s / 60)
    if (m < 60) return m + '分' + (s % 60) + '秒'
    const h = Math.floor(m / 60)
    return h + '小时' + (m % 60) + '分'
}

const echartsResize = () => chart?.resize()

onMounted(() => {
    loadOverview()
    window.addEventListener('resize', echartsResize)
})

onBeforeUnmount(() => {
    window.removeEventListener('resize', echartsResize)
    chart?.dispose()
    chart = null
})
</script>

<style scoped lang="scss">
.ta-box {
    .ta-filter {
        padding-bottom: 4px;
        margin-bottom: 8px;
        border-bottom: 1px solid var(--el-border-color-lighter);
    }
    .ta-select,
    .ta-sort {
        width: 180px;
    }
    .ta-keyword {
        width: 260px;
    }
    .ta-toolbar {
        display: flex;
        gap: 8px;
        align-items: center;
        margin-bottom: 10px;
    }
    .ta-card {
        margin-bottom: 12px;
        .ta-card-label {
            font-size: 12px;
            color: var(--el-text-color-secondary);
        }
        .ta-card-value {
            margin-top: 4px;
            font-size: 22px;
            font-weight: 700;
        }
        .ta-card-sub {
            margin-top: 2px;
            font-size: 12px;
            color: var(--el-text-color-secondary);
        }
    }
    .ta-chart-card {
        .ta-chart {
            width: 100%;
            height: 320px;
        }
    }
    .ta-pager {
        display: flex;
        justify-content: flex-end;
        margin-top: 10px;
    }
    .ta-login-detail {
        margin-top: 12px;
    }
    .ta-detail-user {
        margin-bottom: 10px;
    }
    .ta-timeline-card {
        margin-top: 14px;
        .ta-stay {
            color: var(--el-text-color-secondary);
            font-size: 12px;
        }
        .ta-props {
            margin-left: 6px;
            color: var(--el-text-color-secondary);
            font-size: 12px;
            word-break: break-all;
        }
    }
}
</style>
