<?php

namespace App\Http\Controllers\Bleep;

use App\Models\Bleep;
use App\Models\Comments;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

class CommentsController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display comments for a bleep
     */
    public function index(Bleep $bleep)
    {
        $comments = $bleep->comments()
            ->with('user')
            ->latest()
            ->take(50)
            ->get()
            ->map(fn ($comment) => $this->transformComment($comment));

        return response()->json(['comments' => $comments]);
    }

    /**
     * Store a newly created comment
     */
    public function store(Request $request, Bleep $bleep)
    {
        $request->validate([
            'message' => 'required|string|max:500',
            'is_anonymous' => 'sometimes|boolean',
        ]);

        $comment = $bleep->comments()->create([
            'user_id' => Auth::id(),
            'message' => $request->message,
            'is_anonymous' => $request->boolean('is_anonymous'),
        ])->load('user');

        return response()->json([
            'success' => true,
            'comment' => $this->transformComment($comment),
        ]);
    }

    /**
     * Get comment count
     */
    public function count(Bleep $bleep)
    {
        return response()->json([
            'count' => $bleep->comments()->count()
        ]);
    }

    /**
     * Remove the specified comment
     */
    public function destroy(Comments $comment)
    {
        $this->authorize('delete', $comment);

        $comment->delete();

        return response()->json(['success' => true]);
    }

    protected function transformComment(Comments $comment): array
    {
        $user = $comment->user;

        return [
            'id' => $comment->id,
            'message' => $comment->message,
            'created_at' => optional($comment->created_at)->toDateTimeString(),
            'created_at_iso' => optional($comment->created_at)->toIso8601String(),
            'diffTimestamp' => optional($comment->created_at)->diffForHumans(),
            'is_anonymous' => (bool) $comment->is_anonymous,
            'user' => [
                'username' => $comment->is_anonymous ? null : optional($user)->username,
                'dname' => $comment->is_anonymous ? null : optional($user)->dname,
                'email' => $comment->is_anonymous ? null : optional($user)->email,
                'timezone' => $comment->is_anonymous ? null : optional($user)->timezone,
            ],
        ];
    }
}
