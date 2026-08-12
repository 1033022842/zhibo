<template>
    <div class="iframe-main" v-loading="state.loading">
        <iframe :src="state.iframeSrc" :style="iframeStyle(35)" height="100%" width="100%" id="iframe" @load="hideLoading"></iframe>
    </div>
</template>

<script setup lang="ts">
import { reactive } from 'vue'
import { useRouter } from 'vue-router'
import { mainHeight as iframeStyle } from '/@/utils/layout'

const router = useRouter()

const resolveIframeSrc = (url: string): string => {
    if (!url) return ''
    // 已经是完整 URL 则直接返回
    if (/^https?:\/\//i.test(url)) return url
    // 相对路径：开发环境补上后端地址，线上环境直接使用（同域）
    const apiBase: string = import.meta.env.VITE_AXIOS_BASE_URL as string
    if (apiBase && apiBase !== 'getCurrentDomain') {
        return apiBase.replace(/\/+$/, '') + url
    }
    return url
}

const state = reactive({
    loading: true,
    iframeSrc: resolveIframeSrc(router.currentRoute.value.meta.url as string),
})

const hideLoading = () => {
    state.loading = false
}
</script>

<style scoped lang="scss">
.iframe-main {
    margin: var(--ba-main-space);
    iframe {
        border: none;
    }
}
</style>
