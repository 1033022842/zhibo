<template>
    <div class="maintenance-config">
        <el-alert
            v-if="!configured"
            title="TG 通知尚未配置，请填写 Bot Token 和 Chat ID 并保存"
            type="warning"
            show-icon
            :closable="false"
            style="margin-bottom: 16px"
        />
        <el-card shadow="never" header="Telegram 通知配置">
            <template #header>
                <span>Telegram 通知配置</span>
                <el-button type="primary" :loading="loading" style="float: right" @click="onSave">保存配置</el-button>
            </template>
            <el-form ref="formRef" :model="form" label-width="120px" :rules="rules">
                <el-form-item label="Bot Token" prop="bot_token">
                    <el-input v-model="form.bot_token" placeholder="从 @BotFather 获取的 Token" />
                    <span class="form-tip">在 Telegram 搜索 @BotFather，发送 /newbot 创建机器人获取 Token</span>
                </el-form-item>
                <el-form-item label="Chat ID" prop="chat_id">
                    <el-input v-model="form.chat_id" placeholder="多个 Chat ID 用英文逗号分隔" />
                    <span class="form-tip">将机器人拉入群或私聊后，向机器人发消息，然后访问 https://api.telegram.org/bot{TOKEN}/getUpdates 获取</span>
                </el-form-item>
            </el-form>
        </el-card>

        <el-card shadow="never" style="margin-top: 16px" header="测试发送">
            <el-button type="primary" :loading="testLoading" @click="onTestSend">发送测试消息</el-button>
            <span class="form-tip" style="margin-left: 12px">使用上面填写的配置发送测试消息</span>
            <div v-if="testResult" class="test-result" :class="testResult.ok ? 'success' : 'error'">
                {{ testResult.ok ? '发送成功' : '发送失败: ' + testResult.error }}
            </div>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { baTableApi } from '/@/api/common'
import { ElMessage } from 'element-plus'
import type { FormInstance } from 'element-plus'

defineOptions({
    name: 'live/maintenanceConfig',
})

const api = new baTableApi('/admin/live.MaintenanceConfig/')
const loading = ref(false)
const testLoading = ref(false)
const formRef = ref<FormInstance>()

const form = reactive({
    bot_token: '',
    chat_id: '',
})

const testResult = ref<{ ok: boolean; error?: string } | null>(null)

const configured = computed(() => !!(form.bot_token && form.chat_id))

const rules = {
    bot_token: [{ required: true, message: '请输入 Bot Token', trigger: 'blur' }],
    chat_id: [{ required: true, message: '请输入 Chat ID', trigger: 'blur' }],
}

const loadConfig = async () => {
    loading.value = true
    try {
        const res = await api.index()
        if (res.data?.row) {
            form.bot_token = res.data.row.bot_token || ''
            form.chat_id = res.data.row.chat_id || ''
        }
    } catch {
        // ignore
    }
    loading.value = false
}

const onSave = async () => {
    const valid = await formRef.value?.validate().catch(() => false)
    if (!valid) return

    loading.value = true
    try {
        await api.postData('save', form)
        ElMessage.success('配置保存成功')
    } catch (e: any) {
        ElMessage.error(e?.message || '保存失败')
    }
    loading.value = false
}

const onTestSend = async () => {
    if (!form.bot_token || !form.chat_id) {
        ElMessage.warning('请先填写 Bot Token 和 Chat ID')
        return
    }

    testLoading.value = true
    testResult.value = null
    try {
        await api.postData('test', form)
        testResult.value = { ok: true }
    } catch (e: any) {
        testResult.value = { ok: false, error: e?.message || '发送失败' }
    }
    testLoading.value = false
}

onMounted(() => {
    loadConfig()
})
</script>

<style scoped lang="scss">
.maintenance-config {
    padding: 16px;
    .form-tip {
        font-size: 12px;
        color: #909399;
        margin-left: 8px;
    }
    .test-result {
        margin-top: 8px;
        padding: 8px 12px;
        border-radius: 4px;
        font-size: 13px;
        &.success {
            background-color: #f0f9eb;
            color: #67c23a;
            border: 1px solid #e1f3d8;
        }
        &.error {
            background-color: #fef0f0;
            color: #f56c6c;
            border: 1px solid #fde2e2;
        }
    }
}
</style>
