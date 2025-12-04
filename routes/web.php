<?php

use App\Http\Controllers\Auth\Login;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Auth\Logout;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\Register;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\PostController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\BleepController;
use App\Http\Controllers\SocialController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\FollowingController;
use App\Http\Controllers\Bleep\LikesController;
use App\Http\Controllers\Bleep\ShareController;
use App\Http\Controllers\Bleep\RepostController;
use App\Http\Controllers\Users\ProfileController;
use App\Http\Controllers\Bleep\CommentsController;
use App\Http\Controllers\RememberedDeviceController;
use App\Http\Controllers\Api\Auth\ValidationController;
use App\Http\Controllers\Bleep\CommentsLikesController;
use App\Http\Controllers\Bleep\CommentsRepliesController;

// php info test
// Route::get('/phpinfo', function() {
//     phpinfo();
// });

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

Route::get('/bleeps/comments/{comment}/replies', [CommentsRepliesController::class, 'index'])
    ->name('comments.replies.index');

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

    Route::post('/bleeps/comments/{comment}/likes', [CommentsLikesController::class, 'store'])->name('comments.likes.store');
    Route::delete('/bleeps/comments/{comment}/likes', [CommentsLikesController::class, 'destroy'])->name('comments.likes.destroy');

    Route::post('/bleeps/comments/{comment}/replies', [CommentsRepliesController::class, 'store'])
        ->name('comments.replies.store');

    // Reposts
    Route::post('/bleeps/{bleep}/repost', [RepostController::class, 'store'])->name('bleeps.repost.store');
    Route::delete('/bleeps/{bleep}/repost', [RepostController::class, 'destroy'])->name('bleeps.repost.destroy');

    // Follow/Unfollow Routes
    Route::post('/bleeper/{user}/follow', [FollowingController::class, 'toggle']);

    // Socials Routes
    // Search users for people suggestions
    Route::get('/api/users/search', [SocialController::class, 'searchUsers'])
        ->name('api.users.search');

    // Settings Routes
    Route::get('/settings', fn () => redirect()->route('settings.profile'))
        ->name('settings');

    Route::get('/settings/profile', [SettingsController::class, 'editProfile'])
        ->name('settings.profile');
    Route::put('/settings/profile', [SettingsController::class, 'updateProfile'])
        ->name('settings.profile.update');

    Route::get('/settings/password', [SettingsController::class, 'editPassword'])
        ->name('settings.password');
    Route::put('/settings/password', [SettingsController::class, 'updatePassword'])
        ->name('settings.password.update');

    Route::get('settings/devices', [SettingsController::class, 'devices'])
        ->name('settings.devices');
    Route::delete('settings/devices/{sessionId}/revoke/session', [SettingsController::class, 'revokeSession'])
        ->name('settings.devices.revoke');
    Route::delete('settings/devices/{device}/revoke/device', [SettingsController::class, 'revokeDevice'])
        ->name('settings.devices.device.revoke');

    Route::get('settings/logs', [SettingsController::class, 'logs'])
        ->name('settings.logs');

    // User report submission
    Route::post('/reports', [ReportsController::class, 'store'])
        ->name('reports.store');


    // Admin/Mod routes
    Route::middleware('can:is_admin')->group(function () {
        // Admin Dashboard
        Route::get('/admin/dashboard', [AdminController::class, 'index'])
            ->name('admin.dashboard');

        Route::get('/admin/dashboard/chart-data', [AdminController::class, 'dashboardChartData'])
            ->name('admin.dashboard.chart-data');


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


        // Users Management
        Route::get('/admin/users', [AdminController::class, 'users'])
            ->name('admin.users');

        Route::post('/admin/users/{user}/update', [AdminController::class, 'updateUsers'])
            ->name('admin.users.update');


        // Devices and Sessions Management
        Route::get('/admin/devices', [AdminController::class, 'devices'])
            ->name('admin.devices');

        Route::delete('/admin/devices/{sessionId}/revoke', [AdminController::class, 'revokeSession'])
            ->name('admin.devices.revoke');

        Route::delete('/admin/devices/device/{device}/revoke', [AdminController::class, 'revokeDevice'])
            ->name('admin.devices.device.revoke');

        // System Logs
        Route::get('/admin/logs', [AdminController::class, 'logs'])
            ->name('admin.logs');


    });
}));

// remembered devices routes
Route::middleware(['auth'])->group(function () {
    Route::get('/settings/devices', [SettingsController::class, 'devices'])->name('settings.devices');
    Route::delete('/settings/devices/{remembered_device}', [RememberedDeviceController::class, 'destroy'])
        ->name('settings.devices.destroy');
});



/*
|--------------------------------------------------------------------------
| API Routes for Validation
|--------------------------------------------------------------------------
*/

// REGISTRATION Routes
// Check username availability
Route::post('/check-username', [ValidationController::class, 'checkUsername'])
    ->middleware(['throttle:60,1', 'guest'])
    ->name('check.username');

// Check email availability
Route::post('/check-email', [ValidationController::class, 'checkEmail'])
    ->middleware(['throttle:60,1', 'guest'])
    ->name('check.email');


// MEDIA STREAMING ROUTE
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

Route::get('/media/stream', function (Request $request) {
    $path = $request->query('path');
    $disk = Storage::disk('public');

    if (!$path || !$disk->exists($path)) {
        abort(404);
    }

    $fullPath = $disk->path($path);
    $size = filesize($fullPath);
    $mime = Storage::mimeType($path) ?? mime_content_type($fullPath) ?? 'application/octet-stream';
    $range = $request->header('Range');

    $start = 0;
    $end = $size - 1;

    if ($range && preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
        $start = (int) $matches[1];
        if ($matches[2] !== '') {
            $end = min((int) $matches[2], $size - 1);
        }
    }

    $length = $end - $start + 1;
    $status = $range ? 206 : 200;
    $headers = [
        'Content-Type' => $mime,
        'Accept-Ranges' => 'bytes',
        'Cache-Control' => 'public, max-age=31536000',
    ];

    if ($status === 206) {
        $headers['Content-Length'] = $length;
        $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
    } else {
        $headers['Content-Length'] = $size;
    }

    return new StreamedResponse(function () use ($fullPath, $start, $length) {
        $handle = fopen($fullPath, 'rb');
        if ($handle === false) {
            return;
        }

        fseek($handle, $start);
        $bytesRemaining = $length;

        while ($bytesRemaining > 0 && !feof($handle)) {
            $chunk = fread($handle, min(8192, $bytesRemaining));
            if ($chunk === false) break;

            $bytesRemaining -= strlen($chunk);
            echo $chunk;
            flush();
        }

        fclose($handle);
    }, $status, $headers);
})->name('media.stream');
