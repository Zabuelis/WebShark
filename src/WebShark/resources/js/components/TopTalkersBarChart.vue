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

function compute_packet_amount(){
    var data = []
    props.data.forEach(record => {
        data.push(record.records)
    })
    return data
}

function compute_IP_addresses(){
    var data = []
    props.data.forEach(record => {
        data.push(record.IP)
    })
    return data
}

const packet_amount = compute_packet_amount()

const IP_addresses = compute_IP_addresses()

const bar_chart = ref({
    title: {
        text: props.chart_name,
        subtext: props.subtitle ? props.subtitle : "",
        left: 'center'
    },
    xAxis: {
        type: 'value',
        name: 'Amount',
        nameLocation: 'center',
        nameGap: 50
    },
    yAxis: {
        type: 'category',
        data: IP_addresses,
        inverse: true
    },
    series: [
        {
            data: packet_amount,
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