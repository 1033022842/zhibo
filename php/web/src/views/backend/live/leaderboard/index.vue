<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" title="角色收入排行榜：按累计礼物钻石收入降序排列" type="info" show-icon />
        <TableHeader :buttons="['refresh', 'columnDisplay']" />
        <Table />
    </div>
</template>

<script setup lang="ts">
import { provide } from 'vue'
import baTableClass from '/@/utils/baTable'
import Table from '/@/components/table/index.vue'
import TableHeader from '/@/components/table/header/index.vue'
import { baTableApi } from '/@/api/common'

defineOptions({
    name: 'live/leaderboard',
})

const baTable = new baTableClass(
    new baTableApi('/admin/live.Leaderboard/'),
    {
        column: [
            {
                label: '排名',
                prop: 'rank',
                align: 'center',
                width: 80,
                operator: false,
            },
            { label: '角色名称', prop: 'persona_name', align: 'center', operator: 'LIKE' },
            { label: '房间号', prop: 'room_no', align: 'center', operator: 'LIKE', width: 150 },
            {
                label: '累计礼物钻石',
                prop: 'total_gift',
                align: 'center',
                operator: 'RANGE',
                width: 160,
            },
            {
                label: '送礼人次',
                prop: 'donor_count',
                align: 'center',
                operator: 'RANGE',
                width: 120,
            },
        ],
        dblClickNotEditColumn: [undefined],
    },
    {
        defaultItems: {},
        pagination: {
            pageSize: 20,
        },
    }
)

baTable.mount()
baTable.getData()
provide('baTable', baTable)
</script>
