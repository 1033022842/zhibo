<template>
  <div class="sms-config">
    <el-alert
      v-if="!configured"
      title="短信服务尚未配置，请填写以下参数并保存"
      type="warning"
      show-icon
      :closable="false"
      style="margin-bottom: 16px"
    />
    <el-card shadow="never" header="短信服务配置">
      <template #header>
        <span>短信服务配置</span>
        <el-button type="primary" :loading="loading" style="float: right" @click="onSave">保存配置</el-button>
      </template>
      <el-form ref="formRef" :model="form" label-width="120px" :rules="rules">
        <el-form-item label="API接口地址" prop="sms_api_url">
          <el-input v-model="form.sms_api_url" placeholder="请输入短信服务商的API地址" />
        </el-form-item>
        <el-form-item label="API密钥" prop="sms_api_key">
          <el-input v-model="form.sms_api_key" type="password" show-password placeholder="请输入API密钥" />
          <span class="form-tip">密钥将加密存储，保存后不可查看明文</span>
        </el-form-item>
        <el-form-item label="签名ID" prop="sms_sign_id">
          <el-input v-model="form.sms_sign_id" placeholder="请输入短信签名ID" />
        </el-form-item>
        <el-form-item label="模板ID" prop="sms_template_id">
          <el-input v-model="form.sms_template_id" placeholder="请输入短信模板ID" />
        </el-form-item>
        <el-form-item label="激活渠道" prop="sms_active_provider">
          <el-input v-model="form.sms_active_provider" placeholder="默认: default" />
          <span class="form-tip">当前使用的短信渠道标识，用于多配置灰度切换</span>
        </el-form-item>
        <el-form-item label="灰度切换配置" prop="sms_grayscale_providers">
          <el-input v-model="form.sms_grayscale_providers" type="textarea" :rows="3" placeholder='如 {"provider_a":30,"provider_b":70}' />
          <span class="form-tip">JSON格式，按百分比分配流量</span>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card shadow="never" style="margin-top: 16px" header="测试发送">
      <el-form :model="testForm" label-width="120px" inline>
        <el-form-item label="测试手机号">
          <el-input v-model="testForm.test_mobile" placeholder="请输入接收短信的手机号" style="width: 220px" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :loading="testLoading" @click="onTestSend">发送测试短信</el-button>
        </el-form-item>
      </el-form>
      <div v-if="testResult" class="test-result" :class="testResult.success ? 'success' : 'error'">
        {{ testResult.message }}
      </div>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { index as getConfigIndex, postData as saveConfig } from '/@/api/backend/routine/config'
import { testSend } from '/@/api/backend/routine/smsConfig'
import { ElMessage } from 'element-plus'
import type { FormInstance } from 'element-plus'

defineOptions({
    name: 'routine/smsConfig',
})

const loading = ref(false)
const testLoading = ref(false)
const configured = ref(false)
const formRef = ref<FormInstance>()

const form = reactive({
  sms_api_url: '',
  sms_api_key: '',
  sms_sign_id: '',
  sms_template_id: '',
  sms_active_provider: 'default',
  sms_grayscale_providers: '',
})

const rules = {
  sms_api_url: [{ required: true, message: '请输入API接口地址', trigger: 'blur' }],
  sms_api_key: [{ required: true, message: '请输入API密钥', trigger: 'blur' }],
  sms_sign_id: [{ required: true, message: '请输入签名ID', trigger: 'blur' }],
  sms_template_id: [{ required: true, message: '请输入模板ID', trigger: 'blur' }],
}

const testForm = reactive({
  test_mobile: '',
})

const testResult = ref<{ success: boolean; message: string } | null>(null)

const loadConfig = async () => {
  loading.value = true
  try {
    const res = await getConfigIndex()
    if (res.data?.list?.sms?.list) {
      const items = res.data.list.sms.list
      for (const item of items) {
        if (item.name in form) {
          ;(form as any)[item.name] = item.value || ''
        }
      }
    }
    configured.value = !!(form.sms_api_url && form.sms_api_key && form.sms_sign_id && form.sms_template_id)
  } catch (e: any) {
    ElMessage.error(e?.message || '加载配置失败')
  }
  loading.value = false
}

const onSave = async () => {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return

  loading.value = true
  try {
    await saveConfig('edit', {
      sms_api_url: form.sms_api_url,
      sms_api_key: form.sms_api_key,
      sms_sign_id: form.sms_sign_id,
      sms_template_id: form.sms_template_id,
      sms_active_provider: form.sms_active_provider || 'default',
      sms_grayscale_providers: form.sms_grayscale_providers,
    })
    ElMessage.success('配置保存成功')
    configured.value = true
  } catch (e: any) {
    ElMessage.error(e?.message || '保存失败')
  }
  loading.value = false
}

const onTestSend = async () => {
  if (!testForm.test_mobile) {
    ElMessage.warning('请输入测试手机号')
    return
  }

  testLoading.value = true
  testResult.value = null
  try {
    await testSend({
      test_mobile: testForm.test_mobile,
      sms_api_url: form.sms_api_url,
      sms_api_key: form.sms_api_key,
      sms_sign_id: form.sms_sign_id,
      sms_template_id: form.sms_template_id,
    })
    testResult.value = { success: true, message: '测试短信发送成功' }
  } catch (e: any) {
    testResult.value = { success: false, message: e?.message || '发送失败' }
  }
  testLoading.value = false
}

onMounted(() => {
  loadConfig()
})
</script>

<style scoped lang="scss">
.sms-config {
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
