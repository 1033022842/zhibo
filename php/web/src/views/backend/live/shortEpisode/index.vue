<template>
    <div class="default-main ba-table-box">
        <el-alert
            class="ba-table-alert"
            title="配置短剧剧集：一部剧下可挂多集，每集单独定价、单独解锁；短剧卡片上的「前N集免费」会让集号 ≤ N 的集免钻"
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
    name: 'live/shortEpisode',
})

const baTable = new baTableClass(
    new baTableApi('/admin/live.ShortEpisode/'),
    {
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: 'ID', prop: 'id', align: 'center', width: 70, operator: '=' },
            { label: '所属短剧', prop: 'short_title', align: 'center', showOverflowTooltip: true, operator: false },
            { label: '短剧ID', prop: 'short_id', align: 'center', width: 90, operator: '=' },
            { label: '集号', prop: 'episode_no', align: 'center', width: 80, operator: 'RANGE', sortable: 'custom' },
            { label: '本集标题', prop: 'title', align: 'center', showOverflowTooltip: true, operator: 'LIKE' },
            { label: '封面', prop: 'poster', align: 'center', width: 80, render: 'image', operator: false },
            { label: '价格(钻石)', prop: 'price', align: 'center', width: 110, operator: 'RANGE' },
            {
                label: '免钻',
                prop: 'free_by_series',
                align: 'center',
                width: 80,
                render: 'tag',
                custom: { '0': 'info', '1': 'success' },
                replaceValue: { '0': '否', '1': '前N集免费' },
                operator: false,
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
                label: '更新时间',
                prop: 'updated_at',
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
            short_id: 0,
            episode_no: 1,
            poster: '',
            duration: '',
            price: 0,
            weigh: 0,
            status: 1,
        },
    }
)

baTable.mount()
baTable.getData()
provide('baTable', baTable)
</script>
