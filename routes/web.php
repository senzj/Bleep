<?php

use App\Http\Controllers\Auth\Login;
use App\Http\Controllers\Auth\Logout;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\Register;
use App\Http\Controllers\PostController;
use App\Http\Controllers\BleepController;
use App\Http\Controllers\FollowingController;
use App\Http\Controllers\Bleep\LikesController;
use App\Http\Controllers\Bleep\ShareController;
use App\Http\Controllers\Bleep\RepostController;
use App\Http\Controllers\Bleep\CommentsController;
use App\Http\Controllers\Users\ProfileController;

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


// PUBLIC ROUTES

// Bleep Home page
Route::get('/', [BleepController::class, 'index']);

// Comments
Route::get('/bleeps/comments/{bleep}/comments', [CommentsController::class, 'index']);
Route::get('/bleeps/comments/{bleep}/count', [CommentsController::class, 'count']);
Route::get('/bleeps/comments/{bleep}/html', [CommentsController::class, 'commentsHtml'])->name('comments.html');

// Shares
Route::post('/bleeps/{bleep}/share', [ShareController::class, 'store']);

Route::get('/s/{token}', [ShareController::class, 'redirect'])->name('shares.redirect');

// Bleep Posts (with soft-deleted support)
Route::get('/bleeps/{id}', [PostController::class, 'index'])
    ->name('post');

// Profile page
Route::get('/bleeper/{username}', [ProfileController::class, 'index'])
    ->name('user.profile');

// Protected Auth Routes
Route::middleware('auth')->group((function () {

    // Bleep Resource Routes
    Route::post('/bleeps', [BleepController::class, 'store']);
    Route::put('/bleeps/{bleep}/update', [BleepController::class, 'update']);
    Route::delete('/bleeps/{bleep}/delete', [BleepController::class, 'destroy']);

    // Likes Routes (require auth)
    Route::post('/bleeps/{bleep}/like', [LikesController::class, 'toggle']);
    Route::get('/bleeps/{bleep}/likes-count', [LikesController::class, 'count']);

    // Comments Routes
    Route::post('/bleeps/comments/{bleep}/post', [CommentsController::class, 'store']);
    Route::put('/bleeps/comments/{comment}/update', [CommentsController::class, 'update']);
    Route::delete('/bleeps/comments/{comment}/delete', [CommentsController::class, 'destroy']);
    Route::post('/bleeps/comments/{comment}/report', [CommentsController::class, 'report']);

    // Shares
    Route::post('/bleeps/{bleep}/share', [ShareController::class, 'store']);

    // Reposts
    Route::post('/bleeps/{bleep}/repost', [RepostController::class, 'store'])->name('bleeps.repost.store');
    Route::delete('/bleeps/{bleep}/repost', [RepostController::class, 'destroy'])->name('bleeps.repost.destroy');

    // Follow/Unfollow Routes
    Route::post('/bleeper/{user}/follow', [FollowingController::class, 'toggle']);

    // Profile Settings Route

}));

