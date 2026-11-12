<?php

use App\Http\Controllers\ChunkedUploadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Resumable Media Upload Routes
|--------------------------------------------------------------------------
|
| Large files are split into small chunks so a refresh (or a dropped
| connection) only loses the chunk that was in flight. These routes are
| loaded from routes/web.php and therefore run inside the web middleware,
| which is required for the authenticated session and CSRF protection.
|
*/

Route::middleware('auth')->prefix('uploads')->name('uploads.')->group(function () {
    Route::get('/', [ChunkedUploadController::class, 'index'])->name('index');
    Route::post('/init', [ChunkedUploadController::class, 'init'])->name('init');
    Route::get('/{upload:upload_id}', [ChunkedUploadController::class, 'status'])->name('status');
    Route::post('/{upload:upload_id}/chunk', [ChunkedUploadController::class, 'chunk'])->name('chunk');
    Route::post('/{upload:upload_id}/complete', [ChunkedUploadController::class, 'complete'])->name('complete');
    Route::delete('/{upload:upload_id}', [ChunkedUploadController::class, 'destroy'])->name('destroy');
});
