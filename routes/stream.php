<?php

use App\Http\Controllers\MediaImageController;
use App\Http\Controllers\MediaStreamController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Media Streaming Routes
|--------------------------------------------------------------------------
|
| Registered outside the web middleware group (see bootstrap/app.php) so a
| long video stream never holds the PHP session lock. GET routes also answer
| HEAD requests automatically, which browsers and CDNs use for metadata.
|
*/

Route::get('/media/stream/{path}', MediaStreamController::class)
    ->name('media.stream')
    ->where('path', '.*');

Route::get('/media/image/{size}/{path}', MediaImageController::class)
    ->name('media.image')
    ->where('size', 'thumb|view')
    ->where('path', '.*');
