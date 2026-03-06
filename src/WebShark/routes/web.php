<?php

use App\Http\Controllers\FileController;
use App\Http\Controllers\PcapController;
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

Route::get('/pcap/status/{uuid}', [PcapController::class, 'status'])
    ->middleware('analysis.exists')
    ->name('pcap.status');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

require __DIR__ . '/settings.php';