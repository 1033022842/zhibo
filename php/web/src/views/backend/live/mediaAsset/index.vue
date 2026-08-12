<template>
    <div class="default-main ba-table-box">
        <el-tabs v-model="activeMachine" @tab-change="onMachineChange" class="machine-tabs">
            <el-tab-pane label="全部素材" name="" />
            <el-tab-pane
                v-for="m in machines"
                :key="m.machine_id"
                :label="m.machine_id + ' (' + m.cnt + ')'"
                :name="m.machine_id"
            />
        </el-tabs>
        <TableHeader :buttons="['refresh', 'add', 'edit', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']" />
        <Table />
        <PopupForm />
    </div>
</template>

<script setup lang="ts">
import { provide, ref, onMounted } from 'vue'
import baTableClass from '/@/utils/baTable'
import PopupForm from './popupForm.vue'
import Table from '/@/components/table/index.vue'
import TableHeader from '/@/components/table/header/index.vue'
import { defaultOptButtons } from '/@/components/table'
import { baTableApi } from '/@/api/common'
import createAxios from '/@/utils/axios'

defineOptions({
    name: 'live/mediaAsset',
})

// tab 切换：按 machine_id（AI电脑）筛选素材
const activeMachine = ref('')
const machines = ref<{ machine_id: string; persona: string; cnt: number }[]>([])

const loadMachines = async () => {
    const res = await createAxios({
        url: '/admin/live.MediaAsset/machines',
        method: 'GET',
    })
    // createAxios reductDataFormat:true 返回 response.data = {code,msg,data}
    const data = res?.data ?? res
    if (data && data.list) {
        machines.value = data.list
    }
}

const onMachineChange = () => {
    // 设置过滤条件并刷新
    if (activeMachine.value === '') {
        // 全部：清掉 machine_id 过滤
        baTable.setFilterSearchData([], 'cover')
    } else {
        baTable.setFilterSearchData([{ field: 'machine_id', operator: 'eq', val: activeMachine.value }], 'cover')
    }
    baTable.getData()
}

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
            { label: '人设', prop: 'persona', align: 'center', operator: 'LIKE' },
            {
                label: '来源',
                prop: 'source',
                align: 'center',
                width: 120,
                render: 'tag',
                custom: { 'admin': 'info', 'machine': 'success' },
                replaceValue: { 'admin': '后台上传', 'machine': 'AI电脑' },
                operator: '=',
            },
            { label: '来源机器', prop: 'machine_id', align: 'center', operator: 'LIKE', showOverflowTooltip: true },
            { label: '关键词', prop: 'keywords', align: 'center', render: 'tags', operator: 'LIKE' },
            { label: '权重', prop: 'weight', align: 'center', width: 80, operator: '=' },
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
            persona: '',
            keywords: '',
            weight: 1,
        },
    }
)

baTable.mount()
baTable.getData()
provide('baTable', baTable)

onMounted(() => {
    loadMachines()
})
</script>

<style scoped>
.machine-tabs {
    margin-bottom: 10px;
}
</style>
