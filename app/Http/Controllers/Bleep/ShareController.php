<?php

namespace App\Http\Controllers\Bleep;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Models\Bleep;
use App\Models\Repost;
use App\Models\Share;
use App\Services\Notifications\NotificationData;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShareController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * create if user shares a bleep post
     */
    public function store(Request $request, Bleep $bleep)
    {
        abort_unless($bleep->isVisibleTo(Auth::user()), 404);

        $sessionKey = $request->session()->getId();
        // Pass a Carbon instance so the `shared_on` date cast and the lookup
        // bind the same datetime value. A plain `Y-m-d` string fails to match
        // rows stored by SQLite (and therefore duplicates the daily share).
        $day = now()->startOfDay();

        $share = Share::firstOrCreate(
            [
                'bleep_id' => $bleep->id,
                'shared_on' => $day,
                'user_id' => Auth::id(),
                'session_key' => Auth::check() ? null : $sessionKey,
            ],
            ['token' => Share::generateToken()]
        );

        $share->touch();

        if ($share->wasRecentlyCreated) {
            $this->notifyBleepAuthor($bleep);
        }

        $shares = Share::where('bleep_id', $bleep->id)->count();
        $reposts = Repost::where('bleep_id', $bleep->id)->count();

        $payload = [
            'success' => true,
            'share_url' => url("/s/{$share->token}"),
            'shares_count' => $shares,
            'reposts_count' => $reposts,
            'total_shares' => $shares + $reposts,
        ];

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($payload);
        }

        return back()->with($payload);
    }

    private function notifyBleepAuthor(Bleep $bleep): void
    {
        $bleep->loadMissing('user');

        if (! $bleep->user) {
            return;
        }

        $this->notifications->notify($bleep->user, NotificationType::BleepShared, [
            'actor' => Auth::user(),
            'subject' => $bleep,
            'url' => route('post', $bleep->id),
            'group_key' => 'share:bleep:'.$bleep->id,
            'data' => NotificationData::bleep($bleep),
        ]);
    }

    public function redirect(string $token)
    {
        // Find share, if not found redirect to home
        $share = Share::with('bleep')->where('token', $token)->first();

        if (! $share) {
            return redirect('/')->with('info', 'This share link is no longer available.');
        }

        // Get bleep (including soft-deleted)
        $bleep = $share->bleep;

        // If bleep doesn't exist at all (force deleted), redirect to home
        if (! $bleep) {
            return redirect('/')->with('info', 'This bleep is no longer available.');
        }

        // If bleep is soft-deleted, show deleted page
        if ($bleep->trashed()) {
            return redirect()->route('post', $bleep->id);
        }

        if (! $bleep->isVisibleTo(Auth::user())) {
            return redirect('/')->with('info', 'This bleep is not available.');
        }

        // Increment visits only if bleep is still active
        $share->increment('visits');

        return redirect()->route('post', $bleep->id);
    }

    /**
     * remove share incase if the user wants to unshare.
     */
    public function destroy(Share $share)
    {
        $share->delete();

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'shares_count' => Share::where('bleep_id', $share->bleep_id)->count(),
            ]);
        }

        return redirect()->back()->with('success', 'Unshared successfully.');
    }
}
