<?php

namespace App\Http\Controllers\Bleep;

use App\Http\Controllers\Controller;

use App\Models\Bleep;
use App\Models\Comments;

use Illuminate\Http\Request;
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

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'comment' => $transformed,
            ]);
        }

        return redirect()
            ->route('post', $bleep->id)
            ->with('success', 'Your comment was posted.')
            ->with('new_comment', $transformed);
    }

    /**
     * Update the specified comment
     */
    public function update(Request $request, Comments $comment)
    {
        $this->authorize('update', $comment);

        $request->validate([
            'message' => 'required|string|max:255',
            'is_anonymous' => 'boolean',
        ]);

        $comment->update([
            'message' => $request->message,
            'is_anonymous' => $request->boolean('is_anonymous'),
        ]);

        $comment->load('user');
        $bleep = $comment->bleep;
        $viewerSeed = Auth::check() ? Auth::id() : $request->session()->getId();
        $transformed = $this->transformComment($comment, $bleep, $viewerSeed);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'comment' => $transformed,
                'message' => 'Comment updated successfully.'
            ]);
        }

        return redirect()
            ->route('post', $comment->bleep_id)
            ->with('success', 'Comment updated successfully.');
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

        $bleepId = $comment->bleep_id;
        $comment->delete();

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()
            ->route('post', $bleepId)
            ->with('success', 'Comment deleted successfully.');
    }

    /**
     * Report a comment
     */
    public function report(Request $request, Comments $comment)
    {
        $request->validate([
            'reason' => 'required|in:spam,offensive,harassment,misinformation',
            'description' => 'nullable|string|max:500',
        ]);

        // TODO: Create CommentReport model and save report
        // CommentReport::create([
        //     'comment_id' => $comment->id,
        //     'reported_by' => Auth::id(),
        //     'reason' => $request->reason,
        //     'description' => $request->description,
        // ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Comment reported successfully.',
            ]);
        }

        return redirect()->back()->with('success', 'Comment reported successfully.');
    }

    /**
     * Get comments as rendered HTML with pagination
     */
    public function commentsHtml(Request $request, Bleep $bleep)
    {
        $page = (int) $request->get('page', 1);
        $perPage = 10;

        // Paginate comments
        $comments = $bleep->comments()
            ->with('user')
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);

        // Group by date with timezone
        $groups = $comments->getCollection()->groupBy(function($c) {
            $tz = $c->user?->timezone ?? config('app.timezone', 'UTC');
            return $c->created_at->copy()->setTimezone($tz)->format('Y-m-d') . '|' . $tz;
        });

        // Build HTML
        $html = '';

        if ($groups->isEmpty()) {
            $html = '
                <div class="flex flex-col items-center justify-center py-10 text-base-content/60">
                    <i data-lucide="message-circle-off" class="w-8 h-8 mb-3"></i>
                    <p class="text-sm font-semibold">No comments yet</p>
                    <p class="text-xs">Be the first to share your thoughts.</p>
                </div>
            ';
        } else {
            foreach ($groups as $key => $group) {
                [$date, $tz] = explode('|', $key);
                $dt = \Carbon\Carbon::createFromFormat('Y-m-d', $date, $tz);
                $showYear = $dt->year !== now()->year;
                $label = $dt->format('F j') . ($showYear ? ', ' . $dt->year : '');

                // Date header with unique data attribute for deduplication
                $html .= '<div class="text-sm text-base-content/60 font-medium mb-2 comment-date-header" data-date="' . $date . '">' . $label . '</div>';

                // Comments in this group
                $html .= '<div class="space-y-3">';
                foreach ($group as $comment) {
                    $html .= view('components.subcomponents.comments.commentcard', [
                        'comment' => $comment,
                        'bleep' => $bleep
                    ])->render();
                }
                $html .= '</div>';
            }
        }

        return response()->json([
            'html' => $html,
            'has_more' => $comments->hasMorePages(),
            'next_page' => $comments->currentPage() + 1,
            'current_page' => $comments->currentPage(),
            'total' => $comments->total(),
        ]);
    }

    protected function transformComment(Comments $comment, ?Bleep $bleep = null, $viewerSeed = null): array
    {
        $user = $comment->user;
        $viewerSeed = $viewerSeed ?? (Auth::check() ? Auth::id() : request()->session()->getId());
        $bleepForName = $bleep ?? $comment->bleep;

        $displayName = $comment->is_anonymous
            ? ($bleepForName ? $bleepForName->anonymousDisplayNameFor($viewerSeed) : 'Anonymous')
            : (optional($user)->dname ?? 'Unknown');

        return [
            'id' => $comment->id,
            'message' => $comment->message,
            'created_at' => optional($comment->created_at)->toDateTimeString(),
            'created_at_iso' => optional($comment->created_at)->toIso8601String(),
            'diffTimestamp' => optional($comment->created_at)->diffForHumans(),
            'is_anonymous' => (bool) $comment->is_anonymous,
            'display_name' => $displayName,
            'canEdit' => Auth::check() && Auth::id() === $comment->user_id,
            'canDelete' => Auth::check() && Auth::id() === $comment->user_id,
            'user' => [
                'username' => $comment->is_anonymous ? null : optional($user)->username,
                'dname' => $comment->is_anonymous ? null : optional($user)->dname,
                'email' => $comment->is_anonymous ? null : optional($user)->email,
                'timezone' => $comment->is_anonymous ? null : optional($user)->timezone,
            ],
        ];
    }
}
