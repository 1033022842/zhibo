<template>
  <div id="Test">
    <base-button type="primary" @click="t = true">test</base-button>
  </div>
</template>
<script setup lang="ts">
import BaseButton from '../../components/BaseButton.vue'
import { ref } from 'vue'

const t = ref(false)
</script>

<style scoped lang="less">
@import '../../assets/less/index';

#Test {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  top: 0;
  overflow: auto;
  font-size: 1.4rem;
  color: white;
}
</style>
