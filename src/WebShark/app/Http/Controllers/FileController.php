<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FileController extends Controller
{
    public function uploadPcap(Request $request){
        $validated = $request->validate([
            'pcap_file' => 'required|mimes:pcap,pcapng'
        ]);

        return redirect()->back()->with('success','File uploaded');
    }

}
