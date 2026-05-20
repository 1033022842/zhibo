<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" title="上传视频后会进入素材池，房间可直接绑定使用" type="info" show-icon />
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
    name: 'live/mediaAsset',
})

const baTable = new baTableClass(
    new baTableApi('/admin/live.MediaAsset/'),
    {
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: 'ID', prop: 'id', align: 'center', width: 70, operator: '=' },
            { label: '素材编码', prop: 'asset_code', align: 'center', operator: 'LIKE' },
            { label: '标题', prop: 'title', align: 'center', operator: 'LIKE', showOverflowTooltip: true },
            { label: '素材类型', prop: 'asset_type', align: 'center', render: 'tag', operator: '=' },
            { label: '场景', prop: 'scene_type', align: 'center', render: 'tag', operator: '=' },
            { label: '文件路径', prop: 'file_url', align: 'center', operator: 'LIKE', showOverflowTooltip: true },
            { label: '时长(ms)', prop: 'duration_ms', align: 'center', operator: 'RANGE' },
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
            asset_type: 'video',
            scene_type: 'public',
            duration_ms: 0,
            status: 1,
            checksum: '',
        },
    }
)

baTable.mount()
baTable.getData()
provide('baTable', baTable)
</script>
