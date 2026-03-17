<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\RedisJob;
use App\Models\Packet;

class PcapController extends Controller
{
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
}