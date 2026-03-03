<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use App\Http\Controllers\FileController;
use Illuminate\Support\Facades\Cache;
use App\Http\Middleware\EnsureRateLimiting;


Route::get('/', function () {
    return Inertia::render('Home', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');


Route::middleware('rateLimit')->group( function(){
    Route::post('/file/uploadPcap', [FileController::class, 'uploadPcap'])->name('upload.pcap');

Route::get('/pcap/status/{uuid}', function ($uuid) {
    $data = Cache::get('analysis_' . $uuid);

    // no cache entry means this UUID does not exist
    if ($data === null) {
        return response()->json([
            'status' => 'not_found',
            'message' => 'No analysis found for this ID.',
        ], 404);
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
});


require __DIR__.'/settings.php';
