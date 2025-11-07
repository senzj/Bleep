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
            ['token' => Str::uuid()->toString()]
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
        $share = Share::where('token', $token)->firstOrFail();
        $share->increment('visits');

        return redirect()->route('post', $share->bleep);
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

