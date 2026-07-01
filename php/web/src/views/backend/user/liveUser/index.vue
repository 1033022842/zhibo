<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <TableHeader
            :buttons="['refresh', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="'搜索用户昵称/ID/编号'"
        />

        <Table />
    </div>
</template>

<script setup lang="ts">
import { provide } from 'vue'
import baTableClass from '/@/utils/baTable'
import Table from '/@/components/table/index.vue'
import TableHeader from '/@/components/table/header/index.vue'
import { defaultOptButtons } from '/@/components/table'
import { baTableApi } from '/@/api/common'

defineOptions({
    name: 'user/liveUser',
})

const baTable = new baTableClass(
    new baTableApi('/admin/user.LiveUser/'),
    {
        dblClickNotEditColumn: [undefined],
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: 'ID', prop: 'id', align: 'center', operator: '=', width: 70 },
            { label: '用户编号', prop: 'user_no', align: 'center', operator: 'LIKE' },
            { label: '昵称', prop: 'nickname', align: 'center', operator: 'LIKE', show: true },
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
                width: 100,
                render: 'buttons',
                buttons: defaultOptButtons(['delete']),
                operator: false,
            },
        ],
    }
)

provide('baTable', baTable)
</script>
