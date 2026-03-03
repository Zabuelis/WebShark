<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzePcap;
use App\Models\IpMarker;
use App\Models\RedisJob;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileController extends Controller
{
    public function uploadPcap(Request $request)
    {
        $validated = $request->validate([
            // Now the file size is limited to 10 mb
            'pcap_file' => 'required|file|max:10240',
        ]);

        $sessionID = session()->getId();
        $currentIP = $request->ip();

        // Manual check for pcap extensions because Laravel mime detection can be mid
        if (
            !in_array($request->file('pcap_file')->getClientOriginalExtension(), [
                'pcap',
                'pcapng',
            ])
        ) {
            return response()->json(
                [
                    'error' => 'File upload failed, incorrect file extension',
                ],
                422
            );
        }

        try {
            $fileName = $request->file('pcap_file')->getClientOriginalName();

            // Pcaps are stored with a timestamp + session ID prefix
            $rebuiltFileName =
                now()->format('Y-m-d_h:i:s') . '_' . $sessionID . '_' . $fileName;
            $request->file('pcap_file')->storeAs('pcap', $rebuiltFileName);

            // 1. Dispatch job & log to DB (from origin/dev)
            $uuid = $this->handleNewJob($rebuiltFileName);

            // 2. Increment analyze_counter for the current user
            $ipMarker = IpMarker::where('ip_address', $currentIP)->first();
            if ($ipMarker) {
                $ipMarker->update([
                    'analyze_counter' => $ipMarker->analyze_counter + 1,
                ]);
            }

            // 3. Set Cache for fast status polling
            Cache::put(
                'analysis_' . $uuid,
                [
                    'status' => 'processing',
                ],
                600
            );

            return response()->json([
                'success' => 'File upload was successful. Analysis started.',
                'analysis_id' => $uuid,
                'redirect_url' => '/pcap/status/' . $uuid,
            ]);
        } catch (Exception $e) {
            Log::error('File save failed', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
            return response()->json(
                [
                    'error' => 'File upload failed',
                ],
                422
            );
        }
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