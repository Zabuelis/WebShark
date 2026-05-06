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

// Use the native 'throttle' middleware
Route::middleware('throttle:pcap-uploads')->group(function () {
    Route::post('/file/uploadPcap', [PcapController::class, 'create'])->name('upload.pcap');
});

// Route to display packet data
Route::middleware('analysis.exists')->group(function () {

    Route::controller(PcapController::class)->group(function () {
        Route::get('/pcap/analysis/{id}', 'show')->name('pcap.status');
        Route::get('/pcap/analysis/{id}/packets', 'packets')->name('pcap.packets');
        Route::get('/pcap/analysis/{id}/flows', 'flows')->name('pcap.flows');
    });
});


require __DIR__ . '/settings.php';