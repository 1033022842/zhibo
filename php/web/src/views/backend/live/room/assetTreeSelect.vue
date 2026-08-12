<template>
    <div class="asset-tree-select">
        <el-tree
            ref="treeRef"
            :data="treeData"
            :props="treeProps"
            node-key="id"
            show-checkbox
            :default-checked-keys="checkedKeys"
            :default-expanded-keys="[]"
            :expand-on-click-node="true"
            @check="onCheck"
            empty-text="请先选择人设"
        >
            <template #default="{ node, data }">
                <span class="tree-node" :class="{ 'is-leaf': !!data.title }">
                    <span class="tree-label">{{ data.title || data.label }}</span>
                    <span v-if="data.duration_ms" class="tree-dur">{{ formatDuration(data.duration_ms) }}</span>
                </span>
            </template>
        </el-tree>
    </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { ElTree } from 'element-plus'
import createAxios from '/@/utils/axios'

const props = defineProps<{
    modelValue: number[]
    persona: string
}>()
const emit = defineEmits<{
    (e: 'update:modelValue', val: number[]): void
}>()

const treeRef = ref<InstanceType<typeof ElTree>>()
const treeData = ref<any[]>([])
const checkedKeys = ref<number[]>([])
const treeProps = { label: 'label', children: 'children' }

const loadTree = async () => {
    if (!props.persona) {
        treeData.value = []
        return
    }
    const res = await createAxios({
        url: '/admin/live.MediaAsset/tree',
        method: 'GET',
        params: { persona: props.persona, asset_ids: props.modelValue },
    })
    const data = res?.data ?? res
    if (data?.tree) {
        treeData.value = data.tree
    }
    if (data?.selected) {
        checkedKeys.value = Object.keys(data.selected).map(Number)
    }
}

const onCheck = () => {
    const checked = treeRef.value?.getCheckedKeys(false) as number[]
    const leafIds = (checked || []).filter((k: any) => typeof k === 'number')
    emit('update:modelValue', leafIds)
}

const formatDuration = (ms: number) => {
    if (!ms) return ''
    return (ms / 1000).toFixed(1) + 's'
}

watch(() => props.persona, () => loadTree())
onMounted(() => loadTree())
</script>

<style scoped>
.asset-tree-select {
    border: 1px solid var(--el-border-color);
    border-radius: 6px;
    padding: 10px;
    max-height: 360px;
    overflow-y: auto;
    width: 100%;
}
.asset-tree-select :deep(.el-tree) {
    background: transparent;
    --el-tree-node-hover-bg-color: var(--el-fill-color-light);
}
.asset-tree-select :deep(.el-tree-node__content) {
    height: 32px;
}
.tree-node {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding-right: 8px;
}
.tree-label {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.tree-dur {
    color: var(--el-text-color-secondary);
    font-size: 12px;
    flex-shrink: 0;
}
</style>
