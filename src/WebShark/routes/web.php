<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use App\Http\Controllers\FileController;
use Illuminate\Support\Facades\Cache;


Route::get('/', function () {
    return Inertia::render('Home', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::post('/file/uploadPcap', [FileController::class, 'uploadPcap'])->name('upload.pcap');

Route::get('/pcap/status/{uuid}', function ($uuid) {
    $data = Cache::get('analysis_' . $uuid);

    if ($data) {
        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    return response()->json([
        'status' => 'processing'
    ]);
})->name('pcap.status');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
