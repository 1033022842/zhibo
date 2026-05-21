<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" title="管理房间礼物配置，前台房间页会读取已启用的礼物列表" type="info" show-icon />
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
    name: 'live/gift',
})

const baTable = new baTableClass(
    new baTableApi('/admin/live.Gift/'),
    {
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: 'ID', prop: 'id', align: 'center', width: 70, operator: '=' },
            { label: '礼物编码', prop: 'gift_code', align: 'center', operator: 'LIKE' },
            { label: '礼物名称', prop: 'name', align: 'center', operator: 'LIKE' },
            { label: '钻石价格', prop: 'price_diamond', align: 'center', operator: 'RANGE' },
            {
                label: '触发模式',
                prop: 'trigger_mode',
                align: 'center',
                render: 'tag',
                custom: { none: 'info', privilege: 'warning', interaction: 'success' },
                replaceValue: { none: '普通礼物', privilege: '特权触发', interaction: '互动触发' },
            },
            { label: '触发时长(秒)', prop: 'trigger_duration_sec', align: 'center', operator: 'RANGE' },
            { label: '特效编码', prop: 'effect_code', align: 'center', operator: 'LIKE', showOverflowTooltip: true },
            {
                label: '状态',
                prop: 'status',
                align: 'center',
                render: 'tag',
                custom: { '0': 'danger', '1': 'success' },
                replaceValue: { '0': '禁用', '1': '启用' },
            },
            {
                label: '操作',
                align: 'center',
                width: 120,
                render: 'buttons',
                buttons: defaultOptButtons(['edit', 'delete']),
                operator: false,
            },
        ],
        dblClickNotEditColumn: [undefined],
    },
    {
        defaultItems: {
            trigger_mode: 'none',
            trigger_duration_sec: 0,
            effect_code: '',
            status: 1,
        },
    }
)

baTable.mount()
baTable.getData()
provide('baTable', baTable)
</script>
