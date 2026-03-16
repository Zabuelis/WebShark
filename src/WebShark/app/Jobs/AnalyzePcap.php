<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;
use App\Models\RedisJob;

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
            ->run("$pythonPath $scriptPath $filePath");

        if (!$result->successful()) {
            Log::error("Python error for {$this->uuid}: " . $result->errorOutput());
            RedisJob::where('redis_id', '=', $this->uuid)->update([
                'status' => 'failed',
                'expires_at' => now()->addMinutes(10),
            ]);
            return;
        }

        // Python outputs one JSON object per line
        // We split by newline and decode each line separately
        $lines = explode("\n", trim($result->output()));
        $packets = [];

        foreach ($lines as $line) {
            // skip empty lines
            if (empty($line)) {
                continue;
            }

            // skip error lines from Python
            if (str_starts_with($line, 'ERROR:')) {
                Log::error("Python: " . $line);
                continue;
            }

            $decoded = json_decode($line, true);
            // returns null if the line isn't valid JSON
            if ($decoded !== null) {
                $packets[] = $decoded;
            }
        }

        RedisJob::where('redis_id', '=', $this->uuid)->update([
            'status' => 'finished',
            'expires_at' => now()->addHours(2),
        ]);

        Cache::put('analysis_' . $this->uuid, [
            'status' => 'done',
            'total_packets' => count($packets),
            'packets' => $packets,
        ], 600);
    }
}