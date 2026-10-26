<?php

namespace App\Http\Controllers\Bleep;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Models\Bleep;
use App\Models\Repost;
use App\Models\User;
use App\Services\Notifications\NotificationData;
use App\Services\Notifications\NotificationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

class LikesController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private NotificationService $notifications) {}

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

            $this->notifyBleepAuthor($bleep, $user);
            $this->notifyReposters($bleep, $user);
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

    private function notifyBleepAuthor(Bleep $bleep, User $user): void
    {
        $bleep->loadMissing('user');

        if (! $bleep->user) {
            return;
        }

        $this->notifications->notify($bleep->user, NotificationType::BleepLiked, [
            'actor' => $user,
            'subject' => $bleep,
            'url' => route('post', $bleep->id),
            'group_key' => 'like:bleep:'.$bleep->id,
            'data' => NotificationData::bleep($bleep),
        ]);
    }

    /**
     * People who reposted the bleep may also want to know it was liked.
     * The type is opt-in (off by default) so this stays quiet for most users.
     */
    private function notifyReposters(Bleep $bleep, User $user): void
    {
        $reposters = Repost::query()
            ->where('bleep_id', $bleep->id)
            ->where('user_id', '!=', $user->id)
            ->where('user_id', '!=', $bleep->user_id)
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();

        foreach ($reposters as $reposter) {
            $this->notifications->notify($reposter, NotificationType::RepostLiked, [
                'actor' => $user,
                'subject' => $bleep,
                'url' => route('post', $bleep->id),
                'group_key' => 'like:repost:'.$bleep->id.':'.$reposter->id,
                'data' => NotificationData::bleep($bleep),
            ]);
        }
    }
}
