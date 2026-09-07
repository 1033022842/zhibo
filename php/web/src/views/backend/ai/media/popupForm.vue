<template>
    <el-dialog
        class="ba-operate-dialog"
        :close-on-click-modal="false"
        :model-value="['Add', 'Edit'].includes(baTable.form.operate!)"
        @close="baTable.toggleForm"
        :destroy-on-close="true"
    >
        <template #header>
            <div class="title" v-drag="['.ba-operate-dialog', '.el-dialog__header']" v-zoom="'.ba-operate-dialog'">
                {{ baTable.form.operate ? baTable.form.operate : '' }}AI素材
            </div>
        </template>
        <el-scrollbar v-loading="baTable.form.loading" class="ba-table-form-scrollbar">
            <div class="ba-operate-form" :class="'ba-' + baTable.form.operate + '-form'">
                <el-form ref="formRef" :model="baTable.form.items" :rules="rules" label-width="120px" v-show="!baTable.form.loading">
                    <FormItem label="标题" v-model="baTable.form.items!.title" prop="title" type="string" :input-attr="{ placeholder: '素材标题' }" />
                    <FormItem
                        label="所属角色"
                        v-model="baTable.form.items!.content_id"
                        prop="content_id"
                        type="remoteSelect"
                        :input-attr="{
                            field: 'title',
                            remoteUrl: '/admin/ai.Media/contentOptions',
                            placeholder: '选择角色',
                            clearable: false,
                        }"
                    />
                    <FormItem
                        label="媒体文件"
                        v-model="baTable.form.items!.video_url"
                        prop="video_url"
                        type="file"
                        :input-attr="{ limit: 1, placeholder: '上传视频或语音文件' }"
                    />
                    <FormItem label="封面图" v-model="baTable.form.items!.cover_url" type="image" />
                    <FormItem
                        label="素材类型"
                        v-model="baTable.form.items!.media_type"
                        type="radio"
                        :input-attr="{ border: true, content: { normal: '普通', special: '特殊(付费)' } }"
                    />
                    <FormItem
                        label="媒体种类"
                        v-model="baTable.form.items!.media_kind"
                        type="radio"
                        :input-attr="{ border: true, content: { video: '视频', voice: '语音' } }"
                    />
                    <FormItem
                        label="场景分类"
                        v-model="baTable.form.items!.scene_type"
                        type="radio"
                        :input-attr="{ border: true, content: { chat: '聊天内容', binge: '追剧', affection: '好感度特殊视频' } }"
                    />
                    <FormItem label="解锁价格(钻石)" v-model="baTable.form.items!.unlock_price" type="number" :input-attr="{ min: 0 }" />
                    <FormItem
                        label="触发关键词"
                        v-model="baTable.form.items!.keywords"
                        type="string"
                        :input-attr="{ placeholder: '多个关键词用英文逗号分隔，留空则不触发' }"
                    />
                    <FormItem label="权重" v-model="baTable.form.items!.weigh" type="number" />
                    <FormItem
                        label="状态"
                        v-model="baTable.form.items!.status"
                        type="radio"
                        :input-attr="{ border: true, content: { '1': '启用', '0': '禁用' } }"
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
    title: [{ required: true, message: '请输入素材标题', trigger: 'blur' }],
    content_id: [{ required: true, message: '请选择所属角色', trigger: 'change' }],
    video_url: [{ required: true, message: '请上传视频或语音文件', trigger: 'change' }],
})
</script>
