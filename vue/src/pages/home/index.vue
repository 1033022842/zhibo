<template>
  <div class="home-index" id="home-index">
    <SlideHorizontal name="main" v-model:index="state.baseIndex">
      <SlideItem>
        <LongVideo :active="state.baseIndex === 0" />
        <BaseFooter v-bind:init-tab="0" />
      </SlideItem>
      <SlideItem>
        <UserPanel
          ref="userPanelRef"
          :active="state.baseIndex === 1"
          @back="state.baseIndex = 0"
        />
      </SlideItem>
    </SlideHorizontal>

    <BaseMask v-if="!isMobile" @click="isMobile = true" />
    <div v-if="!isMobile" class="guide">
      <Icon class="danger" icon="mynaui:danger-triangle" />
      <Icon class="close" icon="simple-line-icons:close" @click="isMobile = true" />
      <div class="txt">
        <h2>切换至手机模式获取最佳体验</h2>
        <h3>1. 按 F12 调出控制台</h3>
        <h3>2. 按 Ctrl+Shift+M，或点击下面图标</h3>
      </div>
      <img src="@/assets/img/guide.png" alt="" />
    </div>
  </div>
</template>

<script setup lang="ts">
import SlideHorizontal from '@/components/slide/SlideHorizontal.vue'
import SlideItem from '@/components/slide/SlideItem.vue'
import { onActivated, onDeactivated, onMounted, onUnmounted, reactive, ref } from 'vue'
import bus, { EVENT_KEY } from '@/utils/bus'
import { useNav } from '@/utils/hooks/useNav'
import UserPanel from '@/components/UserPanel.vue'
import LongVideo from '@/pages/home/slide/LongVideo.vue'
import BaseMask from '@/components/BaseMask.vue'

const nav = useNav()
const isMobile = ref(/Mobi|Android|iPhone/i.test(navigator.userAgent))
const userPanelRef = ref()

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
    state.baseIndex = 1
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

.guide {
  color: white;
  z-index: 999;
  background: var(--active-main-bg);
  position: fixed;
  left: 50%;
  top: 50%;
  transform: translate(-50%, -50%);
  border-radius: 16rem;
  overflow: hidden;
  text-align: center;

  .danger {
    margin-top: 10rem;
    font-size: 40rem;
    color: red;
  }

  .close {
    cursor: pointer;
    font-size: 18rem;
    color: white;
    position: absolute;
    right: 15rem;
    top: 15rem;
  }

  .txt {
    text-align: left;
    padding: 0 24rem;
  }

  img {
    display: block;
    width: 350rem;
  }
}
</style>
