<script setup>
import { RecycleScroller } from 'vue-virtual-scroller'
import { ref, computed, nextTick, watch } from 'vue'

const props = defineProps({
    url: String,
    status: String,
    first_packet_time: Number,
})
const selectedPacket = ref(null)

// Takes JSON object and rebuilds it into an array of [attribute_name, attribute_value] pairs.
// Filters boolean values to avoid empty fields.
const rebuildPacketFields = (attributes) => {
    return [ 
        ...(attributes ? Object.entries(attributes).map(([attribute_name, attribute_value]) =>
            attribute_value !== "" ? { label: attribute_name, value: attribute_value } : null
        ).filter(Boolean)
        : []
    )]
}

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
            title: "Network Layer",
            fields: [
                p.l3_protocol != null ? { label: "Protocol", value: p.l3_protocol } : null,
                ...rebuildPacketFields(p.l3_attributes)
            ]
        },
        {
            title: "Transport Layer",
            fields: [
                p.l4_protocol != null ? { label: "Protocol", value: p.l4_protocol } : null, 
                ...rebuildPacketFields(p.l4_attributes)
            ]
        },
        {
            title: "Application Layer",
            fields: rebuildPacketFields(p.l7_attributes)
        }
    ]
})

// For protocol badge colors
const getProtoColor = (packet) => {
    const colors = {
        'TCP': 'bg-blue-100 text-blue-700 border-blue-200',
        'UDP': 'bg-purple-100 text-purple-700 border-purple-200',
        'ICMP': 'bg-pink-100 text-pink-700 border-pink-200',
        'TLS': 'bg-yellow-100 text-yellow-700 border-yellow-200',
        'HTTP': 'bg-indigo-100 text-indigo-700 border-indigo-200',
        'DNS': 'bg-pink-100 text-pink-700 border-pink-200',
        'DHCP': 'bg-teal-100 text-teal-700 border-teal-200',
        'SSH': 'bg-sky-100 text-sky-700 border-sky-200'
    }
    if (packet?.l7_attributes?.Protocol) return colors[packet.l7_attributes.Protocol] || 'bg-slate-100 text-slate-700 border-slate-200'
    if (packet?.l4_protocol) return colors[packet.l4_protocol] || 'bg-slate-100 text-slate-700 border-slate-200'
    return 'bg-slate-100 text-slate-700 border-slate-200'
}

// Return the highest protocol to display
const highestLevelProtocol = (packet) => {
    if (packet?.l7_attributes?.Protocol) return packet.l7_attributes.Protocol
    if (packet?.l4_protocol) return packet.l4_protocol
    if (packet?.l3_protocol) return packet.l3_protocol
    return ""
}

// Protocol specific for quick preview in the INFO column
const protocolSpecificInformation = (packet) => {
    var data = []
    switch (packet.l7_attributes?.Protocol || packet.l4_protocol || packet.l3_protocol) {
        case "TLS":
            data = [packet.l7_attributes.Version, packet.l7_attributes.Encrypted_Protocol]
            break
        case "DHCP":
            data = [packet.l7_attributes.DHCP_Message_Type, `ID:${packet.l7_attributes.Transaction_ID}`]
            break
        case "DNS":
            data = [`ID:${packet.l7_attributes.Transaction_ID}`, packet.l7_attributes.Type, `Flags:${packet.l7_attributes.Flags}`]
            break
        case "HTTP":
            if (packet.l7_attributes.Request_Method === "GET") {
                data = [packet.l7_attributes.Request_Method, packet.l7_attributes.Full_URI, packet.l7_attributes.Version]
                break
            } else if (packet.l7_attributes.Protocol) {
                data = [packet.l7_attributes.Version, packet.l7_attributes.Response_Phrase, packet.l7_attributes.Response_Code]
                break
            }
        case "SSH":
            data = [packet.l7_attributes.SSH_Direction]
            break
        case "TCP":
            data = [packet.l4_attributes.Source_Port, "-> ", packet.l4_attributes.Destination_Port, `Flags=${packet.l4_attributes.Flags}`, `Win=${packet.l4_attributes.Window}`]
            break
        case "UDP":
            data = [packet.l4_attributes.Source_Port, "-> ", packet.l4_attributes.Destination_Port]
            break
        case "ICMP":
            data = [`Type:${packet.l4_attributes.Type}`, " ", `Code:${packet.l4_attributes.Code}`,]
            break
        case "ARP":
            data = [`Opcode:${packet.l3_attributes.Opcode}`]
            break
    }
    data = data.filter(Boolean)
    var data_string = data.join(' ')
    return data_string
}

// Function to handle the click
function handlePacketClick(packet) {
    if (packet._placeholder) return
    selectedPacket.value = selectedPacket.value === packet ? null : packet
}

// Virtual scroll data layer
const PER_PAGE = 100
const WINDOW_SIZE = 5
const ROW_HEIGHT = 36

const filterText = ref('')
const oldFilter = ref('')
const isSearchActive = computed(() => filterText.value.trim() !== '')

const pageStore = new Map()
const loadedPages = ref(new Set())
const loadingPages = new Set()

const totalItems = ref(0)
const totalPages = ref(0)

const items = ref([])
const isSearching = ref(false)

function resetStore() {
    pageStore.clear()
    loadedPages.value = new Set()
    loadingPages.clear()
    totalItems.value = 0
    totalPages.value = 0
    items.value = []
}

function rebuildList() {
    const out = []

    for (let p = 1; p <= totalPages.value; p++) {
        const rows = pageStore.get(p)

        if (rows) {
            out.push(...rows)
        } else {
            const start = (p - 1) * PER_PAGE + 1
            const end = Math.min(p * PER_PAGE, totalItems.value)

            for (let i = start; i <= end; i++) {
                out.push({ _placeholder: true, packet_number: i })
            }
        }
    }

    items.value = out
}

async function fetchPage(page) {
    if (page < 1) return
    if (totalPages.value > 0 && page > totalPages.value) return
    if (loadedPages.value.has(page)) return
    if (loadingPages.has(page)) return

    loadingPages.add(page)

    if (isSearchActive.value) {
        isSearching.value = true
    }

    try {
        const url = new URL(props.url, window.location.origin)

        url.searchParams.set('page', page)
        url.searchParams.set('per_page', PER_PAGE)

        if (isSearchActive.value) {
            url.searchParams.set('q', filterText.value.trim())
        }

        const response = await fetch(url)
        if (!response.ok) throw new Error(`HTTP ${response.status}`)

        const json = await response.json()

        totalItems.value = json.total
        totalPages.value = json.total_pages ?? 1

        pageStore.set(page, json.data)
        loadedPages.value = new Set([...loadedPages.value, page])

        evictDistantPages(page)
        rebuildList()

    } catch (e) {
        console.error('Fetch failed', page, e)
    } finally {
        loadingPages.delete(page)
        isSearching.value = false
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
    rebuildList()
}

function onScroll(event) {
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

    if (!n || n < 1 || (totalItems.value > 0 && n > totalItems.value)) {
        jumpError.value = `Enter a number between 1 and ${totalItems.value.toLocaleString()}`
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

function filterPackets(){
    if(filterText.value.trim() !== oldFilter.value){
        resetStore()

        if(filterText.value.trim() === ''){
            initVirtualList()
            return
        }

        fetchPage(1)
        oldFilter.value = filterText.value.trim()
    }
}

async function initVirtualList() {
    await fetchPage(1)
    fetchPage(2)
}

const formatIP = (ip) => {
    if (!ip) {
        return ip
    } else if (!ip.includes(':')){
        // Edge-case tshark ICMP returns 2 IP addresses, it happens because certain ICMP messages include original IP header.
        if(ip.includes(',')){
            return ip.split(',')[0]
        } else {
            return ip
        }
    } else {
        const parts = ip.split(':')
        if (parts.length <= 4) return ip  // Short enough already
        return `${parts[0]}:${parts[1]}:…:${parts[parts.length - 2]}:${parts[parts.length - 1]}`
    }
}

const formatTime = (packetTimestamp) => {
    if (!packetTimestamp || !props.first_packet_time) return "0.000000"
    return (parseFloat(packetTimestamp) - props.first_packet_time).toFixed(6)
}

// Load packets immediately when status=finished
watch(
    () => props.status,
    async (newStatus) => {
        if (newStatus === 'finished') {
            await initVirtualList()
        }
    },
    { immediate: true }
)

const isFlowHighlightActive = computed(() =>
    selectedPacket.value != null && selectedPacket.value.flow != null
)

</script>

<template>
    <main class="flex-1 flex flex-col overflow-hidden border-r border-slate-200 min-w-0">

        <!-- Toolbar -->
        <div class="p-3 bg-white border-b border-slate-200 flex gap-2">
            <div class="relative flex-1">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                </span>
                <input 
                    v-model="filterText" 
                    type="text" 
                    placeholder="Filter by ip.src, proto, etc. (e.g. ip.src == 192.168.1.1 && proto == TCP)"
                    class="w-full pl-10 pr-4 py-2 bg-slate-100 border border-slate-200 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all"
                />
                <!-- Spinner inside the search box -->
                <span v-if="isSearching" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400">
                    <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
                    </svg>
                </span>
            </div>
            <button
                @click="filterPackets"
                :disabled="isSearching"
                class="px-3 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded-md text-sm font-bold transition-colors flex items-center gap-1.5"
            >
                Filter
            </button>
            <div class="flex items-center gap-1.5 shrink-0">
                <input
                    v-model="jumpInput"
                    @keydown.enter="jumpToPacket"
                    type="number"
                    min="1"
                    :max="totalItems || undefined"
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

        <div class="overflow-x-auto flex-1">
            <div class="min-w-[1000px] flex flex-col h-full">
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

                <!-- virtual scroll -->
                <RecycleScroller
                    ref="scrollerRef"
                    class="flex-1"
                    :items="items"
                    :item-size="ROW_HEIGHT"
                    key-field="packet_number"
                    @scroll="onScroll"
                >
                    <template #default="{ item: packet }">
                        <div
                            @click="handlePacketClick(packet)"
                            :class="[
                                'packet-row grid gap-x-4 px-6 border-b border-slate-100 hover:bg-blue-50 cursor-pointer transition-colors items-center text-sm font-mono',
                                { 'opacity-40 cursor-default hover:bg-transparent': packet._placeholder },
                                { '!bg-yellow-100': jumpHighlight === packet.packet_number },
                                { 'bg-blue-100': selectedPacket === packet },
                                { 'bg-indigo-100': isFlowHighlightActive && selectedPacket.flow === packet.flow },
                            ]"
                        >
                            <div class="text-slate-400">{{ packet.packet_number }}</div>
                            <div class="text-slate-500 text-xs">
                                {{ packet._placeholder ? 'Loading...' : formatTime(packet.timestamp) + 's' }}
                            </div>
                            <div class="text-slate-800 font-medium">{{ formatIP(packet.l3_attributes?.Source_IP) }}</div>
                            <div class="text-slate-800">{{ formatIP(packet.l3_attributes?.Destination_IP) }}</div>
                            <div>
                                <span v-if="!packet._placeholder"
                                    :class="getProtoColor(packet)"
                                    class="text-[10px] font-black px-2 py-0.5 rounded border uppercase">
                                    {{ highestLevelProtocol(packet) }}
                                </span>
                            </div>
                            <div class="text-slate-500 text-xs">{{ packet.captured_packet_length }}</div>
                            <div class="text-slate-600 text-xs truncate italic">
                                {{ packet._placeholder ? 'Loading...' : protocolSpecificInformation(packet) }}
                            </div>
                        </div>
                    </template>
                </RecycleScroller>
            </div>    
        </div>

        <div class="text-xs text-slate-500 px-4 py-2 border-t border-slate-200 bg-white">

            <!-- Normal -->
            <template v-if="!isSearchActive">
                <span class="font-bold text-slate-900">
                    {{ totalItems.toLocaleString() }}
                </span>
                packets total
            </template>

            <!-- Searching -->
            <template v-else-if="isSearching">
                Searching for
                "<span class="font-mono font-bold text-slate-900">{{ filterText }}</span>"...
            </template>

            <!-- Results -->
            <template v-else>
                <span class="font-bold text-slate-900">
                    {{ totalItems.toLocaleString() }}
                </span>
                results for
                "<span class="font-mono">{{ filterText }}</span>"
            </template>

        </div>

    </main>

    <!-- The right side -->
    <aside class="w-[600px] bg-white overflow-y-auto p-6">
        
        <!-- Content if packet is selected -->
        <div v-if="selectedPacket" class="flex-1 overflow-y-auto p-5">
            
            <!-- Header with ID and Protocol Badge -->
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-tight">Packet #{{ selectedPacket.packet_number }}</h3>
                <span :class="getProtoColor(selectedPacket)" class="text-[10px] font-black px-2 py-0.5 rounded border uppercase">
                    {{ highestLevelProtocol(selectedPacket) }}
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
        </div>

        <!-- No Packet Selected -->
        <div v-else class="h-full flex flex-col items-center justify-center text-slate-400 italic">
            <p>Select a packet to view details</p>
        </div>
    </aside>
</template>

<style scoped>
.packet-row {
    height: 36px;
    grid-template-columns: minmax(60px,0.5fr) minmax(90px,0.8fr) minmax(160px,1.5fr) minmax(160px,1.5fr) 80px 70px 2fr;
}
</style>