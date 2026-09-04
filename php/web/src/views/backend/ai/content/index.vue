<template>
    <div class="default-main ba-table-box">
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
    name: 'ai/content',
})

const baTable = new baTableClass(
    new baTableApi('/admin/ai.Content/'),
    {
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: 'ID', prop: 'id', align: 'center', width: 70, operator: '=' },
            { label: '角色名', prop: 'title', align: 'center', operator: 'LIKE', showOverflowTooltip: true },
            { label: '分类', prop: 'category', align: 'center', operator: 'LIKE', showOverflowTooltip: true },
            { label: '封面', prop: 'cover_url', align: 'center', render: 'image', operator: false, width: 90 },
            { label: '权重', prop: 'weigh', align: 'center', width: 80, operator: '=' },
            {
                label: '公开',
                prop: 'is_public',
                align: 'center',
                width: 80,
                render: 'tag',
                custom: { '0': 'danger', '1': 'success' },
                replaceValue: { '0': '隐藏', '1': '公开' },
                operator: '=',
            },
            {
                label: '状态',
                prop: 'status',
                align: 'center',
                width: 90,
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
            category: '',
            weigh: 0,
            is_public: 1,
            status: 1,
        },
    }
)

baTable.mount()
baTable.getData()
provide('baTable', baTable)
</script>

<style scoped></style>
