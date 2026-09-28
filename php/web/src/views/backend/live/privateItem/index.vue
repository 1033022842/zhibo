<template>
    <div class="default-main ba-table-box">
        <el-alert
            class="ba-table-alert"
            title="配置 AI 女友端 Private Content 私密内容卡片，端上列表页会读取已上架的卡片（全部 / 按点赞率排序）"
            type="info"
            show-icon
        />
        <TableHeader :buttons="['refresh', 'add', 'edit', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']" />
        <div class="mb-3">
            <el-button type="warning" @click="priceDialog = true">统一价格</el-button>
            <span class="text-gray-400 text-xs ml-2">批量把（可按分类筛选）私密内容的价格设置为同一数值</span>
        </div>
        <Table />
        <PopupForm />

        <el-dialog v-model="priceDialog" title="统一价格" width="420px">
            <el-form label-width="90px">
                <el-form-item label="应用范围">
                    <el-select v-model="priceScope" style="width: 100%">
                        <el-option label="全部分类" value="" />
                        <el-option label="Girls（真人）" value="girls" />
                        <el-option label="Anime（动漫）" value="anime" />
                    </el-select>
                </el-form-item>
                <el-form-item label="价格(代币)">
                    <el-input-number v-model="priceValue" :min="0" :step="10" style="width: 100%" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="priceDialog = false">取消</el-button>
                <el-button type="primary" :loading="priceLoading" @click="applyPrice">确定</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { provide, reactive, ref } from 'vue'
import baTableClass from '/@/utils/baTable'
import { baTableApi } from '/@/api/common'
import createAxios from '/@/utils/axios'
import PopupForm from './popupForm.vue'
import Table from '/@/components/table/index.vue'
import TableHeader from '/@/components/table/header/index.vue'
import { defaultOptButtons } from '/@/components/table'
defineOptions({
    name: 'live/privateItem',
})

const baTable = new baTableClass(
    new baTableApi('/admin/live.PrivateItem/'),
    {
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: 'ID', prop: 'id', align: 'center', width: 70, operator: '=' },
            { label: '封面', prop: 'poster', align: 'center', width: 80, render: 'image', operator: false },
            { label: '角色名', prop: 'creator', align: 'center', width: 120, operator: 'LIKE', showOverflowTooltip: true },
            {
                label: '分类',
                prop: 'category',
                align: 'center',
                width: 100,
                render: 'tag',
                custom: { girls: 'danger', anime: 'primary' },
                replaceValue: { girls: 'Girls 真人', anime: 'Anime 动漫' },
                operator: '=',
            },
            { label: '价格', prop: 'price', align: 'center', width: 80, operator: 'RANGE' },
            { label: '点赞率', prop: 'like_rate', align: 'center', width: 90, operator: 'RANGE' },
            {
                label: '媒体类型',
                prop: 'media_type',
                align: 'center',
                width: 100,
                render: 'tag',
                custom: { video: 'primary', image: 'warning', mixed: 'success' },
                replaceValue: { video: '视频', image: '图片', mixed: '混合' },
                operator: '=',
            },
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
            status: 1,
            weigh: 0,
            price: 0,
            like_rate: 0,
            media_type: 'video',
            video_count: 0,
            image_count: 0,
            badge: '',
        },
    }
)

baTable.mount()
baTable.getData()
provide('baTable', baTable)

const priceDialog = ref(false)
const priceScope = ref('')
const priceValue = ref(0)
const priceLoading = ref(false)

function applyPrice() {
    priceLoading.value = true
    createAxios({
        url: '/admin/live.PrivateItem/unifyPrice',
        method: 'post',
        params: { category: priceScope.value, price: priceValue.value },
    })
        .then((res) => {
            priceDialog.value = false
            if (window.ElMessage) ElMessage.success((res as any).msg || '已统一价格')
            baTable.getData()
        })
        .catch(() => {})
        .finally(() => {
            priceLoading.value = false
        })
}
</script>
