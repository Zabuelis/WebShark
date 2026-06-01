<script setup>
import { Head } from '@inertiajs/vue3'
import NavBar from '../components/NavBar.vue'
import Footer from '../components/Footer.vue'

const protoBadges = [
    { name: 'TCP', classes: 'bg-blue-100 text-blue-700 border-blue-200' },
    { name: 'UDP', classes: 'bg-purple-100 text-purple-700 border-purple-200' },
    { name: 'TLS', classes: 'bg-yellow-100 text-yellow-700 border-yellow-200' },
    { name: 'HTTP', classes: 'bg-indigo-100 text-indigo-700 border-indigo-200' },
    { name: 'ICMP', classes: 'bg-pink-100 text-pink-700 border-pink-200' },
    { name: 'DNS', classes: 'bg-pink-100 text-pink-700 border-pink-200' },
    { name: 'DHCP', classes: 'bg-teal-100 text-teal-700 border-teal-200' },
    { name: 'SSH', classes: 'bg-sky-100 text-sky-700 border-sky-200' }
]
</script>

<template>
    <Head title="Help & Documentation" />
    <NavBar />

    <div class="flex-auto min-h-screen bg-slate-50 text-slate-800 py-10 font-sans">
        <div class="max-w-4xl mx-auto px-6">
            
            <!-- Header -->
            <div class="border-b border-slate-200 pb-5 mb-8">
                <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 brand-header">WebShark Documentation</h1>
                <p class="mt-2 text-base text-slate-500 font-medium">
                    A guide to analyzing packet captures, understanding visual metrics, and navigating protocol structures.
                </p>
            </div>

            <div class="space-y-8">
                <!-- Getting Started -->
                <section class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm space-y-4">
                    <h2 class="text-xl font-bold text-slate-900 brand-header flex items-center gap-2">
                        <span>1. Upload & Live Analysis Session</span>
                    </h2>
                    <p class="text-slate-600 leading-relaxed text-sm">
                        WebShark is a web-based packet inspection tool.
                        It allows you to view raw network traces interactively without installing any desktop software.
                    </p>
                    <ul class="list-disc pl-5 space-y-2 text-sm text-slate-600">
                        <li><strong>File Support:</strong> Upload native packet capture traces with <code class="bg-slate-100 px-1.5 py-0.5 rounded text-blue-600 font-mono text-xs">.pcap</code> or <code class="bg-slate-100 px-1.5 py-0.5 rounded text-blue-600 font-mono text-xs">.pcapng</code> formats up to <span class="font-bold text-slate-900">10 MB</span>.</li>
                        <li><strong>Processing Queue:</strong> When files are uploaded, jobs enter our worker pool. You will see real-time updates as your capture waiting in queue, being processed and finished.</li>
                        <li><strong>Analysis Session Expiration:</strong> Once analyzed, your URL is fully shareable. Note that results contain an active expiration notice. You can copy the unique analysis URL via the <span class="bg-amber-100 text-amber-800 px-1.5 py-0.5 rounded text-xs font-semibold">Copy Link</span> action to review it later before the storage gets cleared.</li>
                    </ul>
                </section>

                <!-- Filters -->
                <section class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm space-y-4">
                    <h2 class="text-xl font-bold text-slate-900 brand-header">2. Advanced Filtering Syntax</h2>
                    <p class="text-slate-600 leading-relaxed text-sm">
                        For quicker packet search, the tool supports filtering. Filters are finite, however they can be combined to create more strict filtering conditions. 
                        You can isolate protocols, specific hosts, or flows dynamically using WebShark's query filter bar.
                    </p>

                    <!-- Filter Chaining -->
                    <div class="border-l-4 border-blue-500 pl-4 py-2 bg-blue-50/50 rounded-r text-sm">
                        <span class="font-semibold text-blue-800">Syntax Chaining Rule:</span>
                        <p class="text-xs text-slate-600 mt-1">
                            Combine rules sequentially using the logical <code class="bg-blue-100 text-blue-900 px-1 font-mono rounded font-bold">&&</code> operator to construct narrow analysis criteria:
                        </p>
                        <p class="text-xs font-mono text-blue-700 mt-1.5 font-bold">
                            ip.src == 192.168.1.1 && proto == TLS && ip.dst == 18.97.36.54
                        </p>
                    </div>

                    <!-- Available Filters Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 border border-slate-100 mt-2">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Pattern Syntax</th>
                                    <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Application</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-slate-100 text-sm">
                                <tr>
                                    <td class="px-4 py-2.5 whitespace-nowrap font-mono text-xs font-bold text-blue-600">ip.src == &lt;IP&gt;</td>
                                    <td class="px-4 py-2.5 text-slate-600 text-xs">Filters packets matching the specified source IPv4 or IPv6 address.</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-2.5 whitespace-nowrap font-mono text-xs font-bold text-blue-600">ip.dst == &lt;IP&gt;</td>
                                    <td class="px-4 py-2.5 text-slate-600 text-xs">Filters packets matching the specified destination IPv4 or IPv6 address.</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-2.5 whitespace-nowrap font-mono text-xs font-bold text-blue-600">port.src == &lt;PORT&gt;</td>
                                    <td class="px-4 py-2.5 text-slate-600 text-xs">Isolates traffic originating from the specified layer 4 source port.</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-2.5 whitespace-nowrap font-mono text-xs font-bold text-blue-600">port.dst == &lt;PORT&gt;</td>
                                    <td class="px-4 py-2.5 text-slate-600 text-xs">Isolates traffic destined to the specified layer 4 destination port.</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-2.5 whitespace-nowrap font-mono text-xs font-bold text-blue-600">proto == &lt;PROTO&gt;</td>
                                    <td class="px-4 py-2.5 text-slate-600 text-xs">
                                        Searches for specified protocols. This filter is <span class="font-semibold text-slate-900">case-sensitive</span>. Common protocols are uppercase (e.g., <code class="bg-slate-100 px-1 py-0.5 rounded text-xs font-mono font-bold text-slate-700">TLS</code>, <code class="bg-slate-100 px-1 py-0.5 rounded text-xs font-mono font-bold text-slate-700">TCP</code>, <code class="bg-slate-100 px-1 py-0.5 rounded text-xs font-mono font-bold text-slate-700">HTTP</code>, <code class="bg-slate-100 px-1 py-0.5 rounded text-xs font-mono font-bold text-slate-700">UDP</code>, <code class="bg-slate-100 px-1 py-0.5 rounded text-xs font-mono font-bold text-slate-700">ICMP</code>), while network-layer exceptions like <code class="bg-slate-100 px-1 py-0.5 rounded text-xs font-mono font-bold text-slate-700">IPv4</code> or <code class="bg-slate-100 px-1 py-0.5 rounded text-xs font-mono font-bold text-slate-700">IPv6</code> contain mixed casing.
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-2.5 whitespace-nowrap font-mono text-xs font-bold text-blue-600">tcp.flow == &lt;INDEX&gt;</td>
                                    <td class="px-4 py-2.5 text-slate-600 text-xs">
                                        Filters results based on a reassembled stream. TCP flows are organized sequentially during the parsing phase, starting from index <code class="bg-slate-100 px-1 py-0.5 rounded text-xs font-mono">0</code>.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Packet List Details -->
                <section class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm space-y-4">
                    <h2 class="text-xl font-bold text-slate-900 brand-header">3. Inside the Packet Inspector</h2>
                    <p class="text-slate-600 leading-relaxed text-sm">
                        Selecting any row inside the packet list activates a side-by-side split screen that displays detailed values for that specific packet. The inspector is organized into four main levels of information:
                    </p>
                    
                    <div class="grid gap-4 text-xs mt-3">
                        <div class="bg-slate-50 p-4 rounded-lg border border-slate-100 space-y-2">
                            <h3 class="font-bold text-slate-700 uppercase tracking-wider text-[10px]">Inspector Levels</h3>
                            <ul class="list-disc pl-4 space-y-1 text-slate-600">
                                <li><strong>Frame:</strong> Capture length, frame ID, timestamp delta.</li>
                                <li><strong>Network (L3):</strong> Complete layer 3 configuration (e.g. Source/Destination IP details).</li>
                                <li><strong>Transport (L4):</strong> Source & destination ports, TCP flag configurations, window sizing, etc.</li>
                                <li><strong>Application (L7):</strong> Deep packet dissection values, client hellos, HTTP request URIs, or DNS transaction flags.</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Flow Highlight System -->
                    <div class="mt-4 p-4 bg-indigo-50 border border-indigo-100 rounded-lg text-sm">
                        <h4 class="font-bold text-indigo-900 flex items-center gap-1.5">
                            Visual Flow Highlight Connection
                        </h4>
                        <p class="text-indigo-950 text-xs mt-1 leading-relaxed">
                            When you select a packet, the interface searches for matching streams. If the packet belongs to a TCP conversation, the app dynamically highlights other packets in the same TCP flow with a light indigo background. This makes it easy to visually follow the path of a packet stream as you scroll.
                        </p>
                    </div>

                    <!-- Protocol Color Coding -->
                    <div>
                        <h4 class="text-sm font-semibold text-slate-800 mb-2 font-mono">Protocol Coloring Conventions</h4>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            <div v-for="badge in protoBadges" :key="badge.name" class="flex flex-col items-center justify-center p-2 rounded-lg border border-slate-100 bg-slate-50">
                                <span :class="badge.classes" class="text-[10px] font-black px-2.5 py-0.5 rounded border uppercase font-mono tracking-wider">
                                    {{ badge.name }}
                                </span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Visual Analytics -->
                <section class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm space-y-4">
                    <h2 class="text-xl font-bold text-slate-900 brand-header">4. Visual Analytics</h2>
                    <p class="text-slate-600 leading-relaxed text-sm">
                        The <strong>Overview</strong> tab converts raw packet data into visual charts to help you quickly spot network anomalies.
                    </p>
                    <div class="space-y-4 mt-2">
                        <div class="flex items-start gap-4">
                            <div>
                                <h4 class="font-bold text-sm text-slate-800">Protocol Distribution (Pie Charts)</h4>
                                <p class="text-slate-600 text-xs leading-relaxed mt-0.5">
                                    WebShark divides protocol metrics into three distinct logical network levels: Network Layer (L3), Transport Layer (L4), and Application Layer (L7). 
                                    To clean up clutter from scans or noisy local interfaces, protocols representing less than 1% of the total packets are combined into an "Others" category.
                                </p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4">
                            <div>
                                <h4 class="font-bold text-sm text-slate-800">Top Talkers (Bar Chart)</h4>
                                <p class="text-slate-600 text-xs leading-relaxed mt-0.5">
                                    A horizontal bar chart listing the most active IP hosts in the capture file, ordered by total packet count. Perfect for pinpointing heavy-upload endpoints or high-rate automated scanning scripts.
                                </p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4">
                            <div>
                                <h4 class="font-bold text-sm text-slate-800">Packet Size Distribution (Histogram)</h4>
                                <p class="text-slate-600 text-xs leading-relaxed mt-0.5">
                                    A distribution histogram grouping packet sizes into 10 buckets up to 1500 bytes. This visualization allows analysts to quickly distinguish small TCP ACKs keepalives from high-volume data payloads.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Navigation and Shortcuts -->
                <section class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm space-y-4">
                    <h2 class="text-xl font-bold text-slate-900 brand-header">5. Interface Shortcuts & Quick Jumps</h2>
                    <p class="text-slate-600 leading-relaxed text-sm">
                        Working with thousands of rows in high-capacity captures can be exhausting. Use these shortcuts to speed up your analysis workflows:
                    </p>
                    <ul class="list-disc pl-5 space-y-2 text-sm text-slate-600">
                        <li><strong>Jump to Packet:</strong> Enter any number in the <code class="bg-slate-100 px-1 py-0.5 rounded text-xs font-mono border text-slate-700">Go to #</code> input field inside the toolbar and press <kbd class="bg-slate-100 border px-1.5 py-0.5 rounded text-xs shadow-sm text-slate-600">Enter</kbd> (or click Jump) to snap the list scroller straight to that frame index with a highlighted yellow flash.</li>
                        <li><strong>Recycled Scroll:</strong> The main packet list utilizes optimized row pooling. It only mounts elements visible on your screen, letting you scroll smoothly through millions of lines of network traffic without slowing down your browser.</li>
                    </ul>
                </section>
            </div>

        </div>
    </div>

    <Footer />
</template>

<style scoped>
/* Tahoma font matching style.css */
.brand-header {
    font-family: Tahoma, sans-serif;
}
</style>