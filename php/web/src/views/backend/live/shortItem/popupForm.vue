<template>
    <el-dialog
        class="ba-operate-dialog"
        :close-on-click-modal="false"
        :model-value="['Add', 'Edit'].includes(baTable.form.operate!)"
        @close="baTable.toggleForm"
        :destroy-on-close="true"
        width="680px"
    >
        <template #header>
            <div class="title" v-drag="['.ba-operate-dialog', '.el-dialog__header']" v-zoom="'.ba-operate-dialog'">
                {{ baTable.form.operate ? baTable.form.operate : '' }}短剧卡片
            </div>
        </template>
        <el-scrollbar v-loading="baTable.form.loading" class="ba-table-form-scrollbar">
            <div class="ba-operate-form" :class="'ba-' + baTable.form.operate + '-form'">
                <el-form ref="formRef" :model="baTable.form.items" :rules="rules" label-width="120px" v-show="!baTable.form.loading">
                    <FormItem label="标题" v-model="baTable.form.items!.title" prop="title" type="string" />
                    <FormItem
                        label="介绍"
                        v-model="baTable.form.items!.description"
                        type="textarea"
                        :input-attr="{ rows: 4, placeholder: '前台剧集弹窗里展示的剧情简介（可留空）' }"
                    />
                    <FormItem
                        label="前N集免费"
                        v-model="baTable.form.items!.free_episodes"
                        type="number"
                        :input-attr="{ min: 0 }"
                        tip="集号 ≤ N 的剧集免钻直接播放；0 表示没有免费集。剧集在「短剧剧集」菜单里配置"
                    />
                    <FormItem label="封面图" v-model="baTable.form.items!.poster" type="image" />
                    <FormItem
                        label="视频文件"
                        v-model="baTable.form.items!.video_url"
                        type="file"
                        :input-attr="{ limit: 1 }"
                        tip="上传后前台点击卡片在本站弹窗播放；留空则卡片跳下方「链接」"
                    />
                    <FormItem
                        label="链接"
                        v-model="baTable.form.items!.href"
                        type="string"
                        :input-attr="{ placeholder: '卡片兜底跳转链接（可选）' }"
                    />
                    <FormItem
                        label="分区"
                        v-model="baTable.form.items!.section"
                        type="select"
                        :input-attr="{
                            content: {
                                continue_watching: 'Continue watching',
                                top_series: 'Top series',
                                explore: 'Explore',
                            },
                        }"
                    />
                    <FormItem
                        label="名次"
                        v-model="baTable.form.items!.rank"
                        type="number"
                        :input-attr="{ min: 0, placeholder: '仅 Top series 分区使用' }"
                    />
                    <FormItem
                        label="进度%"
                        v-model="baTable.form.items!.progress"
                        type="number"
                        :input-attr="{ min: 0, max: 100, step: 0.01, precision: 2 }"
                    />
                    <FormItem
                        label="SPICY"
                        v-model="baTable.form.items!.spicy"
                        type="radio"
                        :input-attr="{ border: true, content: { '1': '是', '0': '否' } }"
                    />
                    <FormItem
                        label="高亮变体"
                        v-model="baTable.form.items!.featured"
                        type="select"
                        :input-attr="{ content: { '': '无', ring: 'ring', gradient: 'gradient' } }"
                    />
                    <FormItem
                        label="New Episodes"
                        v-model="baTable.form.items!.new_episodes"
                        type="radio"
                        :input-attr="{ border: true, content: { '1': '是', '0': '否' } }"
                    />
                    <FormItem label="权重" v-model="baTable.form.items!.weigh" type="number" :input-attr="{ placeholder: '越大越靠前' }" />
                    <FormItem
                        label="状态"
                        v-model="baTable.form.items!.status"
                        type="radio"
                        :input-attr="{ border: true, content: { '1': '上架', '0': '下架' } }"
                    />
                </el-form>
            </div>
        </el-scrollbar>
        <template #footer>
            <el-button @click="baTable.toggleForm('')">取消</el-button>
            <el-button v-blur :loading="baTable.form.submitLoading" @click="baTable.onSubmit(formRef)" type="primary">保存</el-button>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { inject, reactive, useTemplateRef } from 'vue'
import type baTableClass from '/@/utils/baTable'
import type { FormItemRule } from 'element-plus'
import FormItem from '/@/components/formItem/index.vue'

const formRef = useTemplateRef('formRef')
const baTable = inject('baTable') as baTableClass

const rules: Partial<Record<string, FormItemRule[]>> = reactive({
    title: [{ required: true, message: '请填写标题', trigger: 'blur' }],
})
</script>
