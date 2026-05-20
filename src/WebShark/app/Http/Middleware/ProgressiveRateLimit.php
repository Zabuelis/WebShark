<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProgressiveRateLimit
{
    public function handle(Request $request, Closure $next)
    {
        $sessionKey = $request->session()->getId();
        $ip = $request->ip();
        
        $checks = [
            'session:' . $sessionKey, // Checks if browser is blocked
            'ip:' . $ip, // Checks if IP is blocked
        ];

        foreach ($checks as $key) {
            $blockedUntil = Cache::get("pcap_block:{$key}");

            if ($blockedUntil) {
                $expiry = Carbon::parse($blockedUntil);

                if (now()->lt($expiry)) {
                    $wait = now()->diffForHumans($expiry, true);
                    
                    Log::channel('audit')->warning('BLOCKED_REQUEST_INTERCEPTED', [
                        'ip' => $ip,
                        'session' => $sessionKey,
                        'key' => $key
                    ]);

                    return redirect('/')
                        ->with('error', "You have been temporarily blocked due to repeated violations. Try again in {$wait}.");
                }
                
                // Clean up expired block
                Cache::forget("pcap_block:{$key}");
            }
        }

        return $next($request);
    }

}