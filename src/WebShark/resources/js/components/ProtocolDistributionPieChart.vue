<script setup>
import { use } from 'echarts/core'
import { PieChart } from 'echarts/charts'
import {
	TitleComponent,
	TooltipComponent,
	LegendComponent
} from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'
import VChart from "vue-echarts"
import { ref, computed } from 'vue'

use([
	TitleComponent,
	TooltipComponent,
	LegendComponent,
	PieChart,
	CanvasRenderer
])

const props = defineProps({
	chart_name: String,
	subtitle: String,
	data: Object,   // Expected to contain protocol_name and records
})

const minimum_precentage = 0.01
const minimum_fields = 3

// If there are more than minimum_fields different fields and 
// it takes up less than set precentage threshold aggregate them and display as others
const total_records = computed(() => {
	var i = 0
	props.data.forEach(record => {
		i += record.records
	})
	return i
})

const others = computed(() => {
	var i = 0
	if(props.data.length > minimum_fields){
		props.data.forEach(record => {
		if(record.records / total_records.value < minimum_precentage){
			i+=record.records
		}
		})
		return i
	}
})

const chartData = computed(() => {
	var data=[]
	props.data.forEach(record => {
		if(others.value > 0){
			if(record.records / total_records.value > minimum_precentage){
				data.push({ value: record.records, name: record.protocol_name})
			}
		} else {
			data.push({ value: record.records, name: record.protocol_name})
		}
	})
  	if(others.value > 0){
		data.push({ value: others.value, name: "Others" })
  	}
  	return data
})

const pie_chart = ref({
  	title: {
    	text: props.chart_name,
    	subtext: props.subtitle ? props.subtitle : "",
    	left: 'center'
  	},
  	tooltip: {
	    trigger: 'item'
  	},
  	legend: {
	    orient: 'vertical',
	    left: 'left'
  	},
  	series: [
		{
			name: props.subtitle ? props.subtitle : "",
			type: 'pie',
			radius: '50%',
			data: chartData,
			emphasis: {
				itemStyle: {
				shadowBlur: 10,
				shadowOffsetX: 0,
				shadowColor: 'rgba(0, 0, 0, 0.5)'
				}
			}
		}
	]
})

</script>

<template>
  	<VChart :option="pie_chart" autoresize class="chart"/>
</template>

<style scoped>
.chart {
  	height: 400px;
}
</style>