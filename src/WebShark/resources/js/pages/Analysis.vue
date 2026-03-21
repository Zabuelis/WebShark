<script setup>
import { ref, computed, onMounted } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import NavBar from '../components/NavBar.vue'
import Footer from '../components/Footere.vue'

const props = defineProps({
    packets: Object,
    status: String,
    message: String,
    id: String,
    total_bytes: Number,
    first_packet_time: Number,
    last_packet_time: Number
})

const captureDuration = computed(() => {
  if (!props.first_packet_time || !props.last_packet_time) return "0.00"
  const diff = props.last_packet_time - props.first_packet_time
  return diff.toFixed(3)
})

const formatTime = (packetTimestamp) => {
    if (!packetTimestamp || !props.first_packet_time) return "0.000000"
    const relative = parseFloat(packetTimestamp) - props.first_packet_time
    return relative.toFixed(6)
}

const selectedPacket = ref(null)

const filterText = ref('')

const activeTab = ref('packets') // Options: 'packets', 'overview', 'conversations'

const detailSections = computed(() => {
  if (!selectedPacket.value) return []
  
  const p = selectedPacket.value
  
  return [
    {
      title: "Frame",
      fields: [
        { label: "ID", value: p.packet_id },
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
  selectedPacket.value = selectedPacket.value === packet ? null : packet
}

// Calculate total MB from packets
const totalMB = computed(() => {
  if (!props.total_bytes) return "0.00"
  return (props.total_bytes / (1024 * 1024)).toFixed(2)
})

const filteredPackets = computed(() => {
    const packetArray = props.packets.data || []
    
    // If no filter text, return all packets
    if (!filterText.value) return packetArray

    const search = filterText.value.toLowerCase()
    return packetArray.filter(p => 
        p.src_ip.toLowerCase().includes(search) || 
        p.dst_ip.toLowerCase().includes(search) ||
        p.l4_protocol.toLowerCase().includes(search)

    )
})

// If it's still processing, check again in 1 second (so user does not have to refresh manually)
onMounted(() => {
  if (props.status === 'dispatching') {
    const interval = setInterval(() => {
      router.reload({ 
        only: ['packets', 'status', 'message', 'total_bytes', 'id', 'first_packet_time', 'last_packet_time'],
        onSuccess: () => {
          if (props.status !== 'dispatching') {
            clearInterval(interval)
          }
        }
      })
    }, 1000)
  }
})
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

        <NavBar class="shrink-0" />

        <!-- Tab bar-->
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

                    <!-- The search bar -->
                    <div class="p-3 bg-white border-b border-slate-200">
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                            </span>
                            <input 
                                v-model="filterText" 
                                type="text" 
                                placeholder="Filter by IP, Protocol (e.g. 192.168 or TCP)..." 
                                class="w-full pl-10 pr-4 py-2 bg-slate-100 border border-slate-200 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all"
                            />
                        </div>
                    </div>

                    <!-- Packet headers -->
                    <div class="grid grid-cols-[48px_90px_130px_130px_80px_60px_1fr] px-4 py-2 bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                        <div>ID</div>
                        <div>Time</div>
                        <div>Source</div>
                        <div>Destination</div>
                        <div>Proto</div>
                        <div>Len</div>
                        <div>Info</div>
                    </div>


                    <!-- Packet list -->
                    <div class="flex-1 overflow-y-auto">
                        <div v-for="packet in filteredPackets" 
                            :key="packet.packet_id"
                            @click="handlePacketClick(packet)"
                            :class="{ 'bg-blue-100': selectedPacket === packet }"
                            class="grid grid-cols-[48px_90px_130px_130px_80px_60px_1fr] px-4 py-2 border-b border-slate-100 hover:bg-blue-50 cursor-pointer transition-colors items-center text-sm font-mono">
                        <div class="text-slate-400">{{ packet.packet_id }}</div>
                        <div class="text-slate-500 text-xs">{{ formatTime(packet.timestamp) }}s</div>
                        <div class="text-slate-800 font-medium">{{ packet.src_ip }}</div>
                        <div class="text-slate-800">{{ packet.dst_ip }}</div>
                        <div>
                            <span :class="getProtoColor(packet.l4_protocol)" class="text-[10px] font-black px-2 py-0.5 rounded border uppercase">
                                {{ packet.l4_protocol  }}
                            </span>
                        </div>
                        <div class="text-slate-500 text-xs">{{ packet.captured_packet_length }}</div>
                        <div class="text-slate-600 truncate text-xs italic">Packet data...</div>
                        </div>


                        <!-- Show this if nothing matches the search -->
                        <div v-if="filteredPackets.length === 0" class="p-10 text-center text-slate-400">
                            No packets match "{{ filterText }}"
                        </div>
                    </div>

                    <!-- Pagination footer-->
                    <div class="bg-white border-t border-slate-200 px-6 py-3 flex items-center justify-between shrink-0">
                        
                        <!-- Info Section -->
                        <div class="text-xs text-slate-500">
                            Showing <span class="font-bold text-slate-900">{{ props.packets.from }}</span> 
                            to <span class="font-bold text-slate-900">{{ props.packets.to }}</span> 
                            of <span class="font-bold text-slate-900">{{ props.packets.total }}</span> packets
                        </div>

                        <!-- Page Numbers -->
                        <nav class="flex items-center gap-1">
                            <template v-for="(link, index) in props.packets.links" :key="index">
                                
                                <div v-if="link.label === '...'" class="px-3 py-1 text-slate-400 text-xs">
                                    ...
                                </div>

                                <Link
                                    v-else
                                    :href="link.url || '#'"
                                    v-html="link.label"
                                    :class="[
                                        'px-3 py-1.5 rounded text-xs font-bold transition-all border',
                                        link.active 
                                            ? 'bg-blue-600 border-blue-600 text-white shadow-md' 
                                            : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50',
                                        !link.url ? 'opacity-30 cursor-not-allowed pointer-events-none' : ''
                                    ]"
                                    preserve-scroll
                                />
                            </template>
                        </nav>
                    </div>

                </main>

                <!-- The right side -->
                <aside class="w-[600px] bg-white overflow-y-auto p-6">
                    
                    <!-- Content if packet is selected -->
                    <div v-if="selectedPacket" class="flex-1 overflow-y-auto p-5">
                        
                        <!-- Header with ID and Protocol Badge -->
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-tight">Packet #{{ selectedPacket.packet_id }}</h3>
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
                                {{ totalMB }} MB
                            </p>
                        </div>

                        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                            <p class="text-xs font-bold text-slate-400 uppercase">Packets Captured</p>
                            <p class="text-3xl font-black text-blue-600">{{ props.packets.total }}</p>
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

    </div>

</template>