<template>
    <div class="default-main dashboard-main">
        <!-- 顶部欢迎 -->
        <div class="banner">
            <div class="welcome">
                <div class="welcome-text">
                    <div class="welcome-title">{{ adminInfo.nickname + '，' + getGreet() }}</div>
                    <div class="welcome-note">Sugus 运营控制台 · 数据实时来自线上库</div>
                </div>
                <div class="refresh-btn" @click="loadData">
                    <Icon name="fa fa-refresh" size="14" color="#909399" />
                    <span>刷新</span>
                </div>
            </div>
        </div>

        <!-- 核心指标 -->
        <div class="small-panel-box">
            <el-row :gutter="20">
                <el-col :sm="12" :lg="6">
                    <div class="small-panel user-reg">
                        <div class="small-panel-title">注册用户</div>
                        <div class="small-panel-content">
                            <div class="content-left">
                                <Icon color="#8595F4" size="20" name="fa fa-users" />
                                <span class="num">{{ stats.users_total }}</span>
                            </div>
                            <div class="content-right">今日 +{{ stats.users_today }}</div>
                        </div>
                    </div>
                </el-col>
                <el-col :sm="12" :lg="6">
                    <div class="small-panel file">
                        <div class="small-panel-title">直播间 / 在线推流</div>
                        <div class="small-panel-content">
                            <div class="content-left">
                                <Icon color="#AD85F4" size="20" name="fa fa-video-camera" />
                                <span class="num">{{ stats.rooms_total }}</span>
                            </div>
                            <div class="content-right">
                                <template v-if="stats.rooms_live >= 0">推流 {{ stats.rooms_live }} 路</template>
                                <template v-else>SRS 未知</template>
                            </div>
                        </div>
                    </div>
                </el-col>
                <el-col :sm="12" :lg="6">
                    <div class="small-panel users">
                        <div class="small-panel-title">今日送礼（钻石）</div>
                        <div class="small-panel-content">
                            <div class="content-left">
                                <Icon color="#74A8B5" size="20" name="fa fa-gift" />
                                <span class="num">{{ stats.gift_today_diamonds }}</span>
                            </div>
                            <div class="content-right">{{ stats.gift_today_cnt }} 笔</div>
                        </div>
                    </div>
                </el-col>
                <el-col :sm="12" :lg="6">
                    <div class="small-panel addons">
                        <div class="small-panel-title">今日充值（USDT）</div>
                        <div class="small-panel-content">
                            <div class="content-left">
                                <Icon color="#8D9BFB" size="20" name="fa fa-diamond" />
                                <span class="num">${{ stats.recharge_today_usd }}</span>
                            </div>
                            <div class="content-right">
                                <template v-if="stats.recharge_pending > 0">
                                    <el-tag size="small" type="warning">{{ stats.recharge_pending }} 笔待审核</el-tag>
                                </template>
                                <template v-else>{{ stats.recharge_today_cnt }} 笔</template>
                            </div>
                        </div>
                    </div>
                </el-col>
            </el-row>
        </div>

        <!-- 次级指标 -->
        <div class="sub-stats">
            <span>累计充值：<b>${{ stats.recharge_total_usd }}</b></span>
            <span>累计送礼：<b>{{ stats.gift_total_diamonds }} 钻</b></span>
            <span>用户钱包钻石总量：<b>{{ stats.wallet_diamonds }} 钻</b></span>
        </div>

        <!-- 7日趋势 -->
        <el-card shadow="never" class="chart-card">
            <template #header>近 7 天：送礼钻石 / 充值金额</template>
            <div ref="trendChartRef" class="trend-chart"></div>
        </el-card>

        <!-- 最近记录 -->
        <el-row :gutter="20" class="table-row">
            <el-col :xs="24" :lg="12">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-head">
                            <span>最近充值订单</span>
                            <el-tag v-if="stats.recharge_pending > 0" size="small" type="warning">待审核 {{ stats.recharge_pending }}</el-tag>
                        </div>
                    </template>
                    <el-table :data="latestRecharge" size="small" :show-header="true">
                        <el-table-column prop="order_no" label="订单号" width="180" show-overflow-tooltip />
                        <el-table-column prop="nickname" label="用户" width="90" show-overflow-tooltip />
                        <el-table-column prop="pay_amount" label="USDT" width="80">
                            <template #default="s">${{ s.row.pay_amount }}</template>
                        </el-table-column>
                        <el-table-column prop="diamond_amount" label="钻石" width="70" />
                        <el-table-column label="状态" width="80">
                            <template #default="s">
                                <el-tag size="small" :type="s.row.status == 1 ? 'success' : s.row.status == 0 ? 'warning' : 'info'">
                                    {{ s.row.status_text }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="created_at" label="时间" show-overflow-tooltip />
                    </el-table>
                </el-card>
            </el-col>
            <el-col :xs="24" :lg="12">
                <el-card shadow="never">
                    <template #header>最近送礼</template>
                    <el-table :data="latestGift" size="small" :show-header="true">
                        <el-table-column prop="nickname" label="用户" width="90" show-overflow-tooltip />
                        <el-table-column prop="gift_name" label="礼物" width="110" show-overflow-tooltip />
                        <el-table-column prop="quantity" label="数量" width="60" />
                        <el-table-column label="钻石" width="80">
                            <template #default="s">{{ s.row.total_price }}</template>
                        </el-table-column>
                        <el-table-column prop="room_title" label="直播间" show-overflow-tooltip />
                        <el-table-column prop="created_at" label="时间" width="160" show-overflow-tooltip />
                    </el-table>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup lang="ts">
import * as echarts from 'echarts'
import { nextTick, onActivated, onBeforeMount, onMounted, onUnmounted, reactive, ref } from 'vue'
import { index } from '/@/api/backend/dashboard'
import { getGreet } from '/@/utils/common'
import { useAdminInfo } from '/@/stores/adminInfo'

defineOptions({
    name: 'dashboard',
})

const adminInfo = useAdminInfo()
const trendChartRef = ref<HTMLDivElement>()

const stats = reactive({
    users_total: 0,
    users_today: 0,
    rooms_total: 0,
    rooms_live: -1,
    recharge_today_usd: 0,
    recharge_today_cnt: 0,
    recharge_pending: 0,
    recharge_total_usd: 0,
    gift_today_diamonds: 0,
    gift_today_cnt: 0,
    gift_total_diamonds: 0,
    wallet_diamonds: 0,
})

const trend = ref<any[]>([])
const latestRecharge = ref<any[]>([])
const latestGift = ref<any[]>([])

let chart: echarts.ECharts | null = null

const initTrendChart = () => {
    if (!trendChartRef.value) return
    chart = echarts.init(trendChartRef.value)
    chart.setOption({
        grid: { top: 40, right: 20, bottom: 30, left: 50 },
        tooltip: { trigger: 'axis' },
        legend: { data: ['送礼钻石', '充值USDT'] },
        xAxis: { type: 'category', data: trend.value.map((t) => t.date) },
        yAxis: [
            { type: 'value', name: '钻石' },
            { type: 'value', name: 'USDT' },
        ],
        series: [
            {
                name: '送礼钻石',
                type: 'bar',
                data: trend.value.map((t) => t.gift_diamonds),
                itemStyle: { color: '#8595F4' },
                barMaxWidth: 32,
            },
            {
                name: '充值USDT',
                type: 'line',
                yAxisIndex: 1,
                smooth: true,
                data: trend.value.map((t) => t.recharge_usd),
                itemStyle: { color: '#F56C6C' },
            },
        ],
    })
}

const loadData = () => {
    index().then((res) => {
        const d = res.data
        Object.assign(stats, d.stats)
        trend.value = d.trend ?? []
        latestRecharge.value = d.latest_recharge ?? []
        latestGift.value = d.latest_gift ?? []
        nextTick(() => initTrendChart())
    })
}

const onResize = () => chart && chart.resize()

onBeforeMount(() => {
    loadData()
})

onMounted(() => {
    window.addEventListener('resize', onResize)
})

onActivated(() => {
    loadData()
})

onUnmounted(() => {
    window.removeEventListener('resize', onResize)
    chart && chart.dispose()
})
</script>

<style scoped lang="scss">
.dashboard-main {
    .banner {
        .welcome {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            border-radius: 8px;
            background: var(--el-bg-color-overlay);
            border: 1px solid var(--el-border-color-lighter);
            .welcome-title {
                font-size: 18px;
                font-weight: 600;
            }
            .welcome-note {
                margin-top: 4px;
                font-size: 13px;
                color: var(--el-text-color-secondary);
            }
            .refresh-btn {
                display: flex;
                align-items: center;
                gap: 4px;
                cursor: pointer;
                font-size: 13px;
                color: var(--el-text-color-secondary);
                user-select: none;
                &:hover {
                    color: var(--el-color-primary);
                }
            }
        }
    }
    .small-panel-box {
        margin-top: 15px;
        .small-panel {
            background: var(--el-bg-color-overlay);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid var(--el-border-color-lighter);
            .small-panel-title {
                font-size: 14px;
                color: var(--el-text-color-secondary);
                margin-bottom: 10px;
            }
            .small-panel-content {
                display: flex;
                align-items: center;
                justify-content: space-between;
                .content-left {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    .num {
                        font-size: 26px;
                        font-weight: 600;
                        line-height: 1;
                    }
                }
                .content-right {
                    font-size: 13px;
                    color: var(--el-text-color-secondary);
                }
            }
        }
    }
    .sub-stats {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 28px;
        margin: 5px 0 15px;
        font-size: 13px;
        color: var(--el-text-color-secondary);
        b {
            color: var(--el-text-color-primary);
        }
    }
    .chart-card {
        :deep(.el-card__body) {
            padding: 10px;
        }
        .trend-chart {
            height: 320px;
            width: 100%;
        }
    }
    .table-row {
        margin-top: 15px;
        .card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
    }
}
</style>
