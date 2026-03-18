<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\RedisJob;
use App\Models\Packet;
use App\Jobs\AnalyzePcap;
use App\Models\IpMarker;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
            $rebuiltFileName =
                now()->format('Y-m-d_h:i:s') . '_' . $sessionID . '_' . $fileName;
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

            // This instead of rendering a new page returns a modal view with json data because there is no inertia page to render
            // Testing for now should be done by manually entering the URL
            return redirect('/pcap/analysis/' . $uuid);

        } catch (Exception $e) {
            Log::error('File save failed', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function show(String $id){
        // Return only the status column from redis_job table
        $jobStatus = RedisJob::where('redis_id', '=', $id)->pluck('status')->first();

        // Check the status of the job
        if ($jobStatus === 'dispatching') {
            return response()->json([
                'status' => 'dispatching',
                'message' => 'Still analyzing, try refreshing in a few seconds.',
            ]);
        } else if ($jobStatus === 'failed'){
            return response()->json([
                'status' => 'failed',
                'message' => 'Analysis failed, please retry later.',
            ]);
        }

        // Return packets related to the job id from packet table
        $data = Packet::where('redis_id', '=', $id)->orderBy('packet_id', 'asc')->get();

        return response()->json([
            'packets' => $data
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