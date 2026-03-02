<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Api\Auth\ValidationController;
use App\Http\Controllers\Auth\Login;
use App\Http\Controllers\Auth\Logout;
use App\Http\Controllers\Auth\Register;
use App\Http\Controllers\Bleep\CommentsController;
use App\Http\Controllers\Bleep\CommentsLikesController;
use App\Http\Controllers\Bleep\CommentsRepliesController;
use App\Http\Controllers\Bleep\LikesController;
use App\Http\Controllers\Bleep\RepostController;
use App\Http\Controllers\Bleep\ShareController;
use App\Http\Controllers\BleepController;
use App\Http\Controllers\BlockedUsersController;
use App\Http\Controllers\FollowingController;
use App\Http\Controllers\FollowRequestController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SocialController;
use App\Http\Controllers\UserPreferencesController;
use App\Http\Controllers\Users\ProfileController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// php info test
// Route::get('/phpinfo', function() {
//     phpinfo();
// });

// REGISTER
Route::view('/register', 'auth.register')
    ->middleware('guest')
    ->name('register');

Route::post('/register', Register::class)
    ->middleware('guest', 'progressive.lockout:register');


// LOGIN
Route::view('/login', 'auth.login')
    ->middleware('guest')
    ->name('login');

Route::post('/login', Login::class)
    ->middleware('guest', 'progressive.lockout:login');


// LOGOUT
Route::post('/logout', Logout::class)
    ->middleware('auth')
    ->name('logout');


// PUBLIC ROUTES

// Bleep Home page
Route::get('/', [BleepController::class, 'index'])
    ->name('home');

// Bleep views
Route::post('/bleeps/{bleep}/view', [BleepController::class, 'recordView'])
    ->name('bleeps.view');

// Bleep Lazy Load
Route::get('/bleeps/lazy-load', [BleepController::class, 'lazyLoad'])
    ->name('bleeps.lazyload');

// Get single bleep data (for modal)
Route::get('/bleeps/{bleep}/data', [BleepController::class, 'show'])
    ->name('bleeps.show');

// Comments
Route::get('/bleeps/comments/{bleep}/comments', [CommentsController::class, 'index']);
Route::get('/bleeps/comments/{bleep}/count', [CommentsController::class, 'count']);

// Comment Replies
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

    // Bleep Chat/Message Page
    Route::get('/messages', function () {
        return view('chat');
    })->name('messages');

    // Bleep Resource Routes
    Route::post('/bleeps', [BleepController::class, 'store']);
    Route::match(['put', 'post'], '/bleeps/{bleep}/update', [BleepController::class, 'update']);
    Route::delete('/bleeps/{bleep}/delete', [BleepController::class, 'destroy']);

    // Likes Routes (require auth)
    Route::post('/bleeps/{bleep}/like', [LikesController::class, 'toggle']);
    Route::get('/bleeps/{bleep}/likes-count', [LikesController::class, 'count']);

    // Comments Routes
    Route::post('/bleeps/comments/{bleep}/post', [CommentsController::class, 'store'])
        ->middleware('progressive.lockout:comment')
        ->name('comments.store');
    Route::post('/bleeps/comments/{comment}/update', [CommentsController::class, 'update'])
        ->name('comments.update');
    Route::delete('/bleeps/comments/{comment}/delete', [CommentsController::class, 'destroy'])
        ->name('comments.destroy');
    Route::post('/bleeps/comments/{comment}/report', [CommentsController::class, 'report'])
        ->middleware('progressive.lockout:report')
        ->name('comments.report');

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
    // Social Page
    Route::get('/people', [SocialController::class, 'peoplePage'])
        ->name('social.people');
    // Search users for people suggestions
    Route::get('/api/users/search', [SocialController::class, 'searchUsers'])
        ->name('api.users.search');

    // Follow Request Routes
    Route::get('/requests', [FollowRequestController::class, 'index'])
        ->name('follow.requests');
    Route::post('/api/follow-requests', [FollowRequestController::class, 'store'])
        ->name('follow.requests.store');
    Route::delete('/api/follow-requests/user/{user}', [FollowRequestController::class, 'cancelByUserId'])
        ->name('follow.requests.cancel-by-user');
    Route::post('/api/follow-requests/{request}/accept', [FollowRequestController::class, 'accept'])
        ->name('follow.requests.accept');
    Route::post('/api/follow-requests/{request}/reject', [FollowRequestController::class, 'reject'])
        ->name('follow.requests.reject');
    Route::delete('/api/follow-requests/{request}', [FollowRequestController::class, 'cancel'])
        ->name('follow.requests.cancel');

    // Blocked Users Routes
    Route::get('/blocked', [BlockedUsersController::class, 'index'])
        ->name('blocked.users');
    Route::post('/blocked/{user}/block', [BlockedUsersController::class, 'store'])
        ->name('blocked.users.block');
    Route::delete('/blocked/{user}/unblock', [BlockedUsersController::class, 'destroy'])
        ->name('blocked.users.unblock');

    // Settings Routes
    Route::get('/settings', fn () => redirect()->route('settings.profile'))
        ->name('settings');

    Route::get('/settings/profile', [SettingsController::class, 'editProfile'])
        ->name('settings.profile');
    Route::put('/settings/profile', [SettingsController::class, 'updateProfile'])
        ->name('settings.profile.update');

    Route::get('/settings/preferences', [SettingsController::class, 'showPreferences'])
        ->name('settings.preferences');

    // User Preferences API routes
    Route::get('/api/preferences', [UserPreferencesController::class, 'index'])
        ->name('api.preferences.index');
    Route::post('/api/preferences/update', [UserPreferencesController::class, 'update'])
        ->name('api.preferences.update');
    Route::post('/api/preferences/batch', [UserPreferencesController::class, 'batchUpdate'])
        ->name('api.preferences.batch');
    Route::post('/api/preferences/sounds/upload', [UserPreferencesController::class, 'uploadSound'])
        ->name('api.preferences.sounds.upload');

    Route::get('/settings/password', [SettingsController::class, 'editPassword'])
        ->name('settings.password');
    Route::put('/settings/password', [SettingsController::class, 'updatePassword'])
        ->middleware('progressive.lockout:password-change')
        ->name('settings.password.update');

    Route::get('/settings/devices', [SettingsController::class, 'devices'])
        ->name('settings.devices');
    Route::delete('/settings/devices/{sessionId}/revoke/session', [SettingsController::class, 'revokeSession'])
        ->name('/settings.devices.revoke');
    Route::delete('/settings/devices/{device}/revoke/device', [SettingsController::class, 'revokeDevice'])
        ->name('settings.devices.device.revoke');

    Route::get('/settings/logs', [SettingsController::class, 'logs'])
        ->name('settings.logs');

    // User report submission
    Route::post('/reports', [ReportsController::class, 'store'])
        ->middleware('progressive.lockout:report')
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

        // System Visits
        Route::get('/admin/visits', [AdminController::class, 'visits'])
            ->name('admin.visits');

        Route::get('/admin/visits/data', [AdminController::class, 'visitsData'])
            ->name('admin.visits.data');

    });
}));


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

// Generate random username
Route::post('/generate-username', [ValidationController::class, 'generateRandomUsername'])
    ->middleware(['throttle:60,1', 'guest'])
    ->name('generate.username');


// MEDIA STREAMING ROUTE
// use Illuminate\Http\Request;
// use Symfony\Component\HttpFoundation\StreamedResponse;

Route::get('/media/stream/{path}', function ($path) {
    $fullPath = storage_path('app/public/' . $path);

    if (!file_exists($fullPath)) {
        abort(404);
    }

    $mimeType = mime_content_type($fullPath);
    $fileSize = filesize($fullPath);

    // Support range requests for video/audio streaming
    $headers = [
        'Content-Type' => $mimeType,
        'Accept-Ranges' => 'bytes',
    ];

    // Check if client sent a Range header
    if (request()->hasHeader('Range')) {
        $range = request()->header('Range');
        preg_match('/bytes=(\d+)-(\d*)/', $range, $matches);

        $start = intval($matches[1]);
        $end = $matches[2] ? intval($matches[2]) : $fileSize - 1;
        $length = $end - $start + 1;

        $headers['Content-Range'] = "bytes $start-$end/$fileSize";
        $headers['Content-Length'] = $length;

        $file = fopen($fullPath, 'rb');
        fseek($file, $start);
        $data = fread($file, $length);
        fclose($file);

        return response($data, 206, $headers);
    }

    // No range request, send entire file
    $headers['Content-Length'] = $fileSize;

    return response()->file($fullPath, $headers);
})->name('media.stream')->where('path', '.*');
