<?php

use App\Http\Controllers\BleepController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BleepController::class, 'index']);

Route::post('/bleeps', [BleepController::class, 'store']);
Route::get('/bleeps/{bleep}/edit', [BleepController::class, 'edit']);
Route::put('/bleeps/{bleep}', [BleepController::class, 'update']);
Route::delete('/bleeps/{bleep}', [BleepController::class, 'destroy']);
