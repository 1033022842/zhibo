<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <TableHeader
            :buttons="['refresh', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="'搜索 昵称/ID/编号/邮箱/推广链接/渠道'"
        />

        <div
            style="
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 8px;
                margin-bottom: 10px;
                padding: 10px 12px;
                background: var(--el-fill-color-light);
                border-radius: 6px;
            "
        >
            <span style="color: #606266; font-size: 13px">来源筛选</span>
            <el-select v-model="srcFilter.field" size="small" style="width: 150px">
                <el-option v-for="f in SRC_FIELDS" :key="f.value" :label="f.label" :value="f.value" />
            </el-select>
            <el-input
                v-model="srcFilter.val"
                size="small"
                style="width: 220px"
                placeholder="输入值，如 FB-素材A / fb"
                clearable
                @keyup.enter="onSourceFilterSubmit"
                @clear="clearSourceFilter"
            />
            <el-button size="small" type="primary" @click="onSourceFilterSubmit">筛选</el-button>
            <el-button size="small" @click="clearSourceFilter">清除</el-button>
            <span style="color: #909399; font-size: 12px">
                点列表里的「注册来源」标签可筛同平台、「注册渠道」文字可筛同域名；点 ↗ 看推广数据报表
            </span>
        </div>

        <Table />

        <el-dialog v-model="rechargeState.visible" title="手动充值 / 扣减代币" width="440px" :close-on-click-modal="false">
            <el-form label-width="90px">
                <el-form-item label="用户">
                    <span>{{ rechargeState.nickname }}（ID: {{ rechargeState.userId }}）</span>
                </el-form-item>
                <el-form-item label="当前余额">
                    <span>{{ rechargeState.balance }} 代币</span>
                </el-form-item>
                <el-form-item label="操作类型">
                    <el-radio-group v-model="rechargeState.type">
                        <el-radio value="credit">充值（增加）</el-radio>
                        <el-radio value="debit">扣减（减少）</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="代币数量">
                    <el-input-number v-model="rechargeState.amount" :min="0.01" :precision="2" :step="10" style="width: 180px" />
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model="rechargeState.remark" placeholder="选填，如充值原因" clearable />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="rechargeState.visible = false">{{ t('Cancel') }}</el-button>
                <el-button type="primary" :loading="rechargeState.loading" @click="submitRecharge">{{ t('Confirm') }}</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="vipState.visible" title="开通会员等级包" width="460px" :close-on-click-modal="false">
            <el-form label-width="90px">
                <el-form-item label="用户">
                    <span>{{ vipState.nickname }}（ID: {{ vipState.userId }}）</span>
                </el-form-item>
                <el-form-item label="当前到期">
                    <span>{{ vipState.expireAt || '未开通' }}</span>
                </el-form-item>
                <el-form-item label="等级包">
                    <el-select v-model="vipState.planId" placeholder="请选择" filterable :loading="vipState.plansLoading" style="width: 100%">
                        <el-option
                            v-for="p in vipState.plans"
                            :key="p.id"
                            :label="`${p.name}（${p.months}个月 / ${p.tokens_month} tokens）`"
                            :value="p.id"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <span style="color: #909399; font-size: 12px">后台开通不扣费，到期时间在现有会员基础上顺延。</span>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="vipState.visible = false">{{ t('Cancel') }}</el-button>
                <el-button type="primary" :loading="vipState.loading" @click="submitVip">{{ t('Confirm') }}</el-button>
            </template>
        </el-dialog>
        <el-dialog v-model="detailState.visible" title="用户详情" width="760px" :close-on-click-modal="false" destroy-on-close>
            <iframe v-if="detailState.visible" :src="detailState.url" style="width: 100%; height: 70vh; border: 0" />
        </el-dialog>

        <el-dialog
            v-model="inviteState.visible"
            :title="`下级会员 · ${inviteState.nickname || ''} 邀请来的（${inviteState.list.length} 人）`"
            width="880px"
            destroy-on-close
        >
            <el-table v-loading="inviteState.loading" :data="inviteState.list" size="small" max-height="60vh">
                <el-table-column prop="user_id" label="ID" width="80" align="center" />
                <el-table-column prop="nickname" label="昵称" min-width="120" show-overflow-tooltip />
                <el-table-column prop="user_no" label="编号" width="110" align="center" />
                <el-table-column prop="registered_at" label="注册时间" width="160" align="center" />
                <el-table-column prop="invite_code" label="使用邀请码" width="120" align="center" />
                <el-table-column label="会员" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag size="small" effect="light" :type="row.vip_label === 'Free' ? 'info' : 'primary'">
                            {{ row.vip_label }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="奖励代币" width="100" align="center">
                    <template #default="{ row }">{{ row.reward_tokens || 0 }}</template>
                </el-table-column>
            </el-table>
            <template #footer>
                <el-button @click="inviteState.visible = false">{{ t('Close') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { provide, reactive, h, defineComponent } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElTag, ElTooltip } from 'element-plus'
import baTableClass from '/@/utils/baTable'
import Table from '/@/components/table/index.vue'
import TableHeader from '/@/components/table/header/index.vue'
import { defaultOptButtons } from '/@/components/table'
import { baTableApi } from '/@/api/common'
import { useAdminInfo } from '/@/stores/adminInfo'

defineOptions({
    name: 'user/liveUser',
})

const { t } = useI18n()

const rechargeState = reactive({
    visible: false,
    loading: false,
    userId: 0,
    nickname: '',
    balance: 0,
    type: 'credit',
    amount: 100,
    remark: '',
})

const openRecharge = (row: TableRow) => {
    rechargeState.userId = Number(row.id)
    rechargeState.nickname = String(row.nickname ?? '')
    rechargeState.balance = Number(row.diamond_balance ?? 0)
    rechargeState.type = 'credit'
    rechargeState.amount = 100
    rechargeState.remark = ''
    rechargeState.visible = true
}

const submitRecharge = () => {
    if (!rechargeState.amount || rechargeState.amount <= 0) {
        ElMessage.warning('请输入大于 0 的代币数量')
        return
    }
    rechargeState.loading = true
    baTable.api
        .postData('adjustDiamond', {
            user_id: rechargeState.userId,
            amount: rechargeState.amount,
            type: rechargeState.type,
            remark: rechargeState.remark,
        })
        .then(() => {
            rechargeState.loading = false
            rechargeState.visible = false
            baTable.getData()
        })
        .catch(() => {
            rechargeState.loading = false
        })
}

const vipState = reactive({
    visible: false,
    loading: false,
    plansLoading: false,
    userId: 0,
    nickname: '',
    expireAt: '',
    planId: 0,
    plans: [] as any[],
})

const openVip = (row: TableRow) => {
    vipState.userId = Number(row.id)
    vipState.nickname = String(row.nickname ?? '')
    vipState.expireAt = String(row.vip_expire_at ?? '')
    vipState.planId = 0
    vipState.visible = true
    if (!vipState.plans.length) {
        vipState.plansLoading = true
        baTable.api
            .postData('vipPlans', {})
            .then((res: any) => {
                const payload = res?.data
                vipState.plans = Array.isArray(payload) ? payload : (payload?.data ?? [])
                vipState.plansLoading = false
            })
            .catch(() => {
                vipState.plansLoading = false
            })
    }
}

const submitVip = () => {
    if (!vipState.planId) {
        ElMessage.warning('请选择要开通的等级包')
        return
    }
    vipState.loading = true
    baTable.api
        .postData('grantVip', {
            user_id: vipState.userId,
            plan_id: vipState.planId,
        })
        .then(() => {
            vipState.loading = false
            vipState.visible = false
            baTable.getData()
        })
        .catch(() => {
            vipState.loading = false
        })
}

const adminInfo = useAdminInfo()

// 用户详情（含来源域名/渠道、商家认证审核）：详情页读取 batoken 参数鉴权，iframe 直接打开即可
const detailState = reactive({ visible: false, url: '' })

const openDetail = (row: TableRow) => {
    detailState.url = '/admin/user.LiveUser/detail?id=' + row.id + '&batoken=' + adminInfo.getToken()
    detailState.visible = true
}

// 下级会员（他邀请来的人）：点「下级」数字弹窗查看明细
const inviteState = reactive({ visible: false, loading: false, userId: 0, nickname: '', list: [] as any[] })

const openInvitees = (row: TableRow) => {
    inviteState.userId = Number(row.id)
    inviteState.nickname = String(row.nickname ?? '')
    inviteState.list = []
    inviteState.visible = true
    inviteState.loading = true
    baTable.api
        .postData('invitees', { id: inviteState.userId })
        .then((res: any) => {
            const payload = res?.data
            inviteState.list = Array.isArray(payload) ? payload : (payload?.list ?? payload?.data ?? [])
            inviteState.loading = false
        })
        .catch(() => {
            inviteState.loading = false
        })
}

// ---------------- 来源筛选（顶部筛选器 / 点列表「来源」标签） ----------------
const srcFilter = reactive({ field: 'reg_link_name', val: '' })
const SRC_FIELDS = [
    { label: '推广链接名称', value: 'reg_link_name' },
    { label: '渠道 ch', value: 'reg_channel' },
    { label: '平台', value: 'reg_platform' },
    { label: '来源域名', value: 'reg_domain' },
    { label: '推广像素', value: 'reg_px' },
]

const applySourceFilter = (field: string, val: string, operator = 'LIKE') => {
    const v = (val || '').trim()
    if (!v) {
        baTable.setFilterSearchData([], 'cover')
    } else {
        baTable.setFilterSearchData([{ field, operator, val: v }], 'cover')
    }
    baTable.getData()
}

const onSourceFilterSubmit = () => applySourceFilter(srcFilter.field, srcFilter.val, 'LIKE')

// 点列表「来源」标签：同步顶部筛选条，再用精确匹配筛选
const filterBySourceTag = (field: string, val: string) => {
    if (SRC_FIELDS.some((f) => f.value === field)) {
        srcFilter.field = field
    }
    srcFilter.val = val
    applySourceFilter(field, val, 'eq')
}

const clearSourceFilter = () => {
    srcFilter.field = 'reg_link_name'
    srcFilter.val = ''
    baTable.setFilterSearchData([], 'cover')
    baTable.getData()
}

// 打开「推广数据」报表对应维度（新标签页；同源打开，飘页鉴权可通过）
const openSourceReport = (dim: string) => {
    window.open('/admin/live.PromoSource/index?dim=' + (dim || 'domain'), '_blank')
}

const baTable = new baTableClass(
    new baTableApi('/admin/user.LiveUser/'),
    {
        dblClickNotEditColumn: [undefined],
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: 'ID', prop: 'id', align: 'center', operator: '=', width: 70 },
            { label: '昵称', prop: 'nickname', align: 'center', operator: 'LIKE', width: 130, showOverflowTooltip: true, show: true },
            {
                // 在线/离线：按 lp_user_profile.last_active_at（接口心跳）判定，
                // 窗口是 UserService::ONLINE_WINDOW（5 分钟）
                label: '在线',
                prop: 'online_label',
                align: 'center',
                width: 80,
                operator: false,
                render: 'tag',
                custom: { 在线: 'success', 离线: 'info' },
                show: true,
            },
            { label: '代币余额', prop: 'diamond_balance', align: 'center', width: 110, operator: false },
            {
                // 会员账号：邮箱优先，无邮箱时显示用户名/手机号账号；悬浮显示注册认证方式
                label: '会员账号',
                prop: 'email',
                align: 'center',
                operator: 'LIKE',
                width: 220,
                render: 'customRender',
                customRender: defineComponent({
                    props: { renderRow: Object },
                    setup(props) {
                        return () => {
                            const row: any = props.renderRow
                            const label = row?.account_label || row?.email || ''
                            if (!label) {
                                return h('span', { style: 'color:#c0c4cc' }, '—')
                            }
                            const typeMap: any = { email: '邮箱', username: '用户名', mobile: '手机号' }
                            const typeText = typeMap[row?.auth_type] || ''
                            return h(
                                ElTooltip,
                                { effect: 'dark', placement: 'top', disabled: !typeText },
                                {
                                    default: () => h('span', { style: 'word-break:break-all' }, label),
                                    content: () => h('span', {}, '认证方式：' + typeText),
                                }
                            )
                        }
                    },
                }) as any,
            },
            {
                // 注册来源：平台短名（FB / TT / GG）；点标签=筛选同平台；↗=看推广数据「平台」维度
                label: '注册来源',
                prop: 'reg_source_label',
                align: 'center',
                operator: false,
                width: 120,
                render: 'customRender',
                customRender: defineComponent({
                    props: { renderRow: Object },
                    setup(props) {
                        return () => {
                            const row: any = props.renderRow
                            const label = row?.reg_source_label || ''
                            if (!label) {
                                return h('span', { style: 'color:#c0c4cc' }, '—')
                            }
                            const tag = h(
                                ElTag,
                                {
                                    size: 'small',
                                    effect: 'light',
                                    type: row?.reg_source_type || 'info',
                                    style: row?.reg_platform ? 'cursor:pointer' : '',
                                    onClick: (e: any) => {
                                        e.stopPropagation()
                                        if (row?.reg_platform) {
                                            filterBySourceTag('reg_platform', row.reg_platform)
                                        }
                                    },
                                },
                                () => label
                            )
                            const jump = h(
                                'a',
                                {
                                    href: 'javascript:void(0)',
                                    style: 'margin-left:6px;color:#909399;font-size:13px;text-decoration:none',
                                    onClick: (e: any) => {
                                        e.stopPropagation()
                                        openSourceReport('platform')
                                    },
                                },
                                '↗'
                            )
                            return h('span', { style: 'display:inline-flex;align-items:center' }, [tag, jump])
                        }
                    },
                }) as any,
            },
            {
                // 注册渠道：后台绑定的「渠道名称 · 推广域名」；悬浮看完整明细；点文字=筛选同域名
                label: '注册渠道',
                prop: 'reg_channel_label',
                align: 'center',
                operator: false,
                width: 210,
                render: 'customRender',
                customRender: defineComponent({
                    props: { renderRow: Object },
                    setup(props) {
                        return () => {
                            const row: any = props.renderRow
                            const label = row?.reg_channel_label || ''
                            if (!label) {
                                return h('span', { style: 'color:#c0c4cc' }, '—')
                            }
                            return h(
                                ElTooltip,
                                { effect: 'dark', placement: 'top', disabled: !row?.reg_source_detail },
                                {
                                    default: () =>
                                        h(
                                            'span',
                                            {
                                                style: row?.reg_domain ? 'cursor:pointer' : '',
                                                onClick: (e: any) => {
                                                    e.stopPropagation()
                                                    if (row?.reg_domain) {
                                                        filterBySourceTag('reg_domain', row.reg_domain)
                                                    }
                                                },
                                            },
                                            label
                                        ),
                                    content: () =>
                                        h('div', { style: 'white-space:pre-line;line-height:1.8;max-width:320px' }, [
                                            row?.reg_source_detail || label,
                                            h('div', { style: 'margin-top:6px;color:#a4a4a4' }, '点文字 = 筛选同域名　点 ↗ = 看推广数据'),
                                        ]),
                                }
                            )
                        }
                    },
                }) as any,
            },
            {
                // 上级（邀请人）：谁邀请他注册的；悬浮看邀请人 ID/编号/绑定时间；无记录=自然注册
                label: '上级（邀请人）',
                prop: 'inviter_label',
                align: 'center',
                operator: false,
                width: 140,
                render: 'customRender',
                customRender: defineComponent({
                    props: { renderRow: Object },
                    setup(props) {
                        return () => {
                            const row: any = props.renderRow
                            const label = row?.inviter_label || ''
                            if (!label) {
                                return h('span', { style: 'color:#c0c4cc' }, '自然注册')
                            }
                            return h(
                                ElTooltip,
                                { effect: 'dark', placement: 'top', disabled: !row?.inviter_detail },
                                {
                                    default: () => h('span', { style: 'cursor:pointer' }, label),
                                    content: () =>
                                        h('div', { style: 'white-space:pre-line;line-height:1.8' }, row?.inviter_detail || label),
                                }
                            )
                        }
                    },
                }) as any,
            },
            {
                // 下级（邀请）：他邀请来的人数；悬浮看明细；点数字弹窗列出下级会员
                label: '下级（邀请）',
                prop: 'invite_count',
                align: 'center',
                operator: false,
                width: 120,
                render: 'customRender',
                customRender: defineComponent({
                    props: { renderRow: Object },
                    setup(props) {
                        return () => {
                            const row: any = props.renderRow
                            const n = Number(row?.invite_count || 0)
                            if (!n) {
                                return h('span', { style: 'color:#c0c4cc' }, '—')
                            }
                            return h(
                                ElTooltip,
                                { effect: 'dark', placement: 'top', disabled: !row?.invite_detail },
                                {
                                    default: () =>
                                        h(
                                            ElTag,
                                            {
                                                size: 'small',
                                                effect: 'light',
                                                type: 'success',
                                                style: 'cursor:pointer',
                                                onClick: (e: any) => {
                                                    e.stopPropagation()
                                                    openInvitees(row)
                                                },
                                            },
                                            () => n + ' 人'
                                        ),
                                    content: () =>
                                        h('div', { style: 'white-space:pre-line;line-height:1.8' }, row?.invite_detail || ''),
                                }
                            )
                        }
                    },
                }) as any,
            },
            {
                // 商家认证：未认证/审核中/已通过/已拒绝；有提交时点击标签进入认证审核
                label: '商家认证',
                prop: 'cert_status',
                align: 'center',
                operator: false,
                width: 110,
                render: 'customRender',
                customRender: defineComponent({
                    props: { renderRow: Object },
                    setup(props) {
                        return () => {
                            const row: any = props.renderRow
                            const textMap: any = { '-1': '未认证', '0': '审核中', '1': '已通过', '2': '已拒绝' }
                            const typeMap: any = { '-1': 'info', '0': 'warning', '1': 'success', '2': 'danger' }
                            const key = String(row?.cert_status)
                            const url = row?.cert_review_url || ''
                            return h(
                                ElTag,
                                {
                                    size: 'small',
                                    effect: 'light',
                                    type: typeMap[key] || 'info',
                                    style: url ? 'cursor:pointer' : '',
                                    onClick: (e: any) => {
                                        e.stopPropagation()
                                        if (url) window.open(url, '_blank')
                                    },
                                },
                                () => textMap[key] || '未知'
                            )
                        }
                    },
                }) as any,
            },
            {
                label: '状态',
                prop: 'status',
                align: 'center',
                width: 80,
                render: 'tag',
                replaceValue: { '0': '禁用', '1': '正常' },
                custom: { '0': 'danger', '1': 'success' },
            },
            {
                // 会员身份：Free / Premium / Premium Plus（按生效套餐层级）
                label: '会员身份',
                prop: 'vip_level_label',
                align: 'center',
                operator: false,
                width: 130,
                render: 'customRender',
                customRender: defineComponent({
                    props: { renderRow: Object },
                    setup(props) {
                        return () => {
                            const row: any = props.renderRow
                            const label = row?.vip_level_label || 'Free'
                            return h(
                                ElTag,
                                { size: 'small', effect: 'light', type: row?.vip_level_type || 'info' },
                                () => label
                            )
                        }
                    },
                }) as any,
            },
            { label: '会员到期', prop: 'vip_expire_at', align: 'center', render: 'datetime', width: 160 },
            {
                label: '最后登录',
                prop: 'last_login_at',
                align: 'center',
                render: 'datetime',
                sortable: 'custom',
                operator: 'RANGE',
                width: 160,
            },
            {
                label: '注册时间',
                prop: 'created_at',
                align: 'center',
                render: 'datetime',
                sortable: 'custom',
                operator: 'RANGE',
                width: 160,
                show: true,
            },
            {
                // 登入IP：会员最近一次登录的 IP（来自 lp_user_profile，非主表字段，故不提供筛选）
                label: '登入IP',
                prop: 'last_login_ip',
                align: 'center',
                operator: false,
                width: 150,
                showOverflowTooltip: true,
            },
            {
                label: '操作',
                align: 'center',
                width: 400,
                fixed: 'right',
                render: 'buttons',
                buttons: [
                    {
                        render: 'basicButton',
                        name: 'grantVip',
                        text: '开通会员',
                        type: 'primary',
                        icon: 'fa fa-crown',
                        click: (row: TableRow) => openVip(row),
                    },
                    {
                        render: 'basicButton',
                        name: 'recharge',
                        text: '充值',
                        type: 'warning',
                        icon: 'fa fa-diamond',
                        click: (row: TableRow) => openRecharge(row),
                    },
                    {
                        render: 'basicButton',
                        name: 'detail',
                        text: '详情',
                        type: 'info',
                        icon: 'fa fa-id-card-o',
                        click: (row: TableRow) => openDetail(row),
                    },
                    ...defaultOptButtons(['delete']),
                ],
                operator: false,
            },
        ],
    }
)

baTable.mount()
baTable.getData()
provide('baTable', baTable)
</script>
