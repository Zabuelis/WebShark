<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\IpMarker;

class DeleteExpiredIpMarkers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delete-expired-ip-markers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deletes expired IPs used for rate limiting';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        IpMarker::where('expires_at', '<', now())->delete();
    }
}
