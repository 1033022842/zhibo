<template>
    <div class="default-main dashboard-main">
        <!-- 顶部：欢迎 + 系统时间 -->
        <div class="banner">
            <div class="welcome">
                <div class="welcome-title">{{ adminInfo.nickname + '，' + getGreet() }}</div>
                <div class="welcome-note">运营控制台 · 数据实时来自线上库</div>
            </div>
            <div class="banner-right">
                <span class="sys-time">系统时间 {{ tzText }} <b>{{ clock }}</b></span>
                <div class="refresh-btn" @click="loadData">
                    <Icon name="fa fa-refresh" size="14" color="#909399" />
                    <span>刷新</span>
                </div>
            </div>
        </div>

        <!-- 总览指标 -->
        <div class="big-cards">
            <div class="big-card" v-for="c in bigCards" :key="c.label">
                <div class="big-icon" :style="{ background: c.bg, color: c.color }">
                    <Icon :name="c.icon" size="20" />
                </div>
                <div class="big-info">
                    <div class="big-num">{{ c.value }}</div>
                    <div class="big-label">{{ c.label }}</div>
                    <div class="big-sub"><span v-if="c.live" class="live-dot"></span>{{ c.sub }}</div>
                </div>
            </div>
        </div>

        <!-- 时间筛选 -->
        <div class="filter-bar">
            <span class="filter-label">统计区间</span>
            <el-date-picker
                v-model="query.from"
                type="date"
                value-format="YYYY-MM-DD"
                format="YYYY-MM-DD"
                placeholder="开始日期"
                :clearable="false"
                class="filter-date"
            />
            <span class="filter-sep">至</span>
            <el-date-picker
                v-model="query.to"
                type="date"
                value-format="YYYY-MM-DD"
                format="YYYY-MM-DD"
                placeholder="结束日期"
                :clearable="false"
                class="filter-date"
            />
            <el-button type="primary" :loading="loading" @click="loadData">搜索</el-button>
            <div class="quick-btns">
                <span v-for="q in quickRanges" :key="q.label" class="quick-btn" @click="applyQuick(q.days)">{{ q.label }}</span>
            </div>
        </div>

        <!-- 区间明细指标 -->
        <div class="metric-grid">
            <div class="metric" v-for="m in metrics" :key="m.label">
                <div class="metric-icon" :style="{ background: m.bg, color: m.color }">
                    <Icon :name="m.icon" size="16" />
                </div>
                <div class="metric-info">
                    <div class="metric-num">{{ m.value }}</div>
                    <div class="metric-label">{{ m.label }}</div>
                </div>
            </div>
        </div>

        <!-- 区间接要 -->
        <div class="color-cards">
            <div class="color-card blue">
                <div class="cc-tag">实时</div>
                <div class="cc-title">充值人数</div>
                <div class="cc-num">{{ fmtInt(stats.recharge_period_users) }}</div>
                <div class="cc-sub">首充 {{ fmtInt(stats.recharge_first_users) }} · 复充 {{ fmtInt(stats.recharge_repeat_users) }}</div>
            </div>
            <div class="color-card teal">
                <div class="cc-tag">实时</div>
                <div class="cc-title">主播收益（代币）</div>
                <div class="cc-num">{{ fmtMoney(stats.anchor_income) }}</div>
                <div class="cc-sub">送礼消耗 {{ fmtMoney(stats.gift_period_diamonds) }}</div>
            </div>
            <div class="color-card purple">
                <div class="cc-tag">实时</div>
                <div class="cc-title">用户代币余额</div>
                <div class="cc-num">{{ fmtMoney(stats.wallet_diamonds) }}</div>
                <div class="cc-sub">平台抽成 {{ fmtMoney(stats.gift_period_diamonds - stats.anchor_income) }}</div>
            </div>
            <div class="color-card green">
                <div class="cc-tag">实时</div>
                <div class="cc-title">待打款金额（USDT）</div>
                <div class="cc-num">${{ fmtMoney(stats.withdraw_pending_usd) }}</div>
                <div class="cc-sub">待审提现 {{ fmtInt(stats.withdraw_pending) }} 笔 · 待审充值 {{ fmtInt(stats.recharge_pending) }} 笔</div>
            </div>
        </div>

        <!-- 最近记录 -->
        <el-row :gutter="20" class="table-row">
            <el-col :xs="24" :lg="8">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-head">
                            <span>最近充值订单</span>
                            <el-tag v-if="stats.recharge_pending > 0" size="small" type="warning">{{ stats.recharge_pending }} 笔待审</el-tag>
                        </div>
                    </template>
                    <el-table :data="latestRecharge" size="small" :show-header="true" empty-text="暂无数据">
                        <el-table-column prop="nickname" label="用户" width="90" show-overflow-tooltip />
                        <el-table-column label="USDT" width="90">
                            <template #default="s">
                                <el-tooltip v-if="s.row.fiat_currency" :content="s.row.fiat_amount + ' ' + s.row.fiat_currency + (s.row.fiat_channel ? ' · ' + s.row.fiat_channel : '')" placement="top">
                                    <span class="fiat-amount">${{ fmtMoney(s.row.usd_amount) }}</span>
                                </el-tooltip>
                                <span v-else>${{ fmtMoney(s.row.usd_amount) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="状态" width="80">
                            <template #default="s">
                                <el-tag size="small" :type="s.row.status == 1 ? 'success' : s.row.status == 0 ? 'warning' : 'info'">
                                    {{ s.row.status_text }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="created_at" label="时间" min-width="140" show-overflow-tooltip />
                    </el-table>
                </el-card>
            </el-col>
            <el-col :xs="24" :lg="8">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-head">
                            <span>最近提现</span>
                            <el-tag v-if="stats.withdraw_pending > 0" size="small" type="warning">{{ stats.withdraw_pending }} 笔待审</el-tag>
                        </div>
                    </template>
                    <el-table :data="latestWithdraw" size="small" :show-header="true" empty-text="暂无数据">
                        <el-table-column prop="nickname" label="用户" width="90" show-overflow-tooltip />
                        <el-table-column label="USDT" width="90">
                            <template #default="s">${{ fmtMoney(s.row.actual_usdt) }}</template>
                        </el-table-column>
                        <el-table-column label="状态" width="80">
                            <template #default="s">
                                <el-tag size="small" :type="s.row.status == 1 ? 'success' : s.row.status == 0 ? 'warning' : 'info'">
                                    {{ s.row.status_text }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="created_at" label="时间" min-width="140" show-overflow-tooltip />
                    </el-table>
                </el-card>
            </el-col>
            <el-col :xs="24" :lg="8">
                <el-card shadow="never">
                    <template #header>最近送礼</template>
                    <el-table :data="latestGift" size="small" :show-header="true" empty-text="暂无数据">
                        <el-table-column prop="nickname" label="用户" width="90" show-overflow-tooltip />
                        <el-table-column prop="gift_name" label="礼物" width="90" show-overflow-tooltip />
                        <el-table-column label="代币" width="80">
                            <template #default="s">{{ fmtMoney(s.row.total_price) }}</template>
                        </el-table-column>
                        <el-table-column prop="created_at" label="时间" min-width="140" show-overflow-tooltip />
                    </el-table>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup lang="ts">
import { computed, onActivated, onBeforeMount, onDeactivated, onMounted, onUnmounted, reactive, ref } from 'vue'
import { index } from '/@/api/backend/dashboard'
import { getGreet } from '/@/utils/common'
import { useAdminInfo } from '/@/stores/adminInfo'

defineOptions({
    name: 'dashboard',
})

const adminInfo = useAdminInfo()

const pad = (n: number) => String(n).padStart(2, '0')
const today = () => {
    const d = new Date()
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

const query = reactive({
    from: today(),
    to: today(),
})

const quickRanges = [
    { label: '今日', days: 0 },
    { label: '近 7 天', days: 6 },
    { label: '近 30 天', days: 29 },
]

const stats = reactive({
    users_total: 0,
    users_today: 0,
    users_period: 0,
    online_users: -1,
    rooms_total: 0,
    rooms_live: -1,
    recharge_period_usd: 0,
    recharge_period_cnt: 0,
    recharge_period_users: 0,
    recharge_first_users: 0,
    recharge_repeat_users: 0,
    recharge_total_usd: 0,
    recharge_total_cnt: 0,
    recharge_pending: 0,
    recharge_pending_usd: 0,
    gift_period_diamonds: 0,
    gift_period_cnt: 0,
    gift_period_users: 0,
    gift_total_diamonds: 0,
    gift_total_cnt: 0,
    anchor_income: 0,
    withdraw_period_usd: 0,
    withdraw_period_cnt: 0,
    withdraw_period_users: 0,
    withdraw_total_usd: 0,
    withdraw_pending: 0,
    withdraw_pending_usd: 0,
    wallet_diamonds: 0,
})

const latestRecharge = ref<any[]>([])
const latestWithdraw = ref<any[]>([])
const latestGift = ref<any[]>([])
const loading = ref(false)

const fmtInt = (v: any) => Number(v || 0).toLocaleString('en-US')
const fmtMoney = (v: any) =>
    Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const bigCards = computed(() => [
    {
        label: '总会员数',
        value: fmtInt(stats.users_total),
        sub: `今日 +${fmtInt(stats.users_today)}`,
        icon: 'fa fa-users',
        color: '#4A90E2',
        bg: 'rgba(74, 144, 226, 0.12)',
    },
    {
        label: '在线人数',
        value: stats.online_users >= 0 ? fmtInt(stats.online_users) : '—',
        sub: '近 5 分钟活跃',
        live: true,
        icon: 'fa fa-user-circle',
        color: '#67C23A',
        bg: 'rgba(103, 194, 58, 0.12)',
    },
    {
        label: '累计充值（USDT）',
        value: '$' + fmtMoney(stats.recharge_total_usd),
        sub: `${fmtInt(stats.recharge_total_cnt)} 笔`,
        icon: 'fa fa-diamond',
        color: '#2FB9A8',
        bg: 'rgba(47, 185, 168, 0.12)',
    },
    {
        label: '累计送礼（代币）',
        value: fmtMoney(stats.gift_total_diamonds),
        sub: `${fmtInt(stats.gift_total_cnt)} 笔`,
        icon: 'fa fa-gift',
        color: '#7D64D6',
        bg: 'rgba(125, 100, 214, 0.12)',
    },
    {
        label: '累计提现（USDT）',
        value: '$' + fmtMoney(stats.withdraw_total_usd),
        sub: '已打款金额',
        icon: 'fa fa-credit-card',
        color: '#E6A23C',
        bg: 'rgba(230, 162, 60, 0.12)',
    },
    {
        label: '待处理',
        value: fmtInt(stats.recharge_pending + stats.withdraw_pending),
        sub: `充值 ${fmtInt(stats.recharge_pending)} · 提现 ${fmtInt(stats.withdraw_pending)}`,
        icon: 'fa fa-bell',
        color: '#F56C6C',
        bg: 'rgba(245, 108, 108, 0.12)',
    },
])

const metrics = computed(() => [
    { label: '新增注册', value: fmtInt(stats.users_period), icon: 'fa fa-user-plus', color: '#67C23A', bg: 'rgba(103, 194, 58, 0.12)' },
    {
        label: '在线推流',
        value: stats.rooms_live >= 0 ? fmtInt(stats.rooms_live) : '未知',
        icon: 'fa fa-signal',
        color: '#4A90E2',
        bg: 'rgba(74, 144, 226, 0.12)',
    },
    { label: '充值人数', value: fmtInt(stats.recharge_period_users), icon: 'fa fa-users', color: '#4A90E2', bg: 'rgba(74, 144, 226, 0.12)' },
    { label: '首充人数', value: fmtInt(stats.recharge_first_users), icon: 'fa fa-user', color: '#E6A23C', bg: 'rgba(230, 162, 60, 0.12)' },
    { label: '复充人数', value: fmtInt(stats.recharge_repeat_users), icon: 'fa fa-repeat', color: '#2FB9A8', bg: 'rgba(47, 185, 168, 0.12)' },
    { label: '充值金额', value: '$' + fmtMoney(stats.recharge_period_usd), icon: 'fa fa-dollar', color: '#F56C6C', bg: 'rgba(245, 108, 108, 0.12)' },
    { label: '充值笔数', value: fmtInt(stats.recharge_period_cnt), icon: 'fa fa-list', color: '#909399', bg: 'rgba(144, 147, 153, 0.12)' },
    { label: '送礼人数', value: fmtInt(stats.gift_period_users), icon: 'fa fa-gift', color: '#7D64D6', bg: 'rgba(125, 100, 214, 0.12)' },
    { label: '送礼笔数', value: fmtInt(stats.gift_period_cnt), icon: 'fa fa-shopping-bag', color: '#909399', bg: 'rgba(144, 147, 153, 0.12)' },
    { label: '送礼消耗', value: fmtMoney(stats.gift_period_diamonds), icon: 'fa fa-diamond', color: '#8595F4', bg: 'rgba(133, 149, 244, 0.12)' },
    {
        label: '提现人数',
        value: fmtInt(stats.withdraw_period_users),
        icon: 'fa fa-credit-card',
        color: '#2FB9A8',
        bg: 'rgba(47, 185, 168, 0.12)',
    },
    { label: '提现金额', value: '$' + fmtMoney(stats.withdraw_period_usd), icon: 'fa fa-money', color: '#F56C6C', bg: 'rgba(245, 108, 108, 0.12)' },
])

const loadData = () => {
    loading.value = true
    index({ from: query.from, to: query.to })
        .then((res) => {
            const d = res.data
            Object.assign(stats, d.stats)
            latestRecharge.value = d.latest_recharge ?? []
            latestWithdraw.value = d.latest_withdraw ?? []
            latestGift.value = d.latest_gift ?? []
        })
        .finally(() => {
            loading.value = false
        })
}

const applyQuick = (days: number) => {
    const end = new Date()
    const start = new Date()
    start.setDate(end.getDate() - days)
    const fmt = (d: Date) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
    query.from = fmt(start)
    query.to = fmt(end)
    loadData()
}

// 在线人数：只取在线口径，15 秒轮询一次
const refreshOnline = () => {
    index({ scope: 'online' })
        .then((res) => {
            const n = res.data?.online_users
            if (typeof n === 'number') stats.online_users = n
        })
        .catch(() => {})
}

// 系统时间
const clock = ref('')
const tzText = computed(() => {
    const off = -new Date().getTimezoneOffset()
    return `GMT${off >= 0 ? '+' : '-'}${pad(Math.floor(Math.abs(off) / 60))}:${pad(Math.abs(off) % 60)}`
})
let timer: ReturnType<typeof setInterval> | null = null
let onlineTimer: ReturnType<typeof setInterval> | null = null
const tick = () => {
    const d = new Date()
    clock.value = `${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`
}

const startTimers = () => {
    tick()
    if (!timer) timer = setInterval(tick, 1000)
    if (!onlineTimer) onlineTimer = setInterval(refreshOnline, 15000)
}

const stopTimers = () => {
    if (timer) {
        clearInterval(timer)
        timer = null
    }
    if (onlineTimer) {
        clearInterval(onlineTimer)
        onlineTimer = null
    }
}

let activated = false
onBeforeMount(() => {
    loadData()
})

onMounted(() => {
    startTimers()
})

onActivated(() => {
    if (activated) {
        loadData()
        refreshOnline()
    }
    activated = true
    startTimers()
})

onDeactivated(stopTimers)

onUnmounted(stopTimers)
</script>

<style scoped lang="scss">
.dashboard-main {
    .banner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 16px;
        border-radius: 8px;
        background: var(--el-bg-color-overlay);
        border: 1px solid var(--el-border-color-lighter);
        .welcome-title {
            font-size: 16px;
            font-weight: 600;
        }
        .welcome-note {
            margin-top: 4px;
            font-size: 12px;
            color: var(--el-text-color-secondary);
        }
        .banner-right {
            display: flex;
            align-items: center;
            gap: 18px;
            font-size: 13px;
            color: var(--el-text-color-secondary);
            .sys-time b {
                color: var(--el-text-color-primary);
                font-weight: 600;
            }
            .refresh-btn {
                display: flex;
                align-items: center;
                gap: 4px;
                cursor: pointer;
                user-select: none;
                &:hover {
                    color: var(--el-color-primary);
                }
            }
        }
    }

    .big-cards {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 14px;
        margin-top: 14px;
        .big-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            border-radius: 8px;
            background: var(--el-bg-color-overlay);
            border: 1px solid var(--el-border-color-lighter);
            .big-icon {
                flex-shrink: 0;
                width: 40px;
                height: 40px;
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .big-info {
                min-width: 0;
                .big-num {
                    font-size: 22px;
                    font-weight: 700;
                    line-height: 1.2;
                    word-break: break-all;
                }
                .big-label {
                    margin-top: 2px;
                    font-size: 12px;
                    color: var(--el-text-color-secondary);
                }
                .big-sub {
                    margin-top: 2px;
                    font-size: 12px;
                    color: var(--el-text-color-placeholder);
                    .live-dot {
                        display: inline-block;
                        width: 6px;
                        height: 6px;
                        margin-right: 4px;
                        border-radius: 50%;
                        background: #67c23a;
                        vertical-align: middle;
                        animation: livePulse 1.6s ease-in-out infinite;
                    }
                }
            }
        }
    }

    .filter-bar {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 14px;
        padding: 12px 16px;
        border-radius: 8px;
        background: var(--el-bg-color-overlay);
        border: 1px solid var(--el-border-color-lighter);
        font-size: 13px;
        .filter-label {
            color: var(--el-text-color-secondary);
        }
        .filter-sep {
            color: var(--el-text-color-secondary);
        }
        .filter-date {
            width: 150px;
        }
        .quick-btns {
            display: flex;
            gap: 8px;
            margin-left: 4px;
            .quick-btn {
                padding: 4px 10px;
                border-radius: 4px;
                cursor: pointer;
                color: var(--el-text-color-secondary);
                background: var(--el-fill-color-light);
                user-select: none;
                &:hover {
                    color: var(--el-color-primary);
                }
            }
        }
    }

    .metric-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 12px 14px;
        margin-top: 14px;
        padding: 16px;
        border-radius: 8px;
        background: var(--el-bg-color-overlay);
        border: 1px solid var(--el-border-color-lighter);
        .metric {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
            .metric-icon {
                flex-shrink: 0;
                width: 30px;
                height: 30px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .metric-info {
                min-width: 0;
                .metric-num {
                    font-size: 17px;
                    font-weight: 700;
                    line-height: 1.2;
                    word-break: break-all;
                }
                .metric-label {
                    font-size: 12px;
                    color: var(--el-text-color-secondary);
                }
            }
        }
    }

    .color-cards {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        margin-top: 14px;
        .color-card {
            position: relative;
            padding: 16px;
            border-radius: 8px;
            color: #fff;
            overflow: hidden;
            &.blue {
                background: linear-gradient(135deg, #4a90e2, #3d7bef);
            }
            &.teal {
                background: linear-gradient(135deg, #2fb9a8, #23a596);
            }
            &.purple {
                background: linear-gradient(135deg, #7d64d6, #6c52c4);
            }
            &.green {
                background: linear-gradient(135deg, #24b47e, #16a06c);
            }
            .cc-tag {
                position: absolute;
                top: 10px;
                right: 10px;
                font-size: 11px;
                padding: 1px 6px;
                border-radius: 3px;
                background: rgba(255, 255, 255, 0.24);
            }
            .cc-title {
                font-size: 13px;
                opacity: 0.9;
            }
            .cc-num {
                margin-top: 8px;
                font-size: 26px;
                font-weight: 700;
                line-height: 1.2;
                word-break: break-all;
            }
            .cc-sub {
                margin-top: 6px;
                font-size: 12px;
                opacity: 0.85;
            }
        }
    }

    .table-row {
        margin-top: 14px;
        .card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .fiat-amount {
            color: var(--el-color-primary);
            font-weight: 600;
            cursor: default;
        }
    }
}

@keyframes livePulse {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.25;
    }
}

@media (max-width: 1400px) {
    .dashboard-main {
        .big-cards {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        .metric-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
        .color-cards {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
}

@media (max-width: 900px) {
    .dashboard-main {
        .big-cards {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .metric-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .color-cards {
            grid-template-columns: minmax(0, 1fr);
        }
        .banner {
            flex-direction: column;
            align-items: flex-start;
        }
    }
}
</style>
