<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" title="角色收益明细：按角色聚合的礼物钻石收入" type="info" show-icon />
        <TableHeader :buttons="['refresh', 'comSearch', 'quickSearch', 'columnDisplay']" />
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
    name: 'live/revenue',
})

const baTable = new baTableClass(
    new baTableApi('/admin/live.Revenue/'),
    {
        column: [
            { label: '角色名称', prop: 'persona_name', align: 'center', operator: 'LIKE' },
            { label: '房间号', prop: 'room_no', align: 'center', operator: 'LIKE', width: 150 },
            {
                label: '累计礼物钻石',
                prop: 'total_gift',
                align: 'center',
                operator: 'RANGE',
                width: 160,
                render: 'default',
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
    }
)

baTable.mount()
baTable.getData()
provide('baTable', baTable)
</script>
