<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AnalysisJob;

class DeleteExpiredAnalysisJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delete-expired-redis-jobs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete expired redis jobs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        AnalysisJob::where('expires_at', '<', now())->where('status', '!=', 'dispatched')->delete();
    }
}
