<template>
  <div v-if="visible" class="footer" :class="{ isWhite }">
    <div class="l-button" @click="tab('home')">
      <span :class="{ active: currentTab === 'home' }">首页</span>
    </div>
    <div class="l-button" @click="tab('me')">
      <span :class="{ active: currentTab === 'me' }">我</span>
    </div>
  </div>
</template>

<script>
import bus, { EVENT_KEY } from '../utils/bus'

export default {
  name: 'BaseFooter',
  props: ['initTab', 'isWhite'],
  data() {
    return {
      currentTab: this.resolveCurrentTab(this.$route.path),
      visible: true
    }
  },
  watch: {
    $route(to) {
      this.currentTab = this.resolveCurrentTab(to.path)
    }
  },
  created() {
    bus.on('setFooterVisible', (e) => (this.visible = e))
    bus.on(EVENT_KEY.ENTER_FULLSCREEN, () => (this.visible = false))
    bus.on(EVENT_KEY.EXIT_FULLSCREEN, () => (this.visible = true))
  },
  unmounted() {
    bus.off(EVENT_KEY.ENTER_FULLSCREEN)
    bus.off(EVENT_KEY.EXIT_FULLSCREEN)
  },
  methods: {
    resolveCurrentTab(path) {
      if (path.startsWith('/me')) return 'me'
      if (path.startsWith('/home') || path === '/') return 'home'
      return ''
    },
    $nav(path) {
      this.$router.push(path)
    },
    tab(tabName) {
      switch (tabName) {
        case 'home':
          this.$nav('/home')
          break
        case 'me':
          this.$nav('/me')
          break
      }
    }
  }
}
</script>

<style scoped lang="less">
@import '../assets/less/index';

.footer {
  font-size: 14rem;
  position: fixed;
  width: 100%;
  height: var(--footer-height);
  //border-top: 1px solid #7b7878;
  z-index: 2;
  //不用bottom：0是因为，在进行页面切换的时候，vue的transition
  // 会使footer的bottom：0失效，不能准确定位
  top: calc(var(--vh, 1vh) * 100 - var(--footer-height));
  //bottom: 0;
  background: var(--footer-color);
  color: white;
  display: flex;
  justify-content: space-around;

  &.isWhite {
    background: white !important;
    color: #000 !important;
  }

  .l-button {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    position: relative;
    font-size: 16rem;

    span {
      cursor: pointer;
      font-weight: bold;
      opacity: 0.7;

      &.active {
        opacity: 1;
      }
    }
  }
}
</style>
