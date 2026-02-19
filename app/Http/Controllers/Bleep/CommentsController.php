<?php

namespace App\Http\Controllers\Bleep;

use App\Http\Controllers\Controller;

use App\Models\Bleep;
use App\Models\Comments;
use App\Services\MediaUploadService;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
        $validated = $request->validate([
            'message'      => ['nullable', 'string', 'max:500'],
            'is_anonymous' => ['boolean'],
            'media'        => ['nullable', 'file', 'image', 'max:20480'],
        ], [
            'media.image' => 'Only image files are allowed for comments.',
        ]);

        if (!$request->filled('message') && !$request->hasFile('media')) {
            return response()->json([
                'success' => false,
                'message' => 'Write something or attach media.',
            ], 422);
        }

        // Create comment first to get ID for media storage path
        $comment = $bleep->comments()->create([
            'user_id'      => Auth::id(),
            'message'      => $validated['message'],
            'is_anonymous' => $request->boolean('is_anonymous'),
        ]);

        // Handle media upload with comment ID
        if ($request->hasFile('media')) {
            try {
                $mediaMeta = MediaUploadService::saveCommentMedia(
                    $request->file('media'),
                    $comment->id
                );

                $comment->update([
                    'media_path'   => $mediaMeta['path'],
                    'media_type'   => $mediaMeta['type'],
                    'media_mime'   => $mediaMeta['mime'],
                ]);
            } catch (\Exception $e) {
                // If media upload fails, delete the comment
                $comment->delete();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload media: ' . $e->getMessage(),
                ], 422);
            }
        }

        $comment->load(['user', 'likes']);
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
        Log::info('Request Data: ', $request->all());

        $this->authorize('update', $comment);

        $request->validate([
            'message'      => 'required|string|max:500',
            'is_anonymous' => 'boolean',
            'media'        => ['nullable', 'file', 'image', 'max:20480'],
            'remove_media' => 'boolean',
        ], [
            'media.image' => 'Only image files are allowed for comments.',
        ]);

        // Handle media removal
        if ($request->boolean('remove_media') && $comment->media_path) {
            MediaUploadService::deleteCommentMedia($comment->media_path);
            $comment->update([
                'media_path'   => null,
                'media_type'   => null,
                'media_mime'   => null,
            ]);
        }

        // Handle new media upload (replaces existing)
        if ($request->hasFile('media')) {
            // Delete old media if exists
            if ($comment->media_path) {
                MediaUploadService::deleteCommentMedia($comment->media_path);
            }

            try {
                $mediaMeta = MediaUploadService::saveCommentMedia(
                    $request->file('media'),
                    $comment->id
                );

                $comment->update([
                    'media_path'   => $mediaMeta['path'],
                    'media_type'   => $mediaMeta['type'],
                    'media_mime'   => $mediaMeta['mime'],
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload media: ' . $e->getMessage(),
                ], 422);
            }
        }

        // Update message and anonymity
        $comment->update([
            'message'      => $request->message,
            'is_anonymous' => $request->boolean('is_anonymous'),
        ]);

        $comment->load('user');
        $bleep = $comment->bleep;
        $viewerSeed = Auth::check() ? Auth::id() : $request->session()->getId();

        // Get updated comment data
        $commentData = $this->transformComment($comment, $bleep, $viewerSeed);

        // Build media HTML for frontend
        $mediaHtml = null;
        if ($comment->media_path) {
            $mediaHtml = view('components.subcomponents.comments.commentmedia', [
                'path' => $comment->media_path,
                'type' => $comment->media_type,
                'commentId' => $comment->id,
            ])->render();
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Comment updated successfully.',
                'display_name' => $commentData['display_name'],
                'media_html' => $mediaHtml,
                'media_path' => $comment->media_path,
                'media_type' => $comment->media_type,
                'updated_at' => $comment->updated_at->toIso8601String(),
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
        $perPage = (int) $request->integer('per_page', 10);

        $comments = Comments::with(['user', 'likes'])
            ->withCount('replies')
            ->where('bleep_id', $bleep->id)
            ->whereNull('parent_id')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $html = $comments->isEmpty()
            ? '<p class="text-center text-sm text-base-content/60 py-6">Be the first to comment.</p>'
            : $comments->map(fn ($comment) => view(
                    'components.subcomponents.comments.commentcard',
                    ['comment' => $comment, 'bleep' => $bleep, 'depth' => 0]
                )->render()
            )->implode('');

        return response()->json([
            'html'         => $html,
            'current_page' => $comments->currentPage(),
            'has_more'     => $comments->hasMorePages(),
            'total'        => $comments->total(),
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
