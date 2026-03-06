<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureAnalysisExists
{
    public function handle(Request $request, Closure $next): Response
    {
        $uuid = $request->route('uuid');
        $data = Cache::get('analysis_' . $uuid);

        if ($data === null) {
            return response()->json(
                [
                    'status' => 'not_found',
                    'message' => 'No analysis found for this ID.',
                ],
                404
            );
        }

        $request->attributes->set('analysisData', $data);

        return $next($request);
    }
}