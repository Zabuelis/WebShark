<script setup>
import { use } from 'echarts/core'
import VChart from "vue-echarts"
import { ref, computed } from 'vue'
import { BarChart } from 'echarts/charts'
import {
  TitleComponent,
  TooltipComponent,
  LegendComponent,
  GridComponent
} from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'

use([
  TitleComponent,
  TooltipComponent,
  LegendComponent,
  GridComponent,
  BarChart,
  CanvasRenderer
])


use([GridComponent, BarChart, CanvasRenderer])

const props = defineProps({
    chart_name: String,
    subtitle: String,
    data: Object,
})

const packet_amount = computed(() => {
    var data = []
    props.data.forEach(record => {
        data.push(record.records)
    })
    return data
})

const IP_addresses = computed(() => {
    var data = []
    props.data.forEach(record => {
        data.push(record.IP)
    })
    return data
})

console.log(IP_addresses.value)
console

const bar_chart = ref({
    title: {
        text: props.chart_name,
        subtext: props.subtitle ? props.subtitle : "",
        left: 'center'
    },
    xAxis: {
        type: 'value',
        name: 'Packet Amount',
        nameLocation: 'center',
        nameGap: 50
    },
    yAxis: {
        type: 'category',
        data: IP_addresses.value,
        inverse: true
    },
    series: [
        {
            data: packet_amount.value,
            type: 'bar',
            label: {
                show: true,
                position: 'right',
                distance: 20
            }
        }
    ]
})

</script>

<template>
    <VChart :option="bar_chart" autoresize class="chart"/>
</template>

<style scoped>
.chart {
    height: 600px;
}
</style>