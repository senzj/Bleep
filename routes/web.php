<?php

use App\Http\Controllers\Auth\Login;
use App\Http\Controllers\Auth\Logout;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\Register;
use App\Http\Controllers\BleepController;

// REGISTER
Route::view('/register', 'auth.register')
    ->middleware('guest')
    ->name('register');

Route::post('/register', Register::class)
    ->middleware('guest');


// LOGIN
Route::view('/login', 'auth.login')
    ->middleware('guest')
    ->name('login');

Route::post('/login', Login::class)
    ->middleware('guest');


// LOGOUT
Route::post('/logout', Logout::class)
    ->middleware('auth')
    ->name('logout');


// Bleep Routes
Route::get('/', [BleepController::class, 'index']);

Route::middleware('auth')->group((function () {

    Route::post('/bleeps', [BleepController::class, 'store']);
    Route::get('/bleeps/{bleep}/edit', [BleepController::class, 'edit']);
    Route::put('/bleeps/{bleep}', [BleepController::class, 'update']);
    Route::delete('/bleeps/{bleep}', [BleepController::class, 'destroy']);

}));

