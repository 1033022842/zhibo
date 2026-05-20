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
                {{ baTable.form.operate ? baTable.form.operate : '' }}素材
            </div>
        </template>
        <el-scrollbar v-loading="baTable.form.loading" class="ba-table-form-scrollbar">
            <div class="ba-operate-form" :class="'ba-' + baTable.form.operate + '-form'">
                <el-form ref="formRef" :model="baTable.form.items" :rules="rules" label-width="120px" v-if="!baTable.form.loading">
                    <FormItem label="素材编码" v-model="baTable.form.items!.asset_code" type="string" :input-attr="{ placeholder: '留空自动生成' }" />
                    <FormItem label="标题" v-model="baTable.form.items!.title" prop="title" type="string" />
                    <FormItem label="上传视频" v-model="baTable.form.items!.file_url" prop="file_url" type="file" :input-attr="{ limit: 1 }" />
                    <FormItem
                        label="素材类型"
                        v-model="baTable.form.items!.asset_type"
                        type="select"
                        :input-attr="{ content: { video: '视频', image: '图片', audio: '音频', subtitle: '字幕' } }"
                    />
                    <FormItem
                        label="场景"
                        v-model="baTable.form.items!.scene_type"
                        type="select"
                        :input-attr="{ content: { public: '公共', privilege: '特权', interaction: '互动', cover: '封面' } }"
                    />
                    <FormItem label="时长(ms)" v-model="baTable.form.items!.duration_ms" type="number" />
                    <FormItem label="校验值" v-model="baTable.form.items!.checksum" type="string" :input-attr="{ placeholder: '留空自动计算' }" />
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
    file_url: [{ required: true, message: '请上传视频文件', trigger: 'change' }],
})
</script>
