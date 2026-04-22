<script setup>
import { use } from 'echarts/core'
import { BarChart } from 'echarts/charts'
import { GridComponent } from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'
import { ref, computed } from 'vue'
import VChart from "vue-echarts"

use([GridComponent, BarChart, CanvasRenderer])

const props = defineProps({
    chart_name: String,
    subtitle: String,
    bucket_size: Number,
    bucket_amount: Number,
    data: Object,   // Expected to contain packet_amount and buckets as packet_size (size ranges)
})

const difference = props.bucket_size / props.bucket_amount

function compute_buckets(){
    var buckets = []
    var i = 0
    props.data.forEach(amount => {
        var start = props.bucket_size - (i * difference)
        var end = start - difference
        buckets.push(start + '-' + end)
        i++
    })
    return buckets
}

function compute_packet_amount(){
    var packets = []
    props.data.forEach(amount => {
        packets.push(amount.packet_amount)
    })
    return packets
}

const buckets = compute_buckets()

const packet_amount = compute_packet_amount()

const histogram = ref({
    title: {
        text: props.chart_name,
        subtext: props.subtitle ? props.subtitle : "",
        left: 'center'
    },
    xAxis: {
        type: 'category',
        data: buckets,
        name: 'Size Range',
        nameLocation: 'center',
        nameGap: 50,

    },
    yAxis: {
        name: 'Amount',
        nameLocation: 'center',
        type: 'value'
    },
    series: [
        {
            data: packet_amount,
            type: 'bar',
            label: {
                show: true,
                position: 'top',
            }
        }
    ]
})
</script>

<template>
    <VChart :option="histogram" autoresize class="chart"/>
</template>

<style scoped>
.chart {
  height: 600px;
}
</style>