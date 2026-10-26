<?php

namespace App\Http\Controllers\Bleep;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Models\Comments;
use App\Services\Notifications\NotificationData;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommentsLikesController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Comments $comment)
    {
        // redirect to login if user is not authenticated
        if (! Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $like = $comment->likes()->firstOrCreate(
            ['user_id' => Auth::id()],
            ['is_anonymous' => $request->boolean('is_anonymous')]
        );

        if ($like->wasRecentlyCreated) {
            $this->notifyCommentAuthor($comment);
        }

        return response()->json([
            'liked' => true,
            'likes_count' => $comment->likesCount(),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Comments $comment)
    {
        $comment->likes()
            ->where('user_id', Auth::id())
            ->delete();

        return response()->json([
            'liked' => false,
            'likes_count' => $comment->likesCount(),
        ]);
    }

    private function notifyCommentAuthor(Comments $comment): void
    {
        $comment->loadMissing('user');

        if (! $comment->user) {
            return;
        }

        $this->notifications->notify($comment->user, NotificationType::CommentLiked, [
            'actor' => Auth::user(),
            'subject' => $comment,
            'url' => route('post', $comment->bleep_id),
            'group_key' => 'like:comment:'.$comment->id,
            'data' => NotificationData::comment($comment),
        ]);
    }
}
