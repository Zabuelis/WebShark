<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\RedisJob;
use App\Models\IpMarker;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Jobs\AnalyzePcap;


class FileController extends Controller
{
    public function uploadPcap(Request $request){
        $validated = $request->validate([
            // Now the file size is limited to 10 mb
            'pcap_file' => 'required|file|max:10240'
        ]);

        $sessionID = session()->getId();
        $currentIP = request()->ip();

        // Laravel does not distinguish mime type pcap,pcapng and returns an error therefore, a manual check is needed
        if(!in_array($request->file('pcap_file')->getClientOriginalExtension(), ['pcap', 'pcapng'])){
            return response()->json([
                'error' => 'File upload failed, incorrect file extension'
            ], 422);
        }

        try {
            // Preserving the pcap/pcapng extension, because laravel does not
            $fileName = $request->file('pcap_file')->getClientOriginalName();

            // Pcaps are stored inside storage/app/private/pcap directory with a name combination sessionID_originalname.pcap
            $rebuiltFileName = now()->format('Y-m-d_h:i:s') . '_' . $sessionID . '_' . $fileName;
            $request->file('pcap_file')->storeAs('pcap', $rebuiltFileName);

            // Generate a unique ID for this analysis
            $uuid = (string) Str::uuid();

            // Dispatch the job to analyze the pcap file
            AnalyzePcap::dispatch($uuid, $rebuiltFileName);

            Cache::put('analysis_' . $uuid, [
                'status' => 'processing',
            ], 600);

            $this->handleNewJob($rebuiltFileName);

            // Increment analyze_counter for the current user
            $ipMarker = IpMarker::where('ip_address', $currentIP)->first();
            IpMarker::where('ip_address', $currentIP)->update(['analyze_counter' => $ipMarker->analyze_counter + 1]);

            return response()->json([
                'success' => 'File upload was successful. Analysis started.',
                'analysis_id' => $uuid,
                'redirect_url' => '/pcap/status/' . $uuid,
            ]);
        } catch (Exception $e) {
            Log::error("File save failed", [
                'message' => $e->getMessage(),
                'exception' => $e
            ]);
            return response()->json([
                'error' => 'File upload failed'
            ], 422);
        }
    }

    // Dispatch redis job and log it into db under status 'dispatching'
    private function handleNewJob($rebuiltFileName){
            $uuid = (string) Str::uuid();
            //AnalyzePcap::dispatch($uuid, $rebuiltFileName);

            RedisJob::insert([
                'redis_id' => $uuid,
                'file_path' => $rebuiltFileName,
                'status' => 'dispatching'
            ]);
    }
}
