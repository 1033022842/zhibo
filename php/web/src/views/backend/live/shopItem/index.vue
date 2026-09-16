<template>
    <div class="default-main ba-table-box">
        <el-alert
            class="ba-table-alert"
            title="配置 AI 女友端 Candy Shop 商店在售商品，端上商城会读取已上架的商品列表"
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
    name: 'live/shopItem',
})

const baTable = new baTableClass(
    new baTableApi('/admin/live.ShopItem/'),
    {
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: 'ID', prop: 'id', align: 'center', width: 70, operator: '=' },
            { label: '封面', prop: 'cover_url', align: 'center', width: 80, render: 'image', operator: false },
            { label: '商品名称', prop: 'title', align: 'center', operator: 'LIKE', showOverflowTooltip: true },
            { label: '价格(钻石)', prop: 'price', align: 'center', width: 110, operator: 'RANGE' },
            { label: '评分', prop: 'rating', align: 'center', width: 80, operator: 'RANGE' },
            { label: '评价数', prop: 'reviews', align: 'center', width: 80, operator: 'RANGE' },
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
            status: 1,
            weigh: 0,
            price: 0,
            rating: 5,
            reviews: 0,
        },
    }
)

baTable.mount()
baTable.getData()
provide('baTable', baTable)
</script>
