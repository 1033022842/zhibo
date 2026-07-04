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
                {{ baTable.form.operate ? baTable.form.operate : '' }}切片
            </div>
        </template>
        <el-scrollbar v-loading="baTable.form.loading" class="ba-table-form-scrollbar">
            <div class="ba-operate-form" :class="'ba-' + baTable.form.operate + '-form'">
                <el-form ref="formRef" :model="baTable.form.items" :rules="rules" label-width="120px" v-if="!baTable.form.loading">
                    <FormItem label="标题" v-model="baTable.form.items!.title" prop="title" type="string" />
                    <FormItem label="视频URL" v-model="baTable.form.items!.video_url" prop="video_url" type="string" :input-attr="{ placeholder: '上传后的视频文件路径' }" />
                    <FormItem label="封面图" v-model="baTable.form.items!.cover_url" type="string" :input-attr="{ placeholder: '可选' }" />
                    <FormItem label="时长(秒)" v-model="baTable.form.items!.duration" type="number" />
                    <FormItem label="直播日期" v-model="baTable.form.items!.live_date" prop="live_date" type="date" />
                    <FormItem label="关联角色" v-model="baTable.form.items!.persona_id" type="number" :input-attr="{ placeholder: '关联角色ID' }" />
                    <FormItem label="关联房间" v-model="baTable.form.items!.room_id" type="number" :input-attr="{ placeholder: '关联房间ID' }" />
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
    title: [{ required: true, message: '请输入标题', trigger: 'blur' }],
    video_url: [{ required: true, message: '请输入视频URL', trigger: 'blur' }],
    live_date: [{ required: true, message: '请选择直播日期', trigger: 'blur' }],
})
</script>
