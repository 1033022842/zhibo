<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" title="管理定时维护任务，到期自动通过 Telegram 发送提醒" type="info" show-icon />
        <TableHeader :buttons="['refresh', 'add', 'edit', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']" />
        <Table />
        <PopupForm />
    </div>
</template>

<script setup lang="ts">
import { provide } from 'vue'
import baTableClass from '/@/utils/baTable'
import PopupForm from './popupForm.vue'
import Table from '/@/components/table/index.vue'
import TableHeader from '/@/components/table/header/index.vue'
import { defaultOptButtons } from '/@/components/table'
import { baTableApi } from '/@/api/common'

defineOptions({
    name: 'live/maintenanceTask',
})

const baTable = new baTableClass(
    new baTableApi('/admin/live.MaintenanceTask/'),
    {
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: 'ID', prop: 'id', align: 'center', width: 70, operator: '=' },
            { label: '任务名称', prop: 'name', align: 'center', operator: 'LIKE', showOverflowTooltip: true },
            {
                label: '到期日期',
                prop: 'due_date',
                align: 'center',
                width: 130,
                render: 'datetime',
                operator: 'RANGE',
            },
            {
                label: '备注',
                prop: 'remark',
                align: 'center',
                showOverflowTooltip: true,
            },
            {
                label: '重复提醒',
                prop: 'repeat_remind',
                align: 'center',
                width: 90,
                render: 'tag',
                custom: { '0': 'info', '1': 'warning' },
                replaceValue: { '0': '关闭', '1': '开启' },
            },
            {
                label: '状态',
                prop: 'status',
                align: 'center',
                width: 90,
                render: 'tag',
                custom: { '0': 'info', '1': 'success', '2': 'danger' },
                replaceValue: { '0': '待通知', '1': '已通知', '2': '已关闭' },
            },
            {
                label: '上次通知',
                prop: 'last_notify_at',
                align: 'center',
                width: 160,
                render: 'datetime',
                operator: false,
            },
            {
                label: '操作',
                align: 'center',
                width: 140,
                render: 'buttons',
                buttons: defaultOptButtons(['edit', 'delete']),
                operator: false,
            },
        ],
        dblClickNotEditColumn: [undefined],
    },
    {
        defaultItems: {
            repeat_remind: 0,
            status: 0,
            due_date: '',
        },
    }
)

baTable.mount()
baTable.getData()
provide('baTable', baTable)
</script>
