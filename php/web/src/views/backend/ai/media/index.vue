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
    name: 'ai/media',
})

const baTable = new baTableClass(
    new baTableApi('/admin/ai.Media/'),
    {
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: 'ID', prop: 'id', align: 'center', width: 70, operator: '=' },
            { label: '标题', prop: 'title', align: 'center', operator: 'LIKE', showOverflowTooltip: true },
            { label: '角色ID', prop: 'content_id', align: 'center', width: 90, operator: '=' },
            {
                label: '素材类型',
                prop: 'media_type',
                align: 'center',
                render: 'tag',
                custom: { normal: 'info', special: 'danger' },
                replaceValue: { normal: '普通', special: '特殊(付费)' },
                operator: '=',
            },
            {
                label: '媒体种类',
                prop: 'media_kind',
                align: 'center',
                render: 'tag',
                custom: { video: 'success', voice: 'warning' },
                replaceValue: { video: '视频', voice: '语音' },
                operator: '=',
            },
            {
                label: '场景分类',
                prop: 'scene_type',
                align: 'center',
                render: 'tag',
                custom: { chat: 'info', binge: 'success', affection: 'danger' },
                replaceValue: { chat: '聊天内容', binge: '追剧', affection: '好感度特殊视频' },
                operator: '=',
            },
            { label: '解锁价格', prop: 'unlock_price', align: 'center', width: 100, operator: '=' },
            { label: '触发关键词', prop: 'keywords', align: 'center', operator: 'LIKE', showOverflowTooltip: true },
            { label: '权重', prop: 'weigh', align: 'center', width: 80, operator: '=' },
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
            content_id: 0,
            media_type: 'normal',
            media_kind: 'video',
            scene_type: 'chat',
            unlock_price: 0,
            keywords: '',
            weigh: 0,
            status: 1,
        },
    }
)

baTable.mount()
baTable.getData()
provide('baTable', baTable)
</script>

<style scoped></style>
