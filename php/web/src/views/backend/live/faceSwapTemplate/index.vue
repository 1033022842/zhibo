<template>
    <div class="default-main ba-table-box">
        <el-alert
            class="ba-table-alert"
            title="配置 AI 女友端「上传图片换脸」的固定模板视频，用户在端上选择模板并上传人脸图片后生成换脸视频"
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
    name: 'live/faceSwapTemplate',
})

const baTable = new baTableClass(
    new baTableApi('/admin/live.FaceSwapTemplate/'),
    {
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: 'ID', prop: 'id', align: 'center', width: 70, operator: '=' },
            { label: '模板名称', prop: 'title', align: 'center', operator: 'LIKE', showOverflowTooltip: true },
            { label: '模板视频', prop: 'video_url', align: 'center', operator: 'LIKE', showOverflowTooltip: true },
            { label: '封面图', prop: 'cover_url', align: 'center', operator: 'LIKE', showOverflowTooltip: true },
            { label: '时长(秒)', prop: 'duration_sec', align: 'center', width: 100, operator: 'RANGE' },
            { label: '描述', prop: 'description', align: 'center', operator: 'LIKE', showOverflowTooltip: true },
            { label: '权重', prop: 'weigh', align: 'center', width: 80, operator: 'RANGE' },
            {
                label: '状态',
                prop: 'status',
                align: 'center',
                width: 80,
                render: 'tag',
                custom: { '0': 'danger', '1': 'success' },
                replaceValue: { '0': '禁用', '1': '启用' },
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
            duration_sec: 0,
        },
    }
)

baTable.mount()
baTable.getData()
provide('baTable', baTable)
</script>
