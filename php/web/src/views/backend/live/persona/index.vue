<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" title="管理直播人设，供房间配置和前台展示复用" type="info" show-icon />
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
    name: 'live/persona',
})

const baTable = new baTableClass(
    new baTableApi('/admin/live.Persona/'),
    {
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: 'ID', prop: 'id', align: 'center', width: 70, operator: '=' },
            { label: '人设编码', prop: 'code', align: 'center', operator: 'LIKE' },
            { label: '人设名称', prop: 'name', align: 'center', operator: 'LIKE' },
            { label: '标签', prop: 'tags', align: 'center', operator: 'LIKE', showOverflowTooltip: true },
            { label: '封面', prop: 'cover_url', align: 'center', render: 'image', operator: false },
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
            status: 1,
            cover_url: '',
            tags: '',
        },
    }
)

baTable.mount()
baTable.getData()
provide('baTable', baTable)
</script>
