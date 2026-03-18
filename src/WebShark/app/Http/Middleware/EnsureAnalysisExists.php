<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use App\Models\RedisJob;
use Illuminate\Support\Str;

class EnsureAnalysisExists
{
    public function handle(Request $request, Closure $next): Response
    {
        $uuid = $request->route('id');
        // Check whether provided UUID is valid
        if(!Str::isUuid($uuid)){
            return response()->json(
                [
                    'status' => 'not_found',
                    'message' => 'No analysis found for this ID.',
                ],
                404
            );
        }

        // Check whether provided UUID job exists
        if (!RedisJob::where('redis_id', '=', $uuid)->exists()) {
            return response()->json(
                [
                    'status' => 'not_found',
                    'message' => 'No analysis found for this ID.',
                ],
                404
            );
        }
        return $next($request);
    }
}