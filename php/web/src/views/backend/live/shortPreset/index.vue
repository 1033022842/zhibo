<template>
    <div class="default-main ba-table-box">
        <el-alert
            class="ba-table-alert"
            title="AI 工作室卡片管理：设置每张卡的代币价格（-1=跟随站点全局单价，0=免费，>0=固定价）、上下架与排序。生成参数不在此处修改"
            type="info"
            show-icon
        />
        <TableHeader :buttons="['refresh', 'comSearch', 'quickSearch', 'columnDisplay']" />
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
import { baTableApi } from '/@/api/common'

defineOptions({
    name: 'live/shortPreset',
})

const baTable = new baTableClass(
    new baTableApi('/admin/live.ShortPreset/'),
    {
        column: [
            { label: 'ID', prop: 'id', align: 'center', width: 70, operator: '=' },
            { label: '封面', prop: 'cover_url', align: 'center', width: 80, render: 'image', operator: false },
            { label: '卡片代码', prop: 'code', align: 'center', width: 100, operator: 'LIKE' },
            { label: '标题', prop: 'title', align: 'center', operator: 'LIKE', showOverflowTooltip: true },
            {
                label: '类型',
                prop: 'kind',
                align: 'center',
                width: 90,
                render: 'tag',
                custom: { video: '', image: 'warning' },
                replaceValue: { video: '视频', image: '图片' },
                operator: '=',
            },
            { label: '时长(秒)', prop: 'duration_sec', align: 'center', width: 90, operator: false },
            {
                label: '代币价',
                prop: 'price_tokens',
                align: 'center',
                width: 110,
                render: 'tag',
                custom: { '-1': 'info' },
                replaceValue: { '-1': '跟随全局' },
                operator: 'RANGE',
            },
            { label: '权重', prop: 'weigh', align: 'center', width: 80, operator: false },
            {
                label: '状态',
                prop: 'status',
                align: 'center',
                width: 80,
                render: 'tag',
                custom: { '0': 'danger', '1': 'success' },
                replaceValue: { '0': '下架', '1': '启用' },
                operator: '=',
            },
            {
                label: '创建时间',
                prop: 'created_at',
                align: 'center',
                width: 160,
                render: 'datetime',
                operator: false,
            },
            {
                label: '操作',
                align: 'center',
                width: 100,
                render: 'buttons',
                buttons: defaultOptButtons(['edit']),
                operator: false,
            },
        ],
        dblClickNotEditColumn: [undefined],
    },
    {}
)

baTable.mount()
baTable.getData()
provide('baTable', baTable)
</script>
