<template>
    <el-dialog
        class="ba-operate-dialog"
        :close-on-click-modal="false"
        :model-value="['Edit'].includes(baTable.form.operate!)"
        @close="baTable.toggleForm"
        :destroy-on-close="true"
    >
        <template #header>
            <div class="title" v-drag="['.ba-operate-dialog', '.el-dialog__header']" v-zoom="'.ba-operate-dialog'">
                {{ baTable.form.operate ? baTable.form.operate + '卡片' : '' }}
            </div>
        </template>
        <el-scrollbar v-loading="baTable.form.loading" class="ba-table-form-scrollbar">
            <div class="ba-operate-form" :class="'ba-' + baTable.form.operate + '-form'">
                <el-form ref="formRef" :model="baTable.form.items" :rules="rules" label-width="120px" v-show="!baTable.form.loading">
                    <el-alert
                        class="ba-table-alert"
                        title="卡片代码 / 提示词 / LoRA 等生成参数不在此处修改——它们是调优过的出片参数，误改会破坏生成质量"
                        type="warning"
                        show-icon
                        :closable="false"
                        style="margin-bottom: 12px"
                    />
                    <FormItem label="卡片代码" prop="code" type="string" :input-attr="{ disabled: true }" />
                    <FormItem label="标题" v-model="baTable.form.items!.title" prop="title" type="string" />
                    <FormItem label="类型" prop="kind" type="string" :input-attr="{ disabled: true }" />
                    <FormItem label="时长(秒)" v-model="baTable.form.items!.duration_sec" type="number" :input-attr="{ min: 0, max: 60 }" />
                    <FormItem
                        label="代币价"
                        v-model="baTable.form.items!.price_tokens"
                        type="number"
                        :input-attr="{ placeholder: '-1 = 跟随站点全局单价；0 = 免费；>0 = 固定代币价', step: 0.5 }"
                    />
                    <FormItem label="权重" v-model="baTable.form.items!.weigh" type="number" :input-attr="{ placeholder: '越大越靠前' }" />
                    <FormItem
                        label="状态"
                        v-model="baTable.form.items!.status"
                        type="radio"
                        :input-attr="{ border: true, content: { '1': '启用', '0': '下架' } }"
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
    title: [{ required: true, message: '标题不能为空', trigger: 'blur' }],
})
</script>
