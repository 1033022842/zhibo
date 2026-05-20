<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" title="保存房间时会自动创建或更新播单，并绑定所选素材" type="info" show-icon />
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
    name: 'live/room',
})

const baTable = new baTableClass(
    new baTableApi('/admin/live.Room/'),
    {
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: 'ID', prop: 'id', align: 'center', width: 70, operator: '=' },
            { label: '房间号', prop: 'room_no', align: 'center', operator: 'LIKE' },
            { label: '标题', prop: 'title', align: 'center', operator: 'LIKE' },
            { label: '副标题', prop: 'subtitle', align: 'center', operator: 'LIKE', showOverflowTooltip: true },
            { label: '人设', prop: 'persona.name', align: 'center', operator: 'LIKE' },
            { label: '标签', prop: 'tag_names', align: 'center', operator: false, showOverflowTooltip: true },
            { label: '封面', prop: 'cover_url', align: 'center', render: 'image', operator: false },
            { label: '播单', prop: 'playlist_name', align: 'center', operator: false, showOverflowTooltip: true },
            { label: '排序', prop: 'sort', align: 'center', operator: 'RANGE', sortable: 'custom' },
            {
                label: '状态',
                prop: 'status',
                align: 'center',
                render: 'tag',
                custom: { '0': 'danger', '1': 'success', '2': 'warning' },
                replaceValue: { '0': '关闭', '1': '启用', '2': '维护' },
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
        defaultOrder: { prop: 'sort', order: 'desc' },
        dblClickNotEditColumn: [undefined],
    },
    {
        defaultItems: {
            room_type: 'live',
            status: 1,
            sort: 0,
            tag_names: '',
            asset_ids: [],
        },
    }
)

baTable.mount()
baTable.getData()
provide('baTable', baTable)
</script>
