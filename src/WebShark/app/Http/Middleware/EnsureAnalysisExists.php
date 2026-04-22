<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\AnalysisJob;
use Illuminate\Support\Str;

class EnsureAnalysisExists
{
    public function handle(Request $request, Closure $next): Response
    {
        $uuid = $request->route('id');

        // Check whether provided UUID is valid and exists in DB
        if (!Str::isUuid($uuid) || !AnalysisJob::where('analysis_id', $uuid)->exists()) {
            return redirect()->route('home')->with('error', 'The analysis could not be found.');
        }

        return $next($request);
    }
}
