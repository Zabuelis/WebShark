<script setup>
import { use } from 'echarts/core'
import { ScatterChart } from 'echarts/charts'
import { GridComponent } from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'

use([GridComponent, ScatterChart, CanvasRenderer])
import { ref, computed } from 'vue'
import VChart from "vue-echarts"

const props = defineProps({
    chart_name: String,
    subtitle: String,
    data: Object,
})

const chartData = computed(() => {
    var data = []
    props.data.forEach(record => {
        data.push([record.packet_number, record.captured_packet_length])
    })
    return data
})

const scatter_chart = ref({
    title: {
        text: props.chart_name,
        subtext: props.subtitle ? props.subtitle : "",
        left: 'center'
    },
    xAxis: {
        name: "Packet number",
        name_location: "middle"
    },
    yAxis: {
        name: "Packet size",
        name_location: "middle"
    },
    series: [
    {
        symbolSize: 8,
        data: chartData,
        type: 'scatter'
    }]
})
</script>

<template>
    <VChart :option="scatter_chart" class="chart"/>
</template>

<style scoped>
.chart {
    height: 600px;
}
</style>