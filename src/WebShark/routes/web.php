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
        PcapController::class,
        'create',
    ])->name('upload.pcap');
});

// Route to display packet data
Route::get('/pcap/analysis/{id}', [PcapController::class, 'show'])
    ->middleware('analysis.exists')
    ->name('pcap.status');

require __DIR__ . '/settings.php';