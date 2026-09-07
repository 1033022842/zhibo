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
                {{ baTable.form.operate ? baTable.form.operate : '' }}AI角色
            </div>
        </template>
        <el-scrollbar v-loading="baTable.form.loading" class="ba-table-form-scrollbar">
            <div class="ba-operate-form" :class="'ba-' + baTable.form.operate + '-form'">
                <el-form ref="formRef" :model="baTable.form.items" :rules="rules" label-width="120px" v-show="!baTable.form.loading">
                    <FormItem label="角色名" v-model="baTable.form.items!.title" prop="title" type="string" :input-attr="{ placeholder: '角色名' }" />
                    <FormItem label="分类" v-model="baTable.form.items!.category" type="string" :input-attr="{ placeholder: '分类' }" />
                    <FormItem label="封面图" v-model="baTable.form.items!.cover_url" type="image" />
                    <FormItem
                        label="动效视频"
                        v-model="baTable.form.items!.video_url"
                        prop="video_url"
                        type="file"
                        :input-attr="{ limit: 1, placeholder: '上传列表卡片 hover 动效视频' }"
                    />
                    <FormItem label="简介" v-model="baTable.form.items!.description" type="textarea" :input-attr="{ rows: 4, placeholder: '角色简介，用于聊天人设' }" />
                    <FormItem label="权重" v-model="baTable.form.items!.weigh" type="number" />
                    <FormItem
                        label="是否公开"
                        v-model="baTable.form.items!.is_public"
                        type="radio"
                        :input-attr="{ border: true, content: { '1': '公开', '0': '隐藏' } }"
                    />
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
    title: [{ required: true, message: '请输入角色名', trigger: 'blur' }],
})
</script>
