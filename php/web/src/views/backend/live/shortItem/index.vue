<template>
    <div class="default-main ba-table-box">
        <el-alert
            class="ba-table-alert"
            title="配置 AI 女友端 Candy Shorts 短剧卡片，端上短剧页会按分区读取已上架的卡片"
            type="info"
            show-icon
        />
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
    name: 'live/shortItem',
})

const baTable = new baTableClass(
    new baTableApi('/admin/live.ShortItem/'),
    {
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: 'ID', prop: 'id', align: 'center', width: 70, operator: '=' },
            { label: '封面', prop: 'poster', align: 'center', width: 80, render: 'image', operator: false },
            { label: '标题', prop: 'title', align: 'center', operator: 'LIKE', showOverflowTooltip: true },
            {
                label: '分区',
                prop: 'section',
                align: 'center',
                width: 150,
                render: 'tag',
                replaceValue: {
                    continue_watching: 'Continue watching',
                    top_series: 'Top series',
                    explore: 'Explore',
                },
                operator: '=',
            },
            { label: '名次', prop: 'rank', align: 'center', width: 80, operator: 'RANGE' },
            { label: '进度%', prop: 'progress', align: 'center', width: 90, operator: 'RANGE' },
            {
                label: 'SPICY',
                prop: 'spicy',
                align: 'center',
                width: 80,
                render: 'tag',
                custom: { '0': 'info', '1': 'danger' },
                replaceValue: { '0': '否', '1': '是' },
            },
            {
                label: '高亮变体',
                prop: 'featured',
                align: 'center',
                width: 110,
                replaceValue: { '': '无', ring: 'ring', gradient: 'gradient' },
            },
            { label: '权重', prop: 'weigh', align: 'center', width: 80, operator: 'RANGE' },
            {
                label: '状态',
                prop: 'status',
                align: 'center',
                width: 80,
                render: 'tag',
                custom: { '0': 'danger', '1': 'success' },
                replaceValue: { '0': '下架', '1': '上架' },
            },
            {
                label: '创建时间',
                prop: 'created_at',
                align: 'center',
                width: 160,
                render: 'datetime',
                operator: 'RANGE',
                sortable: 'custom',
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
            section: 'explore',
            rank: 0,
            progress: 0,
            spicy: 0,
            featured: '',
            new_episodes: 0,
            weigh: 0,
            status: 1,
        },
    }
)

baTable.mount()
baTable.getData()
provide('baTable', baTable)
</script>
