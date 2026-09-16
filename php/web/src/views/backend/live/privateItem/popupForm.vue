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
                {{ baTable.form.operate ? baTable.form.operate : '' }}私密内容
            </div>
        </template>
        <el-scrollbar v-loading="baTable.form.loading" class="ba-table-form-scrollbar">
            <div class="ba-operate-form" :class="'ba-' + baTable.form.operate + '-form'">
                <el-form ref="formRef" :model="baTable.form.items" :rules="rules" label-width="120px" v-show="!baTable.form.loading">
                    <FormItem
                        label="描述文案"
                        v-model="baTable.form.items!.title"
                        prop="title"
                        type="textarea"
                        :input-attr="{ rows: 5, placeholder: '卡片上展示的唯一文字' }"
                    />
                    <FormItem label="封面图" v-model="baTable.form.items!.poster" type="image" />
                    <FormItem
                        label="视频文件"
                        v-model="baTable.form.items!.video_url"
                        type="file"
                        :input-attr="{ limit: 1 }"
                        tip="解锁后在前台弹窗里播放"
                    />
                    <FormItem label="图片" v-model="baTable.form.items!.images" type="images" tip="解锁后在前台弹窗里查看，可多张" />
                    <FormItem label="角色头像" v-model="baTable.form.items!.avatar" type="image" />
                    <FormItem label="角色名" v-model="baTable.form.items!.creator" prop="creator" type="string" />
                    <FormItem label="价格" v-model="baTable.form.items!.price" type="number" :input-attr="{ min: 0 }" />
                    <FormItem
                        label="点赞率(%)"
                        v-model="baTable.form.items!.like_rate"
                        type="number"
                        :input-attr="{ min: 0, max: 100 }"
                    />
                    <FormItem
                        label="媒体类型"
                        v-model="baTable.form.items!.media_type"
                        type="select"
                        :input-attr="{ content: { video: '视频', image: '图片', mixed: '混合' } }"
                    />
                    <FormItem label="视频数" v-model="baTable.form.items!.video_count" type="number" :input-attr="{ min: 0 }" />
                    <FormItem
                        label="时长"
                        v-model="baTable.form.items!.duration"
                        type="string"
                        :input-attr="{ placeholder: '如 09:09 或 (Total 01:55)' }"
                    />
                    <FormItem label="图片数" v-model="baTable.form.items!.image_count" type="number" :input-attr="{ min: 0 }" />
                    <FormItem
                        label="角标"
                        v-model="baTable.form.items!.badge"
                        type="select"
                        :input-attr="{ content: { '': '无', new: 'new' } }"
                    />
                    <FormItem
                        label="详情链接"
                        v-model="baTable.form.items!.purchase_url"
                        type="string"
                        :input-attr="{ placeholder: '详情/购买链接，可填外链' }"
                    />
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
    title: [{ required: true, message: '请填写描述文案', trigger: 'blur' }],
})
</script>
