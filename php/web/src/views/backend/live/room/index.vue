<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" title="保存房间时会自动创建或更新播单，并绑定所选素材" type="info" show-icon />
        <TableHeader :buttons="['refresh', 'add', 'edit', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']" />
        <Table />
        <PopupForm />
    </div>
</template>

<script setup lang="ts">
import { provide, h, defineComponent, onMounted, onUnmounted } from 'vue'
import baTableClass from '/@/utils/baTable'
import PopupForm from './popupForm.vue'
import Table from '/@/components/table/index.vue'
import TableHeader from '/@/components/table/header/index.vue'
import { defaultOptButtons } from '/@/components/table'
import { baTableApi } from '/@/api/common'
import { ElTag } from 'element-plus'

defineOptions({
    name: 'live/room',
})

// 状态灯自动刷新间隔（秒）。推流由 AI 电脑负责，后台只做监控，
// 定时刷新让状态灯实时反映 AI 电脑的推流情况（上线/断开自动更新）。
const STREAM_REFRESH_INTERVAL = 20
let refreshTimer: ReturnType<typeof setInterval> | null = null

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
                label: '推流',
                prop: 'stream_state',
                align: 'center',
                width: 130,
                render: 'customRender',
                customRender: defineComponent({
                    props: {
                        renderRow: Object,
                        renderField: Object,
                        renderValue: [Boolean, String],
                        renderColumn: Object,
                        renderIndex: Number,
                    },
                    emits: [],
                    setup(props) {
                        const row = props.renderRow as any
                        return () => {
                            // 状态灯（只读监控）：
                            //   public_live → 绿「推流中」（SRS 有流）
                            //   abnormal    → 黄「异常」（DB 标记推流但 SRS 无流，AI 电脑未推流）
                            //   其他        → 灰「未推流」
                            const state = row?.stream_state
                            let type: 'success' | 'warning' | 'info' = 'info'
                            let text = '未推流'
                            if (state === 'public_live') {
                                type = 'success'
                                text = '推流中'
                            } else if (state === 'abnormal') {
                                type = 'warning'
                                text = '异常'
                            }
                            return h(ElTag, { type, size: 'small', effect: 'light' }, () => text)
                        }
                    },
                }) as any,
                operator: false,
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

// 定时刷新推流状态灯（只读监控 AI 电脑推流情况）
onMounted(() => {
    refreshTimer = setInterval(() => {
        baTable.getData()
    }, STREAM_REFRESH_INTERVAL * 1000)
})
onUnmounted(() => {
    if (refreshTimer) {
        clearInterval(refreshTimer)
        refreshTimer = null
    }
})
</script>
