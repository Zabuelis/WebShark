<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\AnalysisJob;
use App\Models\Packet;
use App\Jobs\AnalyzePcap;
use App\Models\IpMarker;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PcapController extends Controller
{
    // Upload a file and create a job for analysis
    public function create(Request $request)
    {
        $validated = $request->validate([
            // Now the file size is limited to 10 mb
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
            return redirect()->back()->with('error', "There was an error processing your request. Please try again...");
        }
    }

    public function show(String $id)
    {
        $job = AnalysisJob::where('analysis_id', $id)->firstOrFail();
        $status = $job->status;
        $l7_status = $job->l7_status;

        // Default props
        $props = [
            'id' => $id,
            'status' => $status,
            'l7_status' => $job->l7_status,
            'progress' => $job->progress_percentage,
            'expires_at' => $job->expires_at 
            ? \Carbon\Carbon::parse($job->expires_at)->diffForHumans([
                'parts' => 2,
                'join' => true,
            ]) 
            : null,
            'message' => '',
            'packets' => null,
            'total_bytes' => 0,
            'l3_distribution' => null,
            'l4_distribution' => null,
            'l7_distribution' => null,
            'top_talkers' => null,
            'size_distribution' => null,
            'first_packet_time' => 0,
            'last_packet_time' => 0,
        ];

        // Check the status of the job
        if ($status === 'dispatching') {
            $props['message'] = 'Analyzing PCAP... Please wait.';
            return Inertia::render('Analysis', $props);
        }

        if ($status === 'failed') {
            $props['message'] = $job->error_message ?? 'Analysis failed due to an system error. Error code: 3';
            return Inertia::render('Analysis', $props);
        }

        if ($l7_status === 'failed') {
            $props['message'] = $job->error_message;
        }

        // If we are here, everything went well
        $props['packets'] = Packet::where('analysis_id', $id)
                                    ->orderBy('packet_number', 'asc')
                                    ->paginate(20);
        
        $props['total_bytes'] = (int) Packet::where('analysis_id', $id)
                                            ->sum('captured_packet_length');

        $firstPacket = Packet::where('analysis_id', $id)
                               ->orderBy('packet_number', 'asc')
                               ->first();

        $props['l3_distribution'] = Packet::select('l3_protocol as protocol_name',  DB::raw('count (*) as records'))
            ->where('analysis_id', $id)
            ->whereNotNull('l3_protocol')
            ->groupBy('protocol_name')
            ->get();

        $props['l4_distribution'] = Packet::select('l4_protocol as protocol_name',  DB::raw('count (*) as records'))
            ->where('analysis_id', $id)
            ->whereNotNull('l4_protocol')
            ->groupBy('protocol_name')
            ->get();

        $props['top_talkers'] = Packet::select('src_ip as IP', DB::raw('count (*) as records'))
            ->where('analysis_id', $id)
            ->whereNotNull('src_ip')
            ->groupBy('IP')
            ->orderBy('records', 'desc')
            ->limit(15)
            ->get();

        // $props['size_distribution'] = Packet::select('packet_number', 'timestamp')
        //     ->where('analysis_id', $id)
        //     ->whereNotNull('timestamp')
        //     ->orderBy('packet_number', 'asc')
        //     ->get();

        $lastPacket = Packet::where('analysis_id', $id)->orderBy('packet_number', 'desc')->first();

        $props['first_packet_time'] = $firstPacket ? (float) $firstPacket->timestamp : 0;

        $props['last_packet_time'] = $lastPacket ? (float) $lastPacket->timestamp : 0;

        if ($l7_status === 'finished'){
            $props['l7_distribution'] = Packet::select(DB::raw("l7_attributes->>'Protocol' as protocol_name"), DB::raw('count (*) as records'))
                ->where('analysis_id', $id)
                ->whereRaw("l7_attributes->>'Protocol' IS NOT NULL")
                ->groupBy('protocol_name')
                ->get();
        }

        return Inertia::render('Analysis', $props);
    }

    /**
     * Dispatch analysis job and log it into db under status 'dispatching'
     */
    private function handleNewJob($rebuiltFileName)
    {
        $uuid = (string) Str::uuid();

        AnalyzePcap::dispatch($uuid, $rebuiltFileName);

        AnalysisJob::insert([
            'analysis_id' => $uuid,
            'file_path' => $rebuiltFileName,
            'status' => 'dispatching',
            'l7_status' => 'dispatching'
        ]);

        return $uuid;
    }
}