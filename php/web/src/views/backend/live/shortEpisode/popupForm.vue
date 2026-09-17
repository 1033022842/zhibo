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
                {{ baTable.form.operate ? baTable.form.operate : '' }}短剧剧集
            </div>
        </template>
        <el-scrollbar v-loading="baTable.form.loading" class="ba-table-form-scrollbar">
            <div class="ba-operate-form" :class="'ba-' + baTable.form.operate + '-form'">
                <el-form ref="formRef" :model="baTable.form.items" :rules="rules" label-width="120px" v-show="!baTable.form.loading">
                    <FormItem
                        label="所属短剧"
                        v-model="baTable.form.items!.short_id"
                        prop="short_id"
                        type="remoteSelect"
                        :input-attr="{
                            field: 'title',
                            remoteUrl: '/admin/live.ShortItem/index',
                            params: { select: true },
                            placeholder: '选择所属短剧',
                        }"
                    />
                    <FormItem
                        label="第几集"
                        v-model="baTable.form.items!.episode_no"
                        prop="episode_no"
                        type="number"
                        :input-attr="{ min: 1, placeholder: '同一部剧内集号不能重复' }"
                    />
                    <FormItem
                        label="本集标题"
                        v-model="baTable.form.items!.title"
                        type="string"
                        :input-attr="{ placeholder: '如 Ep.3 深夜的告白（可留空）' }"
                    />
                    <FormItem label="本集封面" v-model="baTable.form.items!.poster" type="image" tip="留空则用短剧卡片的封面" />
                    <FormItem
                        label="视频文件"
                        v-model="baTable.form.items!.video_url"
                        type="file"
                        :input-attr="{ limit: 1 }"
                        tip="本集要播放的视频，上传后前台在本站弹窗播放"
                    />
                    <FormItem
                        label="时长"
                        v-model="baTable.form.items!.duration"
                        type="string"
                        :input-attr="{ placeholder: '如 03:12（可选，仅展示）' }"
                    />
                    <FormItem
                        label="价格(钻石)"
                        v-model="baTable.form.items!.price"
                        type="number"
                        :input-attr="{ min: 0 }"
                        tip="0 = 免费；本集单独收费填正数。若集号 ≤ 短剧的「前N集免费」，本集实际免钻"
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
    short_id: [{ required: true, message: '请选择所属短剧', trigger: 'change' }],
    episode_no: [{ required: true, message: '请填写第几集', trigger: 'blur' }],
})
</script>
