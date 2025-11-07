<?php

namespace App\Http\Controllers\Bleep;

use App\Models\Bleep;
use App\Models\Repost;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class RepostController extends Controller
{

    public function store(Bleep $bleep)
    {
        Repost::firstOrCreate([
            'bleep_id' => $bleep->id,
            'user_id'  => Auth::id(),
        ]);

        return response()->json($this->totals($bleep));
    }

    public function destroy(Bleep $bleep)
    {
        Repost::where('bleep_id', $bleep->id)
            ->where('user_id', Auth::id())
            ->delete();

        return response()->json($this->totals($bleep));
    }

    protected function totals(Bleep $bleep): array
    {
        $shares = \App\Models\Share::where('bleep_id', $bleep->id)->count();
        $reposts = Repost::where('bleep_id', $bleep->id)->count();

        return [
            'success' => true,
            'shares_count' => $shares,
            'reposts_count' => $reposts,
            'total_shares' => $shares + $reposts,
        ];
    }
}
