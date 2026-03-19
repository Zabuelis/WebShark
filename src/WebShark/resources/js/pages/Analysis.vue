<script setup>
import { ref, computed, onMounted } from 'vue'
import { router, Head } from '@inertiajs/vue3'
import NavBar from '../components/NavBar.vue'
import Footer from '../components/Footere.vue'

const selectedPacket = ref(null)

// Function to handle the click
function handlePacketClick(packet) {
    // If they click the same one again, maybe deselect it? 
    selectedPacket.value = packet
}

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

        <!-- Left and right side -->
        <div class="flex flex-1 overflow-hidden">

            <!-- The left side -->
            <main class="flex-1 flex flex-col overflow-hidden border-r border-slate-200">

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
                    <div v-for="packet in props.packets" 
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

        </div>

    </div>

</template>