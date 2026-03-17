<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;
use App\Models\RedisJob;
use Illuminate\Support\Facades\File;


class AnalyzePcap implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

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

        $result = Process::timeout($this->timeout)
            ->run("$pythonPath $scriptPath $filePath $this->uuid");

        if (!$result->successful()) {
            RedisJob::where('redis_id', '=', $this->uuid)->update([
                'status' => 'failed',
                'expires_at' => now()->addMinutes(10),
            ]);
            removeFile($filePath);
            Log::error("Python error for {$this->uuid}: " . $result->errorOutput());
            return;
        }

        RedisJob::where('redis_id', '=', $this->uuid)->update([
            'status' => 'finished',
            'expires_at' => now()->addHours(2),
        ]);
        removeFile($filePath);
    }

    private function removeFile($filePath){
        if(file_exists($filePath)){
            unlink($filePath);
        }
    }
}