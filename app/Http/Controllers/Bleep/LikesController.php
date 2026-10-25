<?php

namespace App\Http\Controllers\Bleep;

use App\Http\Controllers\Controller;
use App\Models\Bleep;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

class LikesController extends Controller
{
    use AuthorizesRequests;

    public function toggle(Bleep $bleep)
    {
        if (! Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = Auth::user();

        abort_unless($bleep->isVisibleTo($user), 404);
        $this->authorize('like', $bleep);

        $liked = $bleep->likes()->where('user_id', $user->id)->exists();

        if ($liked) {
            $bleep->likes()->where('user_id', $user->id)->delete();
        } else {
            $bleep->likes()->create(['user_id' => $user->id]);
        }

        return response()->json([
            'success' => true,
            'liked' => ! $liked,
            'count' => $bleep->likes()->count(),
        ]);
    }

    public function count(Bleep $bleep)
    {
        abort_unless($bleep->isVisibleTo(Auth::user()), 404);

        return response()->json([
            'count' => $bleep->likes()->count(),
        ]);
    }
}
