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
                {{ baTable.form.operate ? baTable.form.operate : '' }}换脸模板
            </div>
        </template>
        <el-scrollbar v-loading="baTable.form.loading" class="ba-table-form-scrollbar">
            <div class="ba-operate-form" :class="'ba-' + baTable.form.operate + '-form'">
                <el-form ref="formRef" :model="baTable.form.items" :rules="rules" label-width="120px" v-show="!baTable.form.loading">
                    <FormItem label="模板名称" v-model="baTable.form.items!.title" prop="title" type="string" :input-attr="{ placeholder: '留空自动生成' }" />
                    <FormItem label="模板视频(固定视频)" v-model="baTable.form.items!.video_url" prop="video_url" type="file" :input-attr="{ limit: 1 }" />
                    <FormItem label="封面图" v-model="baTable.form.items!.cover_url" type="image" />
                    <FormItem label="时长(秒)" v-model="baTable.form.items!.duration_sec" type="number" :input-attr="{ min: 0 }" />
                    <FormItem label="描述" v-model="baTable.form.items!.description" type="textarea" :input-attr="{ rows: 3, placeholder: '展示在端上的模板说明（可选）' }" />
                    <FormItem label="权重" v-model="baTable.form.items!.weigh" type="number" :input-attr="{ placeholder: '越大越靠前' }" />
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
    video_url: [{ required: true, message: '请上传模板视频', trigger: 'change' }],
})
</script>
