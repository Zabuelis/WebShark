<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\RedisJob;
use App\Models\Packet;
use App\Jobs\AnalyzePcap;
use App\Models\IpMarker;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PcapController extends Controller
{
    // Upload a file and create a job for analysis
    public function create(Request $request)
    {
        $request->validate([
            'pcap_file' => 'required|file|max:10240',
        ]);

        $sessionID = session()->getId();
        $currentIP = $request->ip();

        // Laravel does not distinguish mime type pcap,pcapng and returns an error therefore, a manual check is needed
        if(!in_array($request->file('pcap_file')->getClientOriginalExtension(), ['pcap', 'pcapng'])){
            return redirect()->back()->with('error', 'File upload failed, incorrect file extension');
        }

        try {
            $fileName = $request->file('pcap_file')->getClientOriginalName();

            // Pcaps are stored with a timestamp + session ID prefix
            $rebuiltFileName = now()->format('Y-m-d_h:i:s') . '_' . $sessionID . '_' . $fileName;
            $request->file('pcap_file')->storeAs('pcap', $rebuiltFileName);

            // 1. Dispatch job & log to DB
            $uuid = $this->handleNewJob($rebuiltFileName);

            // 2. Increment analyze_counter for the current user
            $ipMarker = IpMarker::where('ip_address', $currentIP)->first();
            if ($ipMarker) {
                $ipMarker->update([
                    'analyze_counter' => $ipMarker->analyze_counter + 1,
                ]);
            }

            return redirect()->route('pcap.status', ['id' => $uuid])->with('success', 'File uploaded successfully, analysis started.');

        } catch (Exception $e) {
            Log::error('File save failed', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Only passes metadata — packets are fetched separately with packets()
     */
    public function show(string $id)
    {
        $job = RedisJob::where('redis_id', $id)->firstOrFail();
        $status = $job->status;

        // Default props
        $props = [
            'id' => $id,
            'status' => $status,
            'progress' => $job->progress_percentage,
            'expires_at' => $job->expires_at
                ? \Carbon\Carbon::parse($job->expires_at)->diffForHumans(['parts' => 2, 'join' => true])
                : null,
            'message'  => '',

            // Metadata used by the Overview tab and time formatting
            'total_bytes' => 0,
            'first_packet_time' => 0,
            'last_packet_time' => 0,
            'total_packets' => 0,
        ];

        // Check the status of the job
        if ($status === 'dispatching') {
            $props['message'] = 'Analyzing PCAP... Please wait.';
            return Inertia::render('Analysis', $props);
        }

        if ($status === 'failed') {
            $props['message'] = $job->error_message ?? 'Analysis failed due to a system error. Error code: 3';
            return Inertia::render('Analysis', $props);
        }

        // If we are here, everything went well
        $props['total_packets'] = Packet::where('redis_id', $id)->count();
        $props['total_bytes']   = (int) Packet::where('redis_id', $id)->sum('captured_packet_length');

        $first = Packet::where('redis_id', $id)->orderBy('packet_number', 'asc')->value('timestamp');
        $last  = Packet::where('redis_id', $id)->orderBy('packet_number', 'desc')->value('timestamp');

        $props['first_packet_time'] = $first ? (float) $first : 0;
        $props['last_packet_time']  = $last  ? (float) $last  : 0;

        return Inertia::render('Analysis', $props);
    }

    /**
     * JSON for virtual-scrolling packets
     */
    public function packets(Request $request, string $id): JsonResponse
    {
        $job = RedisJob::where('redis_id', $id)->firstOrFail();

        if ($job->status !== 'finished') {
            return response()->json(['error' => 'Analysis not complete.'], 409);
        }

        $perPage = min((int) $request->query('per_page', 100), 500);
        $page = max((int) $request->query('page', 1), 1);
        $query = trim($request->query('q', ''));

        $queryBuilder = Packet::where('redis_id', $id);

        if ($query !== '') {
            $term = "%{$query}%";

            $queryBuilder->where(function ($q) use ($term) {
                $q->where('src_ip', 'like', $term)
                ->orWhere('dst_ip', 'like', $term)
                ->orWhere('l3_protocol', 'like', $term)
                ->orWhere('l4_protocol', 'like', $term)
                ->orWhere('l7_protocol', 'like', $term);
            });
        }

        $total = $queryBuilder->count();

        $packets = $queryBuilder
            ->orderBy('packet_number', 'asc')
            ->forPage($page, $perPage)
            ->get([
                'packet_id',
                'packet_number',
                'timestamp',
                'l3_protocol',
                'l4_protocol',
                'l7_protocol',
                'src_ip',
                'dst_ip',
                'src_port',
                'dst_port',
                'captured_packet_length',
                'raw_hex',
            ]);

        return response()->json([
            'data' => $packets,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ]);
    }

    /**
     * Dispatch redis job and log it into db under status 'dispatching'
     */
    private function handleNewJob($rebuiltFileName)
    {
        $uuid = (string) Str::uuid();

        AnalyzePcap::dispatch($uuid, $rebuiltFileName);

        RedisJob::insert([
            'redis_id' => $uuid,
            'file_path' => $rebuiltFileName,
            'status' => 'dispatching',
        ]);

        return $uuid;
    }
}