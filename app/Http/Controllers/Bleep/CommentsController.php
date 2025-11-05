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
            ->paginate(50)
            ->map(function($comment) {
                return [
                    'id' => $comment->id,
                    'message' => $comment->message,
                    'created_at' => $comment->created_at->toDateTimeString(),
                    'created_at_iso' => $comment->created_at->toIso8601String(),
                    'diffTimestamp' => $comment->created_at->diffForHumans(),
                    'user' => [
                        'username' => $comment->user->username,
                        'dname' => $comment->user->dname,
                        'email' => $comment->user->email,
                        'timezone' => $comment->user->timezone,
                    ]
                ];
            });

        return response()->json(['comments' => $comments]);
    }

    /**
     * Store a newly created comment
     */
    public function store(Request $request, Bleep $bleep)
    {
        $request->validate([
            'message' => 'required|string|max:500'
        ]);

        $comment = $bleep->comments()->create([
            'user_id' => Auth::id(),
            'message' => $request->message,
            'is_anonymous' => false
        ]);

        return response()->json([
            'success' => true,
            'comment' => $comment->load('user')
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
}
