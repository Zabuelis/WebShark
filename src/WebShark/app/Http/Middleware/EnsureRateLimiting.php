<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\IpMarker;
use Illuminate\Support\Carbon;

class EnsureRateLimiting
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $currentIP = request()->ip();
        $ipMarker = IpMarker::where('ip_address', $currentIP)->first();

        if(!$ipMarker){
            IpMarker::insert([
                'ip_address' => $currentIP,
                'expires_at' => now()->addMinutes(15),
                'analyze_counter' => 0
            ]);
            return $next($request);
        }

        if($ipMarker->analyze_counter >= 10){
            $timeDifference = Carbon::parse($ipMarker->expires_at)->diffForHumans();
            return redirect()->back()->with('error', 'You have reached your analysis limit. Please try again in ' . $timeDifference . '.');
        }

        return $next($request);
    }
}
