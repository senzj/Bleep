<?php

use App\Http\Controllers\Auth\Login;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Auth\Logout;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\Register;
use App\Http\Controllers\PostController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\BleepController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\FollowingController;
use App\Http\Controllers\Bleep\LikesController;
use App\Http\Controllers\Bleep\ShareController;
use App\Http\Controllers\Bleep\RepostController;
use App\Http\Controllers\Users\ProfileController;
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


// PUBLIC ROUTES

// Bleep Home page
Route::get('/', [BleepController::class, 'index']);

// Bleep views
Route::post('/bleeps/{bleep}/view', [BleepController::class, 'recordView'])
    ->name('bleeps.view');

// Bleep Lazy Load
Route::get('/bleeps/lazy-load', [BleepController::class, 'lazyLoad'])
    ->name('bleeps.lazyload');

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

Route::get('/bleeps/{id}/comments/load-more', [PostController::class, 'loadMorePostComments'])
    ->name('bleeps.comments.loadmore');


// Profile page
Route::get('/bleeper/{username}', [ProfileController::class, 'index'])
    ->name('user.profile');

// Lazy fragments (AJAX)
Route::get('/users/{username}/bleeps', [ProfileController::class, 'bleeps'])->name('user.bleeps');
Route::get('/users/{username}/reposts', [ProfileController::class, 'reposts'])->name('user.reposts');


// Banned User Page (must be outside auth group)
Route::get('/banned', function () {
    if (!Auth::check() || !Auth::user()->is_banned) {
        return redirect('/');
    }
    return view('pages.banned');
})->middleware('auth')->name('banned');


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

    // Settings Routes
    Route::get('/settings', fn () => redirect()->route('settings.profile'))
        ->name('settings');

    Route::get('/settings/profile', [\App\Http\Controllers\SettingsController::class, 'editProfile'])
        ->name('settings.profile');
    Route::put('/settings/profile', [\App\Http\Controllers\SettingsController::class, 'updateProfile'])
        ->name('settings.profile.update');

    Route::get('/settings/password', [\App\Http\Controllers\SettingsController::class, 'editPassword'])
        ->name('settings.password');
    Route::put('/settings/password', [\App\Http\Controllers\SettingsController::class, 'updatePassword'])
        ->name('settings.password.update');

    // User report submission
    Route::post('/reports', [ReportsController::class, 'store'])->middleware('auth')->name('reports.store');


    // Admin/Mod routes
    Route::middleware(['auth', 'can:is_admin'])->group(function () {
        // Admin Dashboard
        Route::get('/admin/dashboard', [AdminController::class, 'index'])
            ->name('admin.dashboard');


        // Reports Dashboard
        Route::get('/admin/reports', [ReportsController::class, 'index'])
            ->name('admin.reports');

        Route::post('/admin/reports/{report}/mark-reviewed', [ReportsController::class, 'markReviewed'])
            ->name('admin.reports.markReviewed');

        Route::post('/admin/reports/{report}/delete-bleep', [ReportsController::class, 'deleteBleep'])
            ->name('admin.reports.deleteBleep');

        Route::post('/admin/reports/{report}/ban-reporter', [ReportsController::class, 'banReporter'])
            ->name('admin.reports.banReporter');

        Route::post('/admin/reports/{report}/dismiss', [ReportsController::class, 'dismiss'])
            ->name('admin.reports.dismiss');


        //
    });
}));

