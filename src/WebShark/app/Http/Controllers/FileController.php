<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Jobs\AnalyzePcap;


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

        try {
            // Preserving the pcap/pcapng extension, because laravel does not
            $fileName = $request->file('pcap_file')->getClientOriginalName();

            // Pcaps are stored inside storage/app/private/pcap directory with a name combination sessionID_originalname.pcap
            $rebuiltFileName = $sessionID . "_" . $fileName;
            $request->file('pcap_file')->storeAs('pcap', $rebuiltFileName);

            // Generate a unique ID for this analysis
            $uuid = (string) Str::uuid();

            // Dispatch the job to analyze the pcap file
            AnalyzePcap::dispatch($uuid, $rebuiltFileName);

            return response()->json([
                'success' => 'File upload was successful. Analysis started.',
                'analysis_id' => $uuid,
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
}
