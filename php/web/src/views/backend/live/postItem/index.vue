<template>
    <div class="default-main ba-table-box">
        <el-alert
            class="ba-table-alert"
            title="配置 AI 女友端 Posts 动态，端上动态流会读取已上架的帖子列表"
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
    name: 'live/postItem',
})

const baTable = new baTableClass(
    new baTableApi('/admin/live.PostItem/'),
    {
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: 'ID', prop: 'id', align: 'center', width: 70, operator: '=' },
            { label: '帖子ID', prop: 'post_id', align: 'center', width: 90, operator: 'LIKE' },
            { label: '角色头像', prop: 'character_avatar', align: 'center', width: 90, render: 'image', operator: false },
            { label: '角色名', prop: 'character_name', align: 'center', operator: 'LIKE', showOverflowTooltip: true },
            { label: '点赞数', prop: 'likes', align: 'center', width: 90, operator: 'RANGE' },
            { label: '浏览数', prop: 'views', align: 'center', width: 90, operator: 'RANGE' },
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
            post_id: '',
            status: 1,
            weigh: 0,
            likes: 0,
            views: 0,
        },
    }
)

baTable.mount()
baTable.getData()
provide('baTable', baTable)
</script>
