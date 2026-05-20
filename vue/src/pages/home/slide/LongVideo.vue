<script setup lang="ts">
import { feedLive, liveRoomDetail, type LiveRoom } from '@/api/live'
import SlideVerticalInfinite from '@/components/slide/SlideVerticalInfinite.vue'
import { useBaseStore } from '@/store/pinia'
import bus, { EVENT_KEY } from '@/utils/bus'
import { slideItemRender } from '@/utils'
import { onMounted, onUnmounted, reactive, ref, watch } from 'vue'

interface LiveFeedCard extends LiveRoom {
  aweme_id: string
  desc: string
  author: {
    uid: string
    nickname: string
    signature: string
    avatar_168x168: {
      url_list: string[]
    }
  }
  statistics: {
    digg_count: number
    comment_count: number
    collect_count: number
    share_count: number
  }
  play_loaded?: boolean
  play_loading?: boolean
}

const props = defineProps({
  active: {
    type: Boolean,
    default: false
  }
})

const baseStore = useBaseStore()
const render = slideItemRender({ isLive: true })
const listRef = ref<InstanceType<typeof SlideVerticalInfinite> | null>(null)

const state = reactive({
  index: 0,
  list: [] as LiveFeedCard[],
  cursor: '' as string,
  hasMore: true,
  pageSize: 10,
  initialized: false
})

function normalizeLiveRoom(item: LiveRoom, oldItem?: Partial<LiveFeedCard>): LiveFeedCard {
  const personaName = item.persona?.name || item.title || '官方直播间'
  const coverUrl = item.cover_url || oldItem?.cover_url || ''

  return {
    ...oldItem,
    ...item,
    aweme_id: oldItem?.aweme_id || `live-${item.room_id}`,
    desc: item.subtitle || item.title,
    author: {
      uid: String(item.room_id),
      nickname: personaName,
      signature: item.subtitle || '',
      avatar_168x168: {
        url_list: [coverUrl]
      }
    },
    statistics: {
      digg_count: 0,
      comment_count: 0,
      collect_count: 0,
      share_count: 0
    },
    play_loaded: oldItem?.play_loaded ?? false,
    play_loading: false
  }
}

async function loadFeed(refresh = false) {
  if (baseStore.loading) return
  if (!refresh && !state.hasMore) return

  baseStore.loading = true
  const cursor = refresh ? '' : state.cursor
  const res = await feedLive({
    cursor,
    limit: state.pageSize
  })
  baseStore.loading = false

  if (!res.success) return

  const result = res.data
  const currentList = refresh ? [] : state.list.slice()
  const mappedList = result.list.map((item) => {
    const oldItem = currentList.find((current) => current.room_id === item.room_id)
    return normalizeLiveRoom(item, oldItem)
  })

  state.cursor = result.cursor || ''
  state.hasMore = !!result.has_more
  state.list = refresh ? mappedList : currentList.concat(mappedList)
  state.initialized = true

  preloadVisibleRooms()
}

async function ensurePlayInfo(index: number) {
  const room = state.list[index]
  if (!room || room.play_loaded || room.play_loading) return

  room.play_loading = true
  const res = await liveRoomDetail(room.room_id)
  room.play_loading = false
  if (!res.success) return

  state.list[index] = normalizeLiveRoom(res.data, {
    ...room,
    play_loaded: true
  })
}

function preloadVisibleRooms() {
  if (!props.active || !state.list.length) return
  ensurePlayInfo(state.index)
  ensurePlayInfo(state.index + 1)
}

function loadMore() {
  if (!baseStore.loading) {
    loadFeed()
  }
}

function click(uniqueId: string) {
  if (!props.active || uniqueId !== 'home-live-feed') return
  bus.emit(EVENT_KEY.SINGLE_CLICK_BROADCAST, {
    uniqueId,
    index: state.index,
    type: EVENT_KEY.ITEM_TOGGLE
  })
}

function updateItem({ position, item }) {
  if (position.uniqueId === 'home-live-feed') {
    state.list[position.index] = item
  }
}

watch(
  () => state.index,
  () => {
    preloadVisibleRooms()
  }
)

watch(
  () => props.active,
  (active) => {
    if (!active) return
    if (!state.initialized) {
      loadFeed(true)
      return
    }
    preloadVisibleRooms()
  },
  { immediate: true }
)

onMounted(() => {
  bus.on(EVENT_KEY.SINGLE_CLICK, click)
  bus.on(EVENT_KEY.UPDATE_ITEM, updateItem)
})

onUnmounted(() => {
  bus.off(EVENT_KEY.SINGLE_CLICK, click)
  bus.off(EVENT_KEY.UPDATE_ITEM, updateItem)
})
</script>

<template>
  <div class="long-video">
    <SlideVerticalInfinite
      ref="listRef"
      v-love="'home-live-feed'"
      id="home-live-feed"
      uniqueId="home-live-feed"
      name="home-live-feed"
      :active="props.active"
      :loading="baseStore.loading"
      v-model:index="state.index"
      :render="render"
      :list="state.list"
      @loadMore="loadMore"
      @refresh="loadFeed(true)"
    />
    <div v-if="state.initialized && !state.list.length && !baseStore.loading" class="empty">
      暂无直播内容
    </div>
  </div>
</template>

<style scoped lang="less">
.long-video {
  position: relative;
  width: 100%;
  height: calc(var(--vh, 1vh) * 100 - var(--footer-height));
  background: #000;
  overflow: hidden;

  .empty {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: rgba(255, 255, 255, 0.75);
    font-size: 14rem;
    padding: 10rem 18rem;
    border-radius: 999rem;
    background: rgba(255, 255, 255, 0.1);
  }
}
</style>
