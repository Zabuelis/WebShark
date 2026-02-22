<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

class AnalyzePcap implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $uuid, public string $fileName)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $scriptPath = base_path('scapy/analyze.py');
        $filePath = storage_path('app/private/pcap/' . $this->fileName);
        
        // env() function to get the Python path, the second parameter is a fallback
        $pythonPath = env('PYTHON_BINARY', 'python3');

        $result = Process::run("$pythonPath $scriptPath $filePath");

        if ($result->successful()) {
            Cache::put('analysis_' . $this->uuid, json_decode($result->output()), 600);
        } else {
            Log::error("Python error: " . $result->errorOutput());
        }
    }
}
