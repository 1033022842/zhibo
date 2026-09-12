<template>
  <div class="home-index" id="home-index">
    <!-- 废弃的横向第二页（UserPanel）已移除，仅保留直播 feed，杜绝左右滑动 -->
    <LongVideo :active="state.active" />
    <BaseFooter v-bind:init-tab="0" />
  </div>
</template>

<script setup lang="ts">
import { onActivated, onDeactivated, onMounted, onUnmounted, reactive, ref } from 'vue'
import bus, { EVENT_KEY } from '@/utils/bus'
import { useNav } from '@/utils/hooks/useNav'
import LongVideo from '@/pages/home/slide/LongVideo.vue'
const nav = useNav()

const state = reactive({
  active: true,
  baseIndex: 0,
  fullScreen: false,
  currentItem: {} as any
})

function setCurrentItem(item: any) {
  if (!state.active) return
  if (state.baseIndex !== 0) return
  state.currentItem = item
}

onMounted(() => {
  bus.on(EVENT_KEY.ENTER_FULLSCREEN, () => {
    if (!state.active) return
    state.fullScreen = true
  })
  bus.on(EVENT_KEY.EXIT_FULLSCREEN, () => {
    if (!state.active) return
    state.fullScreen = false
  })
  bus.on(EVENT_KEY.NAV, ({ path, query }: { path: string; query?: any }) => {
    if (!state.active) return
    nav(path, query)
  })
  bus.on(EVENT_KEY.GO_USERINFO, () => {
    if (!state.active) return
    nav('/me')
  })
  bus.on(EVENT_KEY.CURRENT_ITEM, setCurrentItem)
})

onUnmounted(() => {
  bus.offAll()
})

onActivated(() => {
  state.active = true
})

onDeactivated(() => {
  state.active = false
})
</script>

<style scoped lang="less">
.home-index {
  font-size: 14rem;
  width: 100%;
  height: 100%;
  background: black;
  overflow: hidden;
}

</style>
