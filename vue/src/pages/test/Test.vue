<template>
  <div class="body">
    <LongVideo :active="true" />
  </div>
</template>
<script setup lang="ts">
import LongVideo from '@/pages/home/slide/LongVideo.vue'

defineOptions({
  name: 'Test'
})
</script>
<style scoped lang="less"></style>
