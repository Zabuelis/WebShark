<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;
use App\Models\RedisJob;
use Throwable;
use Illuminate\Support\Facades\File;


class AnalyzePcap implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;
    private $filePath;
    private $errorCounter = 0;
    private $errorLimit = 3;

    public function __construct(public string $uuid, public string $fileName)
    {
        $this->filePath = storage_path('app/private/pcap/' . $this->fileName);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $scriptPath = base_path('scapy/analyze.py');
    
        // env() function to get the Python path, the second parameter is a fallback
        $pythonPath = env('PYTHON_BINARY', 'python3');

        $result = Process::timeout($this->timeout)
            ->run("$pythonPath $scriptPath $this->filePath $this->uuid");

        if (!$result->successful()) {
            $errorMessage = $this->extractErrorMessage(
                $result->output(),
                $result->errorOutput()
            );

            RedisJob::where('redis_id', '=', $this->uuid)->update([
                'status' => 'failed',
                'error_message' => $errorMessage,
                'expires_at' => now()->addMinutes(10),
            ]);

            Log::error("Python error for {$this->uuid}: {$errorMessage}");
            $this->removeFile();
            return;
        }

        RedisJob::where('redis_id', '=', $this->uuid)->update([
            'status' => 'finished',
            'expires_at' => now()->addHours(2),
        ]);
       $this->removeFile();
    }

    // On job fail change the status and remove the file
    public function failed(?Throwable $exception): void
    {
        RedisJob::where('redis_id', '=', $this->uuid)->update([
            'status' => 'failed',
            'error_message' => $exception?->getMessage() ?? 'Analysis failed due to an system error. Error code: 1',
            'expires_at' => now()->addMinutes(10),
        ]);
        $this->removeFile();
    }

    private function removeFile(): void{
        if(file_exists($this->filePath)){
            unlink($this->filePath);
        }
    }

    /**
     * Try to extract a user-friendly error from the Python script's output.
     */
    private function extractErrorMessage(string $stdout, string $stderr): string
    {
        // First, try parsing structured JSON from stdout
        $decoded = json_decode(trim($stdout), true);
        if ($decoded && isset($decoded['error'])) {
            return $decoded['error'];
        }

        // Fall back to stderr
        if (!empty(trim($stderr))) {
            return trim($stderr);
        }

        return 'Analysis failed due to an system error. Error code: 2';
    }

}