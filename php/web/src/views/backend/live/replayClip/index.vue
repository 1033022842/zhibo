<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" title="管理直播录播切片，上传后可在前端回放" type="info" show-icon />
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
    name: 'live/replayClip',
})

const baTable = new baTableClass(
    new baTableApi('/admin/live.ReplayClip/'),
    {
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: 'ID', prop: 'id', align: 'center', width: 70, operator: '=' },
            { label: '标题', prop: 'title', align: 'center', operator: 'LIKE', showOverflowTooltip: true },
            { label: '视频地址', prop: 'video_url', align: 'center', operator: 'LIKE', showOverflowTooltip: true },
            {
                label: '直播日期',
                prop: 'live_date',
                align: 'center',
                width: 130,
                render: 'datetime',
                operator: 'RANGE',
            },
            {
                label: '时长(秒)',
                prop: 'duration',
                align: 'center',
                width: 100,
                operator: 'RANGE',
            },
            {
                label: '角色',
                prop: 'persona.name',
                align: 'center',
                operator: 'LIKE',
                width: 120,
            },
            {
                label: '房间',
                prop: 'room.room_no',
                align: 'center',
                operator: 'LIKE',
                width: 120,
            },
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
            duration: 0,
            live_date: '',
        },
    }
)

baTable.mount()
baTable.getData()
provide('baTable', baTable)
</script>
