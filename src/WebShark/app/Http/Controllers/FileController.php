<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\RedisJob;
use App\Models\IpMarker;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;


class FileController extends Controller
{
    public function uploadPcap(Request $request){
        $validated = $request->validate([
            // Now the file size is limited to 10 mb
            'pcap_file' => 'required|file|max:10240'
        ]);

        $sessionID = session()->getId();

        // Laravel does not distinguish mime type pcap,pcapng and returns an error therefore, a manual check is needed
        if(!in_array($request->file('pcap_file')->getClientOriginalExtension(), ['pcap', 'pcapng'])){
            return response()->json([
                'error' => 'File upload failed, incorrect file extension'
            ], 422);
        }

        if($this->isAnalysisLimitReached()){
            return response()->json([
                'error' => 'You have reached your limit of analyzes, please wait until: '
            ], 422);
        }

        try {
            // Preserving the pcap/pcapng extension, because laravel does not
            $fileName = $request->file('pcap_file')->getClientOriginalName();

            // Pcaps are stored inside storage/app/private/pcap directory with a name combination sessionID_originalname.pcap
            $rebuiltFileName = $sessionID . "_" . $fileName;
            $request->file('pcap_file')->storeAs('pcap', $rebuiltFileName);

            $this->handleNewJob($rebuiltFileName);

            return response()->json([
                'success' => 'File upload was successful.',
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

    private function handleNewJob($rebuiltFileName){
            $uuid = (string) Str::uuid();
            //AnalyzePcap::dispatch($uuid, $rebuiltFileName);

            RedisJob::insert([
                'redis_id' => $uuid,
                'file_path' => $rebuiltFileName,
                'status' => 'dispatching'
            ]);
    }

    // Checks whether the user has analyses left. This can potentially be transformed into middleware
    private function isAnalysisLimitReached(): bool{
        $currentIP = request()->ip();
        $ipMarker = IpMarker::where('ip_address', $currentIP)->first();

        if(!$ipMarker){
            IpMarker::insert([
                'ip_address' => $currentIP,
                'expires_at' => now()->addMinutes(15),
                'analyze_counter' => 0
            ]);
            return false;
        }

        if($ipMarker->analyze_counter > 15){
            return true;
        } else {
            IpMarker::where('ip_address', $currentIP)->update(['analyze_counter' => $ipMarker->analyze_counter + 1]);
            return false;
        }

    }
}
