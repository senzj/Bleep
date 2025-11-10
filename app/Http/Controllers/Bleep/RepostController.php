<?php

namespace App\Http\Controllers\Bleep;

use App\Http\Controllers\Controller;
use App\Models\Bleep;
use App\Models\Repost;
use Illuminate\Http\Request;

class RepostController extends Controller
{
    public function store(Request $request, Bleep $bleep)
    {
        $user = $request->user();

        // Check if already reposted
        $existing = Repost::where('bleep_id', $bleep->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Already reposted',
                'reposted' => true,
                'repostCount' => Repost::where('bleep_id', $bleep->id)->count()
            ], 200);
        }

        Repost::create([
            'bleep_id' => $bleep->id,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'reposted' => true,
            'repostCount' => Repost::where('bleep_id', $bleep->id)->count()
        ]);
    }

    public function destroy(Request $request, Bleep $bleep)
    {
        $user = $request->user();

        $repost = Repost::where('bleep_id', $bleep->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$repost) {
            return response()->json([
                'message' => 'Repost not found',
                'reposted' => false,
                'repostCount' => Repost::where('bleep_id', $bleep->id)->count()
            ], 404);
        }

        $repost->delete();

        return response()->json([
            'reposted' => false,
            'repostCount' => Repost::where('bleep_id', $bleep->id)->count()
        ]);
    }
}
