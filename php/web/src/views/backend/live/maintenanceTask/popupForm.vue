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
                {{ baTable.form.operate ? baTable.form.operate : '' }}维护任务
            </div>
        </template>
        <el-scrollbar v-loading="baTable.form.loading" class="ba-table-form-scrollbar">
            <div class="ba-operate-form" :class="'ba-' + baTable.form.operate + '-form'">
                <el-form ref="formRef" :model="baTable.form.items" :rules="rules" label-width="120px" v-show="!baTable.form.loading">
                    <FormItem label="任务名称" v-model="baTable.form.items!.name" prop="name" type="string" :input-attr="{ placeholder: '如: 服务器续费、甜心人设维护' }" />
                    <FormItem label="到期日期" v-model="baTable.form.items!.due_date" prop="due_date" type="date" />
                    <FormItem label="备注" v-model="baTable.form.items!.remark" type="textarea" :input-attr="{ placeholder: '可选，如维护内容说明', rows: 3 }" />
                    <FormItem
                        label="重复提醒"
                        v-model="baTable.form.items!.repeat_remind"
                        type="radio"
                        :input-attr="{ border: true, content: { '0': '关闭', '1': '开启（到期后每天提醒）' } }"
                    />
                    <FormItem
                        label="状态"
                        v-model="baTable.form.items!.status"
                        type="radio"
                        :input-attr="{ border: true, content: { '0': '待通知', '1': '已通知', '2': '已关闭' } }"
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
    name: [{ required: true, message: '请输入任务名称', trigger: 'blur' }],
    due_date: [{ required: true, message: '请选择到期日期', trigger: 'blur' }],
})
</script>
