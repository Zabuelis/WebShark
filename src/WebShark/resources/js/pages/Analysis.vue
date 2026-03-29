<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import { RecycleScroller } from 'vue-virtual-scroller'
import 'vue-virtual-scroller/dist/vue-virtual-scroller.css'
import NavBar from '../components/NavBar.vue'

const props = defineProps({
    total_packets: { type: Number, default: 0 },
    status: String,
    message: String,
    id: String,
    progress: Number,
    total_bytes: Number,
    first_packet_time: Number,
    last_packet_time: Number,
    expires_at: String
})

const showToast = ref(false)

const copyUrl = () => {
    navigator.clipboard.writeText(window.location.href)
        .then(() => {
            showToast.value = true
            setTimeout(() => { showToast.value = false }, 2000)
        })
        .catch(err => console.error('Failed to copy: ', err))
}

const captureDuration = computed(() => {
    if (!props.first_packet_time || !props.last_packet_time) return "0.00"
    return (props.last_packet_time - props.first_packet_time).toFixed(3)
})

const formatTime = (packetTimestamp) => {
    if (!packetTimestamp || !props.first_packet_time) return "0.000000"
    return (parseFloat(packetTimestamp) - props.first_packet_time).toFixed(6)
}

const selectedPacket = ref(null)

const activeTab = ref('packets') // Options: 'packets', 'overview', 'conversations'

const detailSections = computed(() => {
    if (!selectedPacket.value) return []
    const p = selectedPacket.value
    return [
        {
            title: "Frame",
            fields: [
                { label: "ID", value: p.packet_number },
                { label: "Length", value: `${p.captured_packet_length} bytes` },
                { label: "Time", value: `${formatTime(p.timestamp)}s` },
            ]
        },
        {
            title: "Network",
            fields: [
                { label: "L3 Protocol", value: p.l3_protocol },
                { label: "Source IP", value: p.src_ip },
                { label: "Destination IP", value: p.dst_ip },
            ]
        },
        {
            title: "Transport",
            fields: [
                { label: "L4 Protocol", value: p.l4_protocol },
                { label: "Source Port", value: p.src_port },
                { label: "Dest Port", value: p.dst_port },
            ]
        },
        {
            title: "Application",
            fields: [
                { label: "L7 Protocol", value: p.l7_protocol },
                { label: "Info", value: p.info },
            ]
        }
    ]
})

// For protocol badge colors
const getProtoColor = (proto) => {
    const colors = {
        'TCP': 'bg-blue-100 text-blue-700 border-blue-200',
        'UDP': 'bg-purple-100 text-purple-700 border-purple-200',
        'ICMP': 'bg-pink-100 text-pink-700 border-pink-200'
    }
    return colors[proto] || 'bg-slate-100 text-slate-700 border-slate-200'
}

// Function to handle the click
function handlePacketClick(packet) {
    if (packet._placeholder) return
    selectedPacket.value = selectedPacket.value === packet ? null : packet
}

// Calculate total MB from packets
const totalMB = computed(() => {
    if (!props.total_bytes) return "0.00"
    return (props.total_bytes / (1024 * 1024)).toFixed(2)
})

// Virtual scroll data layer
const PER_PAGE = 100
const WINDOW_SIZE = 5
const ROW_HEIGHT = 36

const pageStore = new Map()
const loadedPages = ref(new Set())
const loadingPages = new Set()

const totalPackets = ref(props.total_packets ?? 0)
const totalPages = ref(0)

const packets = ref([])

function rebuildPacketList() {
    const out = []
    for (let p = 1; p <= totalPages.value; p++) {
        const rows = pageStore.get(p)
        if (rows) {
            out.push(...rows)
        } else {
            const start = (p - 1) * PER_PAGE + 1
            const end   = Math.min(p * PER_PAGE, totalPackets.value)
            for (let i = start; i <= end; i++) {
                out.push({ _placeholder: true, packet_number: i })
            }
        }
    }
    packets.value = out
}

async function fetchPage(page) {
    if (page < 1) return
    if (totalPages.value > 0 && page > totalPages.value) return
    if (loadedPages.value.has(page)) return
    if (loadingPages.has(page)) return

    loadingPages.add(page)
    try {
        const response = await fetch(`/pcap/analysis/${props.id}/packets?page=${page}&per_page=${PER_PAGE}`)
        if (!response.ok) throw new Error(`HTTP ${response.status}`)
        const json = await response.json()

        if (totalPackets.value === 0) totalPackets.value = json.total
        if (totalPages.value === 0) totalPages.value = json.total_pages

        pageStore.set(page, json.data)
        loadedPages.value = new Set([...loadedPages.value, page])

        evictDistantPages(page)
        rebuildPacketList()
    } catch (e) {
        console.error('Failed to fetch page', page, e)
    } finally {
        loadingPages.delete(page)
    }
}

function evictDistantPages(currentPage) {
    const half = Math.floor(WINDOW_SIZE / 2)
    const lo = currentPage - half
    const hi = currentPage + half

    for (const p of loadedPages.value) {
        if (p < lo || p > hi) {
            pageStore.delete(p)
            const next = new Set(loadedPages.value)
            next.delete(p)
            loadedPages.value = next
        }
    }
    rebuildPacketList()
}

function onScroll(event) {
    // do not trigger page fetches while a search is active
    if (isSearchActive.value) return

    const scrollTop = event.target.scrollTop
    const rowIndex = Math.floor(scrollTop / ROW_HEIGHT)
    const anchorPage = Math.floor(rowIndex / PER_PAGE) + 1

    fetchPage(anchorPage)
    fetchPage(anchorPage + 1)
    if (anchorPage > 1) fetchPage(anchorPage - 1)
}

// Jump to packet
const scrollerRef = ref(null)
const jumpInput = ref('')
const jumpError = ref('')
const isJumping = ref(false)
const jumpHighlight = ref(null)

async function jumpToPacket() {
    const n = parseInt(jumpInput.value, 10)

    if (!n || n < 1 || (totalPackets.value > 0 && n > totalPackets.value)) {
        jumpError.value = `Enter a number between 1 and ${totalPackets.value.toLocaleString()}`
        return
    }

    jumpError.value = ''
    isJumping.value = true

    const targetPage = Math.ceil(n / PER_PAGE)
    await fetchPage(targetPage)
    fetchPage(targetPage - 1)
    fetchPage(targetPage + 1)

    await nextTick()

    scrollerRef.value?.scrollToItem(n - 1)

    jumpHighlight.value = n
    setTimeout(() => { jumpHighlight.value = null }, 2000)

    isJumping.value = false
}

// server-side search
const filterText = ref('')
const searchResults = ref([]) // packets returned by the server
const searchTotal = ref(0) // total matches in DB (may be > searchResults.length)
const isSearching = ref(false) // true while request is in flight

const isSearchActive = computed(() => filterText.value.trim() !== '')

let searchDebounceTimer = null

// Watch the filter input and fire a search 300ms after the user stops typing
watch(filterText, (newVal) => {
    clearTimeout(searchDebounceTimer)

    if (newVal.trim() === '') {
        searchResults.value = []
        searchTotal.value = 0
        isSearching.value = false // Reset searching state
        return
    }

    isSearching.value = true 

    searchDebounceTimer = setTimeout(() => {
        runSearch(newVal.trim())
    }, 300)
})


async function runSearch(query) {
    isSearching.value = true
    try {
        const response = await fetch(`/pcap/analysis/${props.id}/search?q=${encodeURIComponent(query)}`)
        if (!response.ok) throw new Error(`HTTP ${response.status}`)
        const json = await response.json()

        searchResults.value = json.data
        searchTotal.value   = json.total
    } catch (e) {
        console.error('Search failed', e)
    } finally {
        isSearching.value = false
    }
}

// Polling and init

let pollingInterval = null

function startPolling() {
    if (pollingInterval) return
    pollingInterval = setInterval(() => {
        router.reload({ 
            only: ['status', 'message', 'total_bytes', 'id', 'first_packet_time',
                   'last_packet_time', 'progress', 'expires_at', 'total_packets'],
        })
    }, 500)
}

function stopPolling() {
    if (pollingInterval) {
        clearInterval(pollingInterval)
        pollingInterval = null
    }
}

async function initVirtualList() {
    await fetchPage(1)
    fetchPage(2)
}

watch(
    () => props.status,
    async (newStatus) => {
        if (newStatus === 'dispatching') {
            startPolling()
        }
        if (newStatus === 'finished') {
            stopPolling()
            await initVirtualList()
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
         class="fixed inset-0 z-50 bg-slate-900 flex flex-col items-center justify-center text-center px-6">
        
        <!-- The Content -->
        <div class="relative z-10 flex flex-col items-center">

            <!-- Spinner -->
            <div class="w-16 h-16 border-4 border-blue-500/20 border-t-blue-500 rounded-full animate-spin mb-8"></div>
            
            <!-- Status Message -->
            <p class="text-slate-400 font-mono text-sm max-w-md leading-relaxed">
                Status: <span class="animate-pulse">{{ props.message }}</span>
            </p>

            <!-- Progress Bar -->
            <div class="w-64 bg-slate-800 h-2 rounded-full overflow-hidden border border-slate-700">
                <div class="bg-blue-500 h-full transition-all duration-500 ease-out" 
                     :style="{ width: props.progress + '%' }">
                </div>
            </div>
            <p class="text-slate-500 text-xs mt-2 font-mono">{{ props.progress }}% processed</p>

        </div>
    </div>

    <!-- Show this only if the status is 'failed' -->
    <div v-else-if="props.status === 'failed'" class="flex flex-col items-center justify-center h-screen bg-slate-900 text-white">
        <p class="text-slate-400 mt-2">{{ props.message }}</p>
        <Link :href="route('home')" class="mt-6 px-4 py-2 bg-blue-600 rounded-lg">
            Go back to home page
        </Link>
    </div>

    <!-- Show this only if the status is 'completed' -->
    <div v-else class="h-screen flex flex-col bg-slate-50 overflow-hidden font-sans">
        <Head title="Analysis page" />

        <NavBar/>

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
            <template v-if="activeTab === 'packets'">

                <!-- The left side -->
                <main class="flex-1 flex flex-col overflow-hidden border-r border-slate-200">

                    <!-- Toolbar -->
                    <div class="p-3 bg-white border-b border-slate-200 flex gap-2">
                        <div class="relative flex-1">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                            </span>
                            <input 
                                v-model="filterText" 
                                type="text" 
                                placeholder="Search by IP, Protocol (e.g. 192.168 or TCP)..." 
                                class="w-full pl-10 pr-4 py-2 bg-slate-100 border border-slate-200 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all"
                            />
                            <!-- Spinner inside the search box -->
                            <span v-if="isSearching" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400">
                                <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
                                </svg>
                            </span>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <input
                                v-model="jumpInput"
                                @keydown.enter="jumpToPacket"
                                type="number"
                                min="1"
                                :max="totalPackets || undefined"
                                placeholder="Go to #"
                                :class="[
                                    'w-28 px-3 py-2 bg-slate-100 border rounded-md text-sm font-mono',
                                    'focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all',
                                    jumpError ? 'border-red-400' : 'border-slate-200'
                                ]"
                            />
                            <button
                                @click="jumpToPacket"
                                :disabled="isJumping"
                                class="px-3 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded-md text-sm font-bold transition-colors flex items-center gap-1.5"
                            >
                                <svg v-if="isJumping" class="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
                                </svg>
                                Jump
                            </button>
                        </div>
                    </div>

                    <!-- Jump error -->
                    <div v-if="jumpError" class="px-4 py-1.5 bg-red-50 border-b border-red-100 text-red-600 text-xs shrink-0">
                        {{ jumpError }}
                    </div>

                    <!-- Packet headers -->
                    <div class="packet-row grid gap-x-4 px-6 py-2 bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-400 uppercase tracking-wider shrink-0">
                        <div>ID</div>
                        <div>Time</div>
                        <div>Source</div>
                        <div>Destination</div>
                        <div>Proto</div>
                        <div>Len</div>
                        <div>Info</div>
                    </div>

                    <!-- two modes depending on whether search is active -->

                    <!-- MODE 1: no search query = normal virtual scroll -->
                    <RecycleScroller
                        v-if="!isSearchActive"
                        ref="scrollerRef"
                        class="flex-1"
                        :items="packets"
                        :item-size="ROW_HEIGHT"
                        key-field="packet_number"
                        @scroll.native="onScroll"
                    >
                        <template #default="{ item: packet }">
                            <div
                                @click="handlePacketClick(packet)"
                                :class="[
                                    'packet-row grid gap-x-4 px-6 border-b border-slate-100 hover:bg-blue-50 cursor-pointer transition-colors items-center text-sm font-mono',
                                    { 'bg-blue-100': selectedPacket === packet },
                                    { 'opacity-40 cursor-default hover:bg-transparent': packet._placeholder },
                                    { '!bg-yellow-100': jumpHighlight === packet.packet_number }
                                ]"
                            >
                                <div class="text-slate-400">{{ packet.packet_number }}</div>
                                <div class="text-slate-500 text-xs">{{ packet._placeholder ? 'Loading...' : formatTime(packet.timestamp) + 's' }}</div>
                                <div class="text-slate-800 font-medium">{{ packet.src_ip }}</div>
                                <div class="text-slate-800">{{ packet.dst_ip }}</div>
                                <div>
                                    <span v-if="!packet._placeholder" :class="getProtoColor(packet.l4_protocol)" class="text-[10px] font-black px-2 py-0.5 rounded border uppercase">
                                        {{ packet.l4_protocol }}
                                    </span>
                                </div>
                                <div class="text-slate-500 text-xs">{{ packet.captured_packet_length }}</div>
                                <div class="text-slate-600 truncate text-xs italic">{{ packet._placeholder ? 'Loading...' : 'Packet data...' }}</div>
                            </div>
                        </template>
                    </RecycleScroller>

                    <!-- MODE 2: search is active = plain scrollable list of results -->
                    <div v-else class="flex-1 overflow-y-auto">

                        <!-- Loading state -->
                        <div v-if="isSearching" class="p-10 text-center text-slate-400">
                            <p class="animate-pulse">Searching for packets...</p>
                        </div>

                        <!-- No results state -->
                        <div v-if="!isSearching && searchResults.length === 0" class="p-10 text-center text-slate-400">
                            No packets match "{{ filterText }}"
                        </div>

                        <!-- Results -->
                        <div
                            v-for="packet in searchResults"
                            :key="packet.packet_id"
                            @click="handlePacketClick(packet)"
                            :class="[
                                'packet-row grid gap-x-4 px-6 border-b border-slate-100 hover:bg-blue-50 cursor-pointer transition-colors items-center text-sm font-mono',
                                { 'bg-blue-100': selectedPacket === packet }
                            ]"
                        >
                            <div class="text-slate-400">{{ packet.packet_number }}</div>
                            <div class="text-slate-500 text-xs">{{ formatTime(packet.timestamp) }}s</div>
                            <div class="text-slate-800 font-medium">{{ packet.src_ip }}</div>
                            <div class="text-slate-800">{{ packet.dst_ip }}</div>
                            <div>
                                <span :class="getProtoColor(packet.l4_protocol)" class="text-[10px] font-black px-2 py-0.5 rounded border uppercase">
                                    {{ packet.l4_protocol }}
                                </span>
                            </div>
                            <div class="text-slate-500 text-xs">{{ packet.captured_packet_length }}</div>
                            <div class="text-slate-600 truncate text-xs italic">Packet data...</div>
                        </div>
                    </div>

                    <!-- Status bar -->
                    <div class="bg-white border-t border-slate-200 px-6 py-3 flex items-center justify-between shrink-0">

                        <!-- shows different info depending on mode -->
                        <div class="text-xs text-slate-500">

                            <!-- Normal mode: total packet count -->
                            <template v-if="!isSearchActive">
                                <span class="font-bold text-slate-900">{{ totalPackets.toLocaleString() }}</span> packets total
                            </template>

                            <!-- Search mode: how many results, capped at 500 -->
                            <template v-else-if="isSearching">
                                Searching for "<span class="font-mono font-bold text-slate-900">{{ filterText }}</span>"...
                            </template>
                            <template v-else>
                                <span class="font-bold text-slate-900">{{ searchResults.length.toLocaleString() }}</span>
                                <template v-if="searchTotal > searchResults.length">
                                    of <span class="font-bold text-slate-900">{{ searchTotal.toLocaleString() }}</span>
                                </template>
                                packets found
                                <span v-if="searchTotal > 500" class="text-amber-600 ml-1">(showing first 500)</span>
                            </template>

                        </div>
                    </div>

                </main>

                <!-- The right side -->
                <aside class="w-[600px] bg-white overflow-y-auto p-6">
                    
                    <!-- Content if packet is selected -->
                    <div v-if="selectedPacket" class="flex-1 overflow-y-auto p-5">
                        
                        <!-- Header with ID and Protocol Badge -->
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-tight">Packet #{{ selectedPacket.packet_number }}</h3>
                            <span :class="getProtoColor(selectedPacket.l4_protocol)" class="text-[10px] font-black px-2 py-0.5 rounded border uppercase">
                                {{ selectedPacket.l4_protocol }}
                            </span>
                        </div>

                        <!-- Sections (uses 'const detailSections') -->
                        <div v-for="section in detailSections" :key="section.title" class="mb-6">
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3 border-b border-slate-100 pb-1">
                                {{ section.title }}
                            </div>
                            
                            <div class="space-y-2">
                                <div v-for="field in section.fields" :key="field.label" class="flex justify-between items-start gap-4">
                                    <span class="text-xs text-slate-400 font-medium whitespace-nowrap">{{ field.label }}</span>
                                    <span class="text-xs font-mono font-bold text-slate-800 text-right break-all">
                                        {{ field.value }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Raw Hex -->
                        <div class="mt-8">
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Raw Hex</div>
                            <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 font-mono text-[11px] text-slate-600 leading-relaxed shadow-sm">
                                {{ selectedPacket.raw_hex }}
                            </div>
                        </div>
                    </div>

                    <!-- No Packet Selected -->
                    <div v-else class="h-full flex flex-col items-center justify-center text-slate-400 italic">
                        <p>Select a packet to view details</p>
                    </div>
                </aside>

            </template>

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
                            <p class="text-xs font-bold text-slate-400 uppercase">Packets Captured</p>
                            <p class="text-3xl font-black text-blue-600">{{ totalPackets.toLocaleString() }}</p>
                        </div>

                        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                            <p class="text-xs font-bold text-slate-400 uppercase">Capture Duration</p>
                            <p class="text-3xl font-black text-blue-600">
                                {{ captureDuration }} <span class="text-xl text-slate-400">sec</span>
                            </p>
                        </div>

                    </div>

                </div>
            </div>

            <!-- For the conversations tab -->
            <div v-else-if="activeTab === 'conversations'" class="flex-1 p-8">
                <h2 class="text-2xl font-bold text-slate-900 mb-4">Network Conversations</h2>
                <p class="text-slate-500 italic">Find out who is talking to whom</p>
            </div>


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
<style scoped>
.packet-row {
    height: 36px;
    grid-template-columns: minmax(60px,0.5fr) minmax(90px,0.8fr) minmax(140px,1.2fr) minmax(140px,1.2fr) 80px 70px 2fr;
}
</style>
