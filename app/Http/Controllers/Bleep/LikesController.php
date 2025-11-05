<?php

namespace App\Http\Controllers\Bleep;

use App\Models\Bleep;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class LikesController extends Controller
{
    use AuthorizesRequests;

    public function toggle(Bleep $bleep)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = Auth::user();

        $this->authorize('like', $bleep);

        if ($bleep->likes()->where('user_id', $user->id)->exists()) {
            $bleep->likes()->where('user_id', $user->id)->delete();
        } else {
            $bleep->likes()->create(['user_id' => $user->id]);
        }

        return response()->json(['success' => true]);
    }

    public function count(Bleep $bleep)
    {
        return response()->json([
            'count' => $bleep->likes()->count()
        ]);
    }
}
