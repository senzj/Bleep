<?php

use App\Http\Controllers\BleepController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BleepController::class, 'index']);

Route::post('/bleeps', [BleepController::class, 'store']);
