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
                {{ baTable.form.operate ? baTable.form.operate : '' }}动态
            </div>
        </template>
        <el-scrollbar v-loading="baTable.form.loading" class="ba-table-form-scrollbar">
            <div class="ba-operate-form" :class="'ba-' + baTable.form.operate + '-form'">
                <el-form ref="formRef" :model="baTable.form.items" :rules="rules" label-width="120px" v-show="!baTable.form.loading">
                    <FormItem label="帖子ID" v-model="baTable.form.items!.post_id" type="string" :input-attr="{ placeholder: '原站帖子id，如 163' }" />
                    <FormItem label="角色名" v-model="baTable.form.items!.character_name" prop="character_name" type="string" />
                    <FormItem label="角色头像" v-model="baTable.form.items!.character_avatar" type="image" />
                    <FormItem
                        label="角色主页链接"
                        v-model="baTable.form.items!.character_url"
                        type="string"
                        tip="留空或填站外地址时，前台一律回退到站内 ./Chat.html；要跳指定角色请填站内地址，如 ./Chat.html?id=123"
                        :input-attr="{ placeholder: '如 ./Chat.html?id=123（留空=站内聊天页）' }"
                    />
                    <FormItem label="视频地址" v-model="baTable.form.items!.video_url" type="file" :input-attr="{ limit: 1 }" />
                    <FormItem label="视频封面" v-model="baTable.form.items!.poster_url" type="image" />
                    <FormItem
                        label="描述"
                        v-model="baTable.form.items!.description"
                        type="textarea"
                        :input-attr="{ rows: 3, placeholder: '帖子描述（可选）' }"
                    />
                    <FormItem label="点赞数" v-model="baTable.form.items!.likes" type="number" :input-attr="{ min: 0 }" />
                    <FormItem label="浏览数" v-model="baTable.form.items!.views" type="number" :input-attr="{ min: 0 }" />
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
    character_name: [{ required: true, message: '请填写角色名', trigger: 'blur' }],
})
</script>
