<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;

class FileController extends Controller
{
    public function uploadPcap(Request $request){
        $validated = $request->validate([
            // Laravel does not distinguish mime type pcap,pcapng and returns an error another validation way is needed
            // 'pcap_file' => 'required|mimes:pcap,pcapng|max:102400'
            'pcap_file' => 'required|file|max:102400'
        ]);
        return response()->json([
            'succeess' => true,
            'message' => 'File uploaded successfully'
        ]);
    }
}
