<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use App\Http\Controllers\FileController;
use App\Http\Middleware\EnsureRateLimiting;


Route::get('/', function () {
    return Inertia::render('Home', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');


Route::middleware('rateLimit')->group( function(){
    Route::post('/file/uploadPcap', [FileController::class, 'uploadPcap'])->name('upload.pcap');
});


require __DIR__.'/settings.php';
