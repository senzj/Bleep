<?php

namespace App\Http\Controllers\Bleep;

use App\Models\Bleep;
use App\Models\Share;
use App\Models\Repost;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class ShareController extends Controller
{
    /**
     * create if user shares a bleep post
     */
    public function store(Request $request, Bleep $bleep)
    {
        $sessionKey = $request->session()->getId();
        $day = now()->toDateString();

        $share = Share::firstOrCreate(
            [
                'bleep_id'   => $bleep->id,
                'shared_on'  => $day,
                'user_id'    => Auth::id(),
                'session_key'=> Auth::check() ? null : $sessionKey,
            ],
            ['token' => Share::generateToken()]
        );

        $share->touch();

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

    public function redirect(string $token)
    {
        // Find share, if not found redirect to home
        $share = Share::with('bleep')->where('token', $token)->first();

        if (!$share) {
            return redirect('/')->with('info', 'This share link is no longer available.');
        }

        // Get bleep (including soft-deleted)
        $bleep = $share->bleep;

        // If bleep doesn't exist at all (force deleted), redirect to home
        if (!$bleep) {
            return redirect('/')->with('info', 'This bleep is no longer available.');
        }

        // If bleep is soft-deleted, show deleted page
        if ($bleep->trashed()) {
            return redirect()->route('post', $bleep->id);
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

