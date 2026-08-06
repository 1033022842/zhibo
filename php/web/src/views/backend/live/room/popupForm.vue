<template>
    <el-dialog
        class="ba-operate-dialog"
        :close-on-click-modal="false"
        :model-value="['Add', 'Edit'].includes(baTable.form.operate!)"
        @close="baTable.toggleForm"
        :destroy-on-close="true"
        width="780px"
    >
        <template #header>
            <div class="title" v-drag="['.ba-operate-dialog', '.el-dialog__header']" v-zoom="'.ba-operate-dialog'">
                {{ baTable.form.operate ? baTable.form.operate : '' }}房间
            </div>
        </template>
        <el-scrollbar v-loading="baTable.form.loading" class="ba-table-form-scrollbar">
            <div class="ba-operate-form" :class="'ba-' + baTable.form.operate + '-form'">
                <el-form ref="formRef" :model="baTable.form.items" :rules="rules" label-width="120px" v-if="!baTable.form.loading">
                    <FormItem label="房间号" v-model="baTable.form.items!.room_no" prop="room_no" type="string" />
                    <FormItem label="房间标题" v-model="baTable.form.items!.title" prop="title" type="string" />
                    <FormItem label="房间副标题" v-model="baTable.form.items!.subtitle" type="string" />
                    <FormItem
                        label="绑定人设"
                        v-model="baTable.form.items!.persona_id"
                        prop="persona_id"
                        type="remoteSelect"
                        :input-attr="{
                            field: 'name',
                            remoteUrl: '/admin/live.Persona/index',
                            params: { select: true, status: 1 },
                            placeholder: '选择人设',
                        }"
                    />
                    <FormItem
                        label="视频素材"
                        v-model="baTable.form.items!.asset_ids"
                        prop="asset_ids"
                        type="remoteSelect"
                        :input-attr="{
                            multiple: true,
                            field: 'title',
                            remoteUrl: '/admin/live.MediaAsset/index',
                            params: { select: true, status: 1, asset_type: 'video', scene_type: 'public' },
                            placeholder: '选择一个或多个视频素材',
                        }"
                    />
                    <FormItem label="标签" v-model="baTable.form.items!.tag_names" type="string" :input-attr="{ placeholder: '逗号分隔，例如：情感,陪伴' }" />
                    <FormItem label="封面" v-model="baTable.form.items!.cover_url" type="image" />
                    <FormItem label="排序" v-model="baTable.form.items!.sort" type="number" />
                    <FormItem
                        label="状态"
                        v-model="baTable.form.items!.status"
                        type="radio"
                        :input-attr="{ border: true, content: { '0': '关闭', '1': '启用', '2': '维护' } }"
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
    room_no: [{ required: true, message: '请输入房间号', trigger: 'blur' }],
    title: [{ required: true, message: '请输入房间标题', trigger: 'blur' }],
    persona_id: [{ required: true, message: '请选择人设', trigger: 'change' }],
    asset_ids: [{ required: true, message: '请选择视频素材', trigger: 'change' }],
})
</script>
