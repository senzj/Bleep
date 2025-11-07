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
        $viewerSeed = Auth::check() ? Auth::id() : request()->session()->getId();

        $comments = $bleep->comments()
            ->with('user')
            ->latest()
            ->take(50)
            ->get()
            ->map(fn ($comment) => $this->transformComment($comment, $bleep, $viewerSeed));

        return response()->json(['comments' => $comments]);
    }

        /**
         * Store a newly created comment
         */
        public function store(Request $request, Bleep $bleep)
        {
            $request->validate(['message' => 'required|string|max:255']);

            $comment = $bleep->comments()->create([
                'user_id' => Auth::id(),
                'message' => $request->message,
                'is_anonymous' => $request->boolean('is_anonymous'),
            ])->load('user');

            $viewerSeed = Auth::check() ? Auth::id() : $request->session()->getId();
            $transformed = $this->transformComment($comment, $bleep, $viewerSeed);

            // JSON response for AJAX/fetch clients
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                'comment' => $transformed,
            ]);
        }

        // Non-AJAX: redirect back to the post page with optional flash data
        return redirect()
            ->route('post', $bleep->id)
            ->with('success', 'Your comment was posted.')
            ->with('new_comment', $transformed);
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

    protected function transformComment(Comments $comment, ?Bleep $bleep = null, $viewerSeed = null): array
    {
        $user = $comment->user;
        $viewerSeed = $viewerSeed ?? (Auth::check() ? Auth::id() : request()->session()->getId());

        // prefer the parent bleep if provided to compute the anonymous name
        $bleepForName = $bleep ?? $comment->bleep;

        $displayName = $comment->is_anonymous
            ? ($bleepForName ? $bleepForName->anonymousDisplayNameFor($viewerSeed) : 'anonymous')
            : (optional($user)->dname ?? 'Unknown');

        return [
            'id' => $comment->id,
            'message' => $comment->message,
            'created_at' => optional($comment->created_at)->toDateTimeString(),
            'created_at_iso' => optional($comment->created_at)->toIso8601String(),
            'diffTimestamp' => optional($comment->created_at)->diffForHumans(),
            'is_anonymous' => (bool) $comment->is_anonymous,
            'display_name' => $displayName,
            'user' => [
                'username' => $comment->is_anonymous ? null : optional($user)->username,
                'dname' => $comment->is_anonymous ? null : optional($user)->dname,
                'email' => $comment->is_anonymous ? null : optional($user)->email,
                'timezone' => $comment->is_anonymous ? null : optional($user)->timezone,
            ],
        ];
    }
}
