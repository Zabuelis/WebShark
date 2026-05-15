<script setup>
import { ref, computed, watch, onUnmounted } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import { RecycleScroller } from 'vue-virtual-scroller'
import 'vue-virtual-scroller/dist/vue-virtual-scroller.css'
import NavBar from '../components/NavBar.vue'
import Footer from '../components/Footer.vue'
import ProtocolDistributionPieChart from '@/components/ProtocolDistributionPieChart.vue'
import TopTalkersBarChart from '@/components/TopTalkersBarChart.vue'
import PacketSizeHistogram from '@/components/PacketSizeHistogram.vue'
import PacketList from '@/components/PacketList.vue'

const props = defineProps({
    total_packets: { type: Number, default: 0 },
    status: String,
    l7_status: String,
    message: String,
    queue_position: Number,
    id: String,
    progress: Number,
    total_bytes: Number,
    first_packet_time: Number,
    last_packet_time: Number,
    expires_at: String,
    l3_distribution: Object,
    l4_distribution: Object,
    l7_distribution: Object,
    top_talkers: Object,
    size_distribution: Object,
    total_flows: Number,
})

let expiryInterval = null
const totalItems = ref(props.total_packets ?? 0)

function startExpiryPolling() {
    if (expiryInterval) return
    expiryInterval = setInterval(() => {
        router.reload({ only: ['expires_at'] })
    }, 60_000) // refresh every minute
}

function stopExpiryPolling() {
    if (expiryInterval) {
        clearInterval(expiryInterval)
        expiryInterval = null
    }
}

onUnmounted(() => {
    stopPolling()
    stopExpiryPolling()
})

const showToast = ref(false)
const copyUrl = () => {
    const textToCopy = window.location.href;

    // Try the modern Clipboard API
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(textToCopy)
            .then(() => {
                showToast.value = true;
                setTimeout(() => { showToast.value = false; }, 2000);
            })
            .catch(err => console.error('Failed to copy: ', err));
    } 
    // Fallback for insecure contexts (HTTP)
    else {
        const textArea = document.createElement("textarea");
        textArea.value = textToCopy;
        
        // Ensure the textarea is not visible but part of the DOM
        textArea.style.position = "fixed";
        textArea.style.left = "-9999px";
        textArea.style.top = "0";
        document.body.appendChild(textArea);
        
        textArea.focus();
        textArea.select();

        try {
            const successful = document.execCommand('copy');
            if (successful) {
                showToast.value = true;
                setTimeout(() => { showToast.value = false; }, 2000);
            } else {
                console.error('Fallback copy command failed');
            }
        } catch (err) {
            console.error('Fallback copy failed: ', err);
        }

        document.body.removeChild(textArea);
    }
}

const captureDuration = computed(() => {
    if (!props.first_packet_time || !props.last_packet_time) return "0.00"
    return (props.last_packet_time - props.first_packet_time).toFixed(3)
})

const activeTab = ref('packets') // Options: 'packets', 'overview', 'conversations'

// Calculate total MB from packets
const totalMB = computed(() => {
    if (!props.total_bytes) return "0.00"
    return (props.total_bytes / (1024 * 1024)).toFixed(2)
})

// Polling and init

let pollingInterval = null

function startPolling() {
    if (pollingInterval) return
    pollingInterval = setInterval(() => {
        router.reload({ 
            only: 
                [
                    'status', 'l7_status', 'message', 'total_bytes', 'id', 'first_packet_time',
                    'last_packet_time', 'progress', 'expires_at', 'total_packets', 'total_flows',
                    'l7_distribution', 'l3_distribution', 'l4_distribution', 'top_talkers', 'size_distribution',
                    'queue_position'
                ],
        })
    }, 500)
}

function stopPolling() {
    if (pollingInterval) {
        clearInterval(pollingInterval)
        pollingInterval = null
    }
}

watch(
    () => [props.status, props.l7_status],
    async ([newStatus, newL7Status]) => {
        if (newStatus === 'dispatching' || newL7Status === 'dispatching') {
            startPolling()
        }
        if (newStatus === 'finished') {
            if (newL7Status !== 'dispatching') {
                stopPolling()
                totalItems.value = props.total_packets
                startExpiryPolling()
            }
        }
        if (newStatus === 'failed') {
            stopPolling()
        }
    },
    { immediate: true }
)
</script>


<template>
    <Head title="Analyzing Packets..." />

    <!-- Show this only if the status is 'dispatching' -->
    <div v-if="props.status === 'dispatching'" 
         class="fixed inset-0 z-50 flex flex-col items-center justify-center text-center px-6">
        
        <!-- The Content -->
        <div class="relative z-10 flex flex-col items-center">

            <!-- Spinner -->
            <div class="w-16 h-16 border-4 border-blue-400/20 border-t-blue-400 rounded-full animate-spin mb-8"></div>
            
            <!-- Status Message -->
            <p class="text-slate-800 font-mono text-sm max-w-md leading-relaxed flex flex-col">
                <div v-if="props.queue_position !== 0">
                    Status: <span class="animate-pulse">Waiting in queue...</span><br>
                    Queue Position: <span class="animate-pulse">{{ props.queue_position }}</span>
                </div>
                <div v-else>
                    Status: <span class="animate-pulse">{{ props.message }}</span>
                </div>
            </p>

            <!-- Progress Bar -->
            <div class="w-64 h-2 rounded-full overflow-hidden border border-slate-400">
                <div class="bg-blue-400 h-full transition-all duration-500 ease-out" 
                     :style="{ width: props.progress + '%' }">
                </div>
            </div>
            <p class="text-slate-800 text-xs mt-2 font-mono">{{ props.progress }}% processed</p>

        </div>
    </div>

    <!-- Show this only if the status is 'failed' -->
    <div v-else-if="props.status === 'failed'" class="flex flex-col items-center justify-center h-screen text-white">
        <p class="text-slate-800 mt-2">{{ props.message }}</p>
        <Link :href="route('home')" class="mt-6 px-4 py-2 bg-blue-600 rounded-lg">
            Go back to home page
        </Link>
    </div>

    <!-- Show this only if the status is 'completed' -->
    <div v-else class="h-screen flex flex-col bg-slate-50 overflow-hidden font-sans">
        <Head title="Analysis page" />

        <NavBar/>

        <!-- Spinner to indicate L7 parsing -->
        <div v-if="props.l7_status === 'dispatching'" class="bg-sky-100 flex gap-4 border border-sky-400 text-start text-sky-700 pl-6 py-2 justify-center items-center" role="status">
            <span class="font-bold text-sm">Analyzing Application Layer Data</span>
                <div class="w-6 h-6 border-4 border-blue-500/20 border-t-blue-500 rounded-full animate-spin"></div>
        </div>

        <!-- Show this if L7 analytics failed -->
        <div v-if="props.l7_status === 'failed'" class="bg-sky-100 border border-sky-400 text-center text-sky-700 py-2 " role="alert">
            <span class="font-bold text-sm">{{ props.message }}</span>
        </div>

        <!-- Expiration Notice -->
        <div v-if="expires_at" class="px-6 py-2 bg-amber-50 text-amber-700 text-xs border-b border-amber-100 flex items-center justify-between">
            
            <span>
                Analysis link expires in: <strong>{{ expires_at }}</strong>
            </span>

            <button 
                @click="copyUrl"
                class="flex items-center gap-1.5 px-2 py-1 bg-amber-100 hover:bg-amber-200 text-amber-800 rounded border border-amber-200 transition-colors font-medium"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                </svg>
                Copy Link
            </button>
        </div>

        <!-- Tab bar -->
        <div class="bg-white border-b border-slate-200 px-6 flex gap-8 shrink-0">
            <button 
                v-for="tab in ['packets', 'overview', 'conversations']" 
                :key="tab"
                @click="activeTab = tab"
                :class="[
                    'py-3 text-sm font-bold capitalize transition-all border-b-2',
                    activeTab === tab ? 'text-blue-600 border-blue-600' : 'text-slate-400 border-transparent hover:text-slate-600'
                ]"
            >
                {{ tab }}
            </button>
        </div>

        <!-- The main area -->
        <div class="flex flex-1 overflow-hidden">

            <!-- For the packets tab -->
            <PacketList  v-if="activeTab === 'packets'" :first_packet_time="props.first_packet_time" :url="`/pcap/analysis/${props.id}/packets`" :status="props.status" :l7_status="props.l7_status" />

            <!-- For the overview tab -->
            <div v-else-if="activeTab === 'overview'" class="flex-1 overflow-y-auto p-8 bg-slate-50">
                <div class="max-w-6xl mx-auto">
                    <h2 class="text-2xl font-bold text-slate-900 mb-6">Traffic Overview</h2>
                    
                    <!-- Status cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                            <p class="text-xs font-bold text-slate-400 uppercase">Total Data</p>
                            <p class="text-3xl font-black text-blue-600">
                                {{ totalMB }} <span class="text-xl text-slate-400">MB</span>
                            </p>
                        </div>

                        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                            <div class="flex flex-row justify-between">
                                <p class="text-xs font-bold text-start text-slate-400 uppercase">Packets Captured</p>
                                <p class="text-xs font-bold text-end text-slate-400 uppercase">Total TCP Flows</p>
                            </div>
                            <div class="flex flex-row justify-between">
                                <p class="text-3xl flex text-start font-black text-blue-600">{{ totalItems.toLocaleString() }}</p>
                                <p class="text-3xl flex text-end font-black text-blue-600">{{ props.total_flows }}</p>
                            </div>
                        </div>

                        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                            <p class="text-xs font-bold text-slate-400 uppercase">Capture Duration</p>
                            <p class="text-3xl font-black text-blue-600">
                                {{ captureDuration }} <span class="text-xl text-slate-400">sec</span>
                            </p>
                        </div>

                    </div>
                    <!-- Protocol distribution PieCharts -->
                    <div class="grid grid-cols-1 pt-6 md:grid-cols-3 gap-6">
                        <div v-if="Object.keys(props.l3_distribution).length !== 0" class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                            <ProtocolDistributionPieChart chart_name="Network Layer" subtitle="Network Layer Protocol Distribution" :data="props.l3_distribution" />
                        </div>

                        <div v-if="Object.keys(props.l4_distribution).length !== 0" class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                            <ProtocolDistributionPieChart chart_name="Transport Layer" subtitle="Transport Layer Protocol Distribution" :data="props.l4_distribution" />
                        </div>

                        <div v-if="Object.keys(props.l7_distribution).length !== 0" class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                            <ProtocolDistributionPieChart chart_name="Application Layer" subtitle="Application Layer Protocol Distribution" :data="props.l7_distribution" />
                        </div>
                    </div>

                    <!-- Hosts with most packets sent histogram -->
                    <div class="pt-6">
                        <div v-if="Object.keys(props.top_talkers).length !== 0" class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                            <TopTalkersBarChart chart_name="Top Talkers" subtitle="Hosts who Sent the Most Packets" :data="props.top_talkers"></TopTalkersBarChart>
                        </div>
                    </div>

                    <!-- Packet size distribution histogram -->
                    <div class="pt-6">
                        <div v-if="Object.keys(props.size_distribution).length !== 0" class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                            <PacketSizeHistogram chart_name="Packet Size Distribution" :first_packet_time="props.first_packet_time" :data="props.size_distribution" :bucket_amount="10" :bucket_size="1500" />
                        </div>
                    </div>

                </div>
            </div>

            <!-- For the conversations tab -->
            <PacketList  v-if="activeTab === 'conversations'" :first_packet_time="props.first_packet_time" :url="`/pcap/analysis/${props.id}/flows`" :status="props.status" :l7_status="props.l7_status" />


        </div>

        <!-- Toast -->
        <transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="transform translate-y-2 opacity-0"
            enter-to-class="transform translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="transform translate-y-0 opacity-100"
            leave-to-class="transform translate-y-2 opacity-0"
        >
            <div v-if="showToast" 
                class="fixed bottom-6 right-6 z-50 bg-slate-800 text-white px-4 py-2 rounded-lg shadow-xl flex items-center gap-2 text-sm font-medium">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Link copied to clipboard
            </div>
        </transition>
        
    </div>

</template>
