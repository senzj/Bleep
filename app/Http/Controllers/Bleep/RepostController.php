<?php

namespace App\Http\Controllers\Bleep;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Models\Bleep;
use App\Models\Repost;
use App\Models\User;
use App\Services\Notifications\NotificationData;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\Request;

class RepostController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function store(Request $request, Bleep $bleep)
    {
        $user = $request->user();

        abort_unless($bleep->isVisibleTo($user), 404);

        // Check if already reposted
        $existing = Repost::where('bleep_id', $bleep->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Already reposted',
                'reposted' => true,
                'repostCount' => Repost::where('bleep_id', $bleep->id)->count(),
            ], 200);
        }

        Repost::create([
            'bleep_id' => $bleep->id,
            'user_id' => $user->id,
        ]);

        $this->notifyBleepAuthor($bleep, $user);

        return response()->json([
            'reposted' => true,
            'repostCount' => Repost::where('bleep_id', $bleep->id)->count(),
        ]);
    }

    private function notifyBleepAuthor(Bleep $bleep, User $user): void
    {
        $bleep->loadMissing('user');

        if (! $bleep->user) {
            return;
        }

        $this->notifications->notify($bleep->user, NotificationType::BleepReposted, [
            'actor' => $user,
            'subject' => $bleep,
            'url' => route('post', $bleep->id),
            'group_key' => 'repost:bleep:'.$bleep->id,
            'data' => NotificationData::bleep($bleep),
        ]);
    }

    public function destroy(Request $request, Bleep $bleep)
    {
        $user = $request->user();

        abort_unless($bleep->isVisibleTo($user), 404);

        $repost = Repost::where('bleep_id', $bleep->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $repost) {
            return response()->json([
                'message' => 'Repost not found',
                'reposted' => false,
                'repostCount' => Repost::where('bleep_id', $bleep->id)->count(),
            ], 404);
        }

        $repost->delete();

        return response()->json([
            'reposted' => false,
            'repostCount' => Repost::where('bleep_id', $bleep->id)->count(),
        ]);
    }
}
