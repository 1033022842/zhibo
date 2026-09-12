<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <TableHeader
            :buttons="['refresh', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="'搜索用户昵称/ID/编号'"
        />

        <Table />

        <el-dialog v-model="rechargeState.visible" title="手动充值 / 扣减钻石" width="440px" :close-on-click-modal="false">
            <el-form label-width="90px">
                <el-form-item label="用户">
                    <span>{{ rechargeState.nickname }}（ID: {{ rechargeState.userId }}）</span>
                </el-form-item>
                <el-form-item label="当前余额">
                    <span>{{ rechargeState.balance }} 钻</span>
                </el-form-item>
                <el-form-item label="操作类型">
                    <el-radio-group v-model="rechargeState.type">
                        <el-radio value="credit">充值（增加）</el-radio>
                        <el-radio value="debit">扣减（减少）</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="钻石数量">
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
    </div>
</template>

<script setup lang="ts">
import { provide, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import baTableClass from '/@/utils/baTable'
import Table from '/@/components/table/index.vue'
import TableHeader from '/@/components/table/header/index.vue'
import { defaultOptButtons } from '/@/components/table'
import { baTableApi } from '/@/api/common'

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
        ElMessage.warning('请输入大于 0 的钻石数量')
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

const baTable = new baTableClass(
    new baTableApi('/admin/user.LiveUser/'),
    {
        dblClickNotEditColumn: [undefined],
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: 'ID', prop: 'id', align: 'center', operator: '=', width: 70 },
            { label: '用户编号', prop: 'user_no', align: 'center', operator: 'LIKE' },
            { label: '昵称', prop: 'nickname', align: 'center', operator: 'LIKE', show: true },
            { label: '钻石余额', prop: 'diamond_balance', align: 'center', width: 110, operator: false },
            { label: '邮箱', prop: 'email', align: 'center', operator: 'LIKE', render: 'tag' },
            {
                label: '认证方式',
                prop: 'auth_type',
                align: 'center',
                render: 'tag',
                replaceValue: { 'email': '邮箱', 'username': '用户名', 'mobile': '手机号' },
                custom: { 'email': 'success', 'username': 'info', 'mobile': 'warning' },
            },
            { label: '手机号', prop: 'auth_account', align: 'center', operator: 'LIKE', render: 'tag' },
            {
                label: '商家认证',
                prop: 'cert_status',
                align: 'center',
                width: 100,
                render: 'tag',
                replaceValue: { '-1': '未认证', '0': '审核中', '1': '已通过', '2': '已拒绝' },
                custom: { '-1': 'info', '0': 'warning', '1': 'success', '2': 'danger' },
            },
            {
                label: '审核认证',
                prop: 'cert_review_url',
                align: 'center',
                width: 100,
                render: 'tag',
                replaceValue: { '': '-' },
            },
            {
                label: '状态',
                prop: 'status',
                align: 'center',
                render: 'tag',
                replaceValue: { '0': '禁用', '1': '正常' },
                custom: { '0': 'danger', '1': 'success' },
            },
            { label: '等级', prop: 'level', align: 'center', operator: '=', width: 70 },
            { label: '最后登录IP', prop: 'last_login_ip', align: 'center', operator: 'LIKE', render: 'tag' },
            {
                label: '最后登录时间',
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
                label: '操作',
                align: 'center',
                width: 220,
                render: 'buttons',
                buttons: [
                    {
                        render: 'basicButton',
                        name: 'recharge',
                        text: '充值',
                        type: 'warning',
                        icon: 'fa fa-diamond',
                        click: (row: TableRow) => openRecharge(row),
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
