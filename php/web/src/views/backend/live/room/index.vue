<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" title="保存房间时会自动创建或更新播单，并绑定所选素材" type="info" show-icon />
        <TableHeader :buttons="['refresh', 'add', 'edit', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']" />
        <Table />
        <PopupForm />
    </div>
</template>

<script setup lang="ts">
import { provide, h, defineComponent, reactive } from 'vue'
import baTableClass from '/@/utils/baTable'
import PopupForm from './popupForm.vue'
import Table from '/@/components/table/index.vue'
import TableHeader from '/@/components/table/header/index.vue'
import { defaultOptButtons } from '/@/components/table'
import { baTableApi } from '/@/api/common'
import { ElButton, ElMessage, ElTooltip } from 'element-plus'
import createAxios from '/@/utils/axios'

defineOptions({
    name: 'live/room',
})

// 追踪各房间推流操作的 loading 状态
const streamLoading = reactive(new Map<number, boolean>())

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
                prop: 'stream_running',
                align: 'center',
                width: 90,
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
                        const running = () => row?.stream_running === true
                        const loading = () => streamLoading.get(row?.id) || false
                        const hasPlaylist = () => row?.has_playlist !== false

                        const toggleStream = async () => {
                            if (streamLoading.get(row?.id)) return
                            const action = running() ? 'stopStream' : 'startStream'
                            streamLoading.set(row?.id, true)
                            try {
                                const res = await createAxios({
                                    url: '/admin/live.Room/' + action,
                                    method: 'POST',
                                    data: { id: row.id },
                                })
                                // BA 的 reductDataFormat 已解包，res 即 response.data
                                if (res.code === 1) {
                                    ElMessage.success(res.msg)
                                    baTable.getData()
                                } else {
                                    ElMessage.error(res.msg || '操作失败')
                                }
                            } catch {
                                ElMessage.error('请求失败')
                            } finally {
                                streamLoading.set(row?.id, false)
                            }
                        }

                        return () => {
                            const btn = h(
                                ElButton,
                                {
                                    type: running() ? 'danger' : 'success',
                                    size: 'small',
                                    loading: loading(),
                                    disabled: loading() || !hasPlaylist(),
                                    onClick: toggleStream,
                                },
                                () => (running() ? '关播' : '开播')
                            )

                            if (!hasPlaylist()) {
                                return h(
                                    ElTooltip,
                                    { content: '未配置人设，无法推流', placement: 'top' },
                                    () => btn
                                )
                            }
                            return btn
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
</script>
