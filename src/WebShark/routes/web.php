<?php

use App\Http\Controllers\FileController;
use App\Http\Middleware\EnsureRateLimiting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Home', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

// Upload route with rate limiting
Route::middleware('rateLimit')->group(function () {
    Route::post('/file/uploadPcap', [
        FileController::class,
        'uploadPcap',
    ])->name('upload.pcap');
});

// PCAP Status polling logic
Route::get('/pcap/status/{uuid}', function ($uuid) {
    $data = Cache::get('analysis_' . $uuid);

    // no cache entry means this UUID does not exist
    if ($data === null) {
        return response()->json(
            [
                'status' => 'not_found',
                'message' => 'No analysis found for this ID.',
            ],
            404
        );
    }

    // job exists but hasn't finished yet
    if ($data['status'] === 'processing') {
        return response()->json([
            'status' => 'processing',
            'message' => 'Still analyzing, try refreshing in a few seconds.',
        ]);
    }

    return response()->json($data);
})->name('pcap.status');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

require __DIR__ . '/settings.php';