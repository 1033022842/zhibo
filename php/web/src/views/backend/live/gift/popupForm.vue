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
                {{ baTable.form.operate ? baTable.form.operate : '' }}礼物
            </div>
        </template>
        <el-scrollbar v-loading="baTable.form.loading" class="ba-table-form-scrollbar">
            <div class="ba-operate-form" :class="'ba-' + baTable.form.operate + '-form'">
                <el-form ref="formRef" :model="baTable.form.items" :rules="rules" label-width="120px" v-if="!baTable.form.loading">
                    <FormItem label="礼物编码" v-model="baTable.form.items!.gift_code" prop="gift_code" type="string" />
                    <FormItem label="礼物名称" v-model="baTable.form.items!.name" prop="name" type="string" />
                    <FormItem label="钻石价格" v-model="baTable.form.items!.price_diamond" prop="price_diamond" type="number" :input-attr="{ precision: 2 }" />
                    <FormItem
                        label="触发模式"
                        v-model="baTable.form.items!.trigger_mode"
                        type="radio"
                        :input-attr="{
                            border: true,
                            content: { 'none': '无触发', 'keyword': '关键词触发', 'privilege': '特权模式', 'interaction': '互动模式' },
                        }"
                    />
                    <FormItem
                        label="触发关键词"
                        v-model="baTable.form.items!.keyword"
                        type="remoteSelect"
                        :input-attr="{
                            field: 'keyword',
                            remoteUrl: '/admin/live.Gift/keywords',
                            placeholder: '选择触发关键词（留空则不触发）',
                            clearable: true,
                        }"
                    />
                    <FormItem label="触发时长(秒)" v-model="baTable.form.items!.trigger_duration_sec" type="number" />
                    <FormItem label="特效编码" v-model="baTable.form.items!.effect_code" type="string" />
                    <FormItem
                        label="状态"
                        v-model="baTable.form.items!.status"
                        type="radio"
                        :input-attr="{ border: true, content: { '0': '禁用', '1': '启用' } }"
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
    gift_code: [{ required: true, message: '请输入礼物编码', trigger: 'blur' }],
    name: [{ required: true, message: '请输入礼物名称', trigger: 'blur' }],
    price_diamond: [{ required: true, message: '请输入钻石价格', trigger: 'blur' }],
})
</script>
