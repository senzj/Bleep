<?php

use App\Http\Controllers\Auth\Login;
use App\Http\Controllers\Auth\Logout;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\Register;
use App\Http\Controllers\BleepController;
use App\Http\Controllers\Bleep\LikesController;
use App\Http\Controllers\Bleep\CommentsController;

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


// Bleep Home page
Route::get('/', [BleepController::class, 'index']);


// Public: comments listing and count (allow guests to view comments)
Route::get('/bleeps/{bleep}/comments', [CommentsController::class, 'index']);
Route::get('/bleeps/{bleep}/comments/count', [CommentsController::class, 'count']);


// Protected Auth Routes
Route::middleware('auth')->group((function () {

    // Bleep Resource Routes
    Route::post('/bleeps', [BleepController::class, 'store']);
    Route::put('/bleeps/{bleep}', [BleepController::class, 'update']);
    Route::delete('/bleeps/{bleep}', [BleepController::class, 'destroy']);

    // Likes Routes (require auth)
    Route::post('/bleeps/{bleep}/like', [LikesController::class, 'toggle']);
    Route::get('/bleeps/{bleep}/likes-count', [LikesController::class, 'count']);

    // Comments Routes (only store/delete require auth)
    Route::post('/bleeps/{bleep}/comments', [CommentsController::class, 'store'])->middleware('auth');
    Route::delete('/comments/{comment}', [CommentsController::class, 'destroy'])->middleware('auth');
}));

