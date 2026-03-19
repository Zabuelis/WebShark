<script setup>
import { ref, computed, onMounted } from 'vue'
import { router, Head } from '@inertiajs/vue3'
import NavBar from '../components/NavBar.vue'
import Footer from '../components/Footere.vue'

const selectedPacket = ref(null)

const filterText = ref('')

const activeTab = ref('packets') // Options: 'packets', 'overview', 'conversations'

// Function to handle the click
function handlePacketClick(packet) {
    // If they click the same one again, maybe deselect it? 
    selectedPacket.value = packet
}

// Calculate total MB from packets
const totalMB = computed(() => {
  return (props.packets.reduce((acc, p) => acc + (p.captured_packet_length || 0), 0) / (1024 * 1024)).toFixed(2)
})

const filteredPackets = computed(() => {
    // If search is empty, just show everything
    if (!filterText.value) return props.packets

    const search = filterText.value.toLowerCase()

    return props.packets.filter(p => {
        return (
            p.src_ip.toLowerCase().includes(search) ||
            p.dst_ip.toLowerCase().includes(search) ||
            p.l4_protocol.toLowerCase().includes(search)
            // ToDo: Add more fields to search
        )
    })
})

const props = defineProps({
    packets: {
        type: Array,
        default: () => []
    },
    status: String,
    message: String,
    id: String
})

// If it's still processing, check again in 1 second
onMounted(() => {
  if (props.status === 'dispatching') {
    const interval = setInterval(() => {
      router.reload({ 
        only: ['packets', 'status'],
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

    <!-- This only shows if the status is 'dispatching' -->
    <div v-if="props.status === 'dispatching'" 
         class="fixed inset-0 z-50 bg-slate-900 flex flex-col items-center justify-center text-center px-6">
        
        <!-- The Content -->
        <div class="relative z-10 flex flex-col items-center">
            <!-- Spinner -->
            <div class="w-16 h-16 border-4 border-blue-500/20 border-t-blue-500 rounded-full animate-spin mb-8"></div>
            
            <!-- Status Message -->
            <p class="text-slate-400 font-mono text-sm max-w-md leading-relaxed">
                Status: <span class="animate-pulse">Analyzing PCAP...</span>
            </p>
        </div>
    </div>



    <Head title="Analysis page" />

    <div class="h-screen flex flex-col bg-slate-50 overflow-hidden font-sans">

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
                        <div class="text-slate-500 text-xs">0.000s</div>
                        <div class="text-slate-800 font-medium">{{ packet.src_ip }}</div>
                        <div class="text-slate-800">{{ packet.dst_ip }}</div>
                        <div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-700 border border-blue-200 uppercase">
                            {{ packet.l4_protocol }}
                            </span>
                        </div>
                        <div class="text-slate-500 text-xs">{{ packet.captured_packet_length }}</div>
                        <div class="text-slate-600 truncate text-xs italic">Raw packet data...</div>
                        </div>


                        <!-- Show this if nothing matches the search -->
                        <div v-if="filteredPackets.length === 0" class="p-10 text-center text-slate-400">
                            No packets match "{{ filterText }}"
                        </div>
                    </div>
                </main>

                <!-- The right side -->
                <aside class="w-[600px] bg-white overflow-y-auto p-6">
                    <div v-if="selectedPacket">
                    <h2 class="text-xl font-bold mb-6 text-slate-900">Packet #{{ selectedPacket.packet_id }}</h2>
                    
                    <div class="space-y-4">
                        <div class="bg-slate-50 p-3 rounded-lg border border-slate-100">
                            <p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Source IP</p>
                            <p class="font-mono text-sm">{{ selectedPacket.src_ip }}</p>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-lg border border-slate-100">
                            <p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Destination IP</p>
                            <p class="font-mono text-sm">{{ selectedPacket.dst_ip }}</p>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-lg border border-slate-100">
                            <p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Protocol</p>
                            <p class="font-mono text-sm text-blue-600 font-bold">{{ selectedPacket.l4_protocol }}</p>
                        </div>
                    </div>
                    </div>

                    <div v-else class="h-full flex flex-col items-center justify-center text-slate-400 italic">
                    <p>Select a packet to view details</p>
                    </div>
                </aside>

            </template>

            <!-- For the overview tab -->
            <div v-else-if="activeTab === 'overview'" class="flex-1 overflow-y-auto p-8 bg-slate-50">
                <div class="max-w-6xl mx-auto">
                    <h2 class="text-2xl font-bold text-slate-900 mb-6">Traffic Overview</h2>
                    
                    <!-- Placeholder for your Stats Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                            <p class="text-xs font-bold text-slate-400 uppercase">Total Data</p>
                            <p class="text-3xl font-black text-blue-600">
                                {{ totalMB }} MB
                            </p>
                        </div>
                        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                            <p class="text-xs font-bold text-slate-400 uppercase">Packets Captured</p>
                            <p class="text-3xl font-black text-slate-900">{{ props.packets.length }}</p>
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