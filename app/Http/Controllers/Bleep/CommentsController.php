<?php

namespace App\Http\Controllers\Bleep;

use App\Http\Controllers\Controller;
use App\Models\Bleep;
use App\Models\Comments;
use App\Services\MediaUploadService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommentsController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display comments for a bleep
     */
    public function index(Bleep $bleep)
    {
        abort_unless($bleep->isVisibleTo(Auth::user()), 404);

        $viewerSeed = Auth::check() ? Auth::id() : request()->session()->getId();

        $commentsQuery = $bleep->comments()
            ->whereNull('parent_id')  // Only fetch top-level comments
            ->with('user')
            ->withCount(['likes', 'replies'])
            ->latest()
            ->take(20);

        if (Auth::check()) {
            $commentsQuery->withExists([
                'likes as liked' => fn ($query) => $query->where('user_id', Auth::id()),
            ]);
        }

        $comments = $commentsQuery
            ->get()
            ->map(fn ($comment) => $this->transformComment($comment, $bleep, $viewerSeed));

        return response()->json(['comments' => $comments]);
    }

    /**
     * Store a newly created comment
     */
    public function store(Request $request, Bleep $bleep)
    {
        abort_unless($bleep->isVisibleTo(Auth::user()), 404);

        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:500'],
            'is_anonymous' => ['boolean'],
            'is_nsfw' => ['boolean'],
            'media' => [
                'nullable',
                'file',
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,audio/mpeg,audio/wav,audio/mp3',
                'max:50480',
            ],
        ], [
            'media.mimetypes' => 'Only image, video, or audio files are allowed for comments.',
        ]);

        if (! $request->filled('message') && ! $request->hasFile('media')) {
            return response()->json([
                'success' => false,
                'message' => 'Write something or attach media.',
            ], 422);
        }

        // Create comment first to get ID for media storage path
        $comment = $bleep->comments()->create([
            'user_id' => Auth::id(),
            'message' => $validated['message'],
            'is_anonymous' => $request->boolean('is_anonymous'),
            'is_nsfw' => $request->boolean('is_nsfw'),
        ]);

        // Handle media upload with comment ID
        if ($request->hasFile('media')) {
            try {
                $mediaMeta = MediaUploadService::saveCommentMedia(
                    $request->file('media'),
                    $comment->id
                );

                $comment->update([
                    'media_path' => $mediaMeta['path'],
                    'media_type' => $mediaMeta['type'],
                    'media_mime' => $mediaMeta['mime'],
                ]);
            } catch (\Exception $e) {
                // If media upload fails, delete the comment
                $comment->delete();

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload media: '.$e->getMessage(),
                ], 422);
            }
        }

        $comment->load(['user']);
        $comment->loadCount(['likes', 'replies']);
        $comment->setAttribute('liked', false);
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
            'message' => 'required|string|max:500',
            'is_anonymous' => 'boolean',
            'is_nsfw' => 'boolean',
            'media' => [
                'nullable',
                'file',
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/quicktime,audio/mpeg,audio/wav,audio/mp3,audio/ogg,audio/x-m4a',
                'max:50480',
            ],
            'remove_media' => 'boolean',
        ], [
            'media.mimetypes' => 'Only image, video, or audio files are allowed for comments.',
        ]);

        // Handle media removal
        if ($request->boolean('remove_media') && $comment->media_path) {
            MediaUploadService::deleteCommentMedia($comment->media_path);
            $comment->update([
                'media_path' => null,
                'media_type' => null,
                'media_mime' => null,
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
                    'media_path' => $mediaMeta['path'],
                    'media_type' => $mediaMeta['type'],
                    'media_mime' => $mediaMeta['mime'],
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload media: '.$e->getMessage(),
                ], 422);
            }
        }

        // Update message and anonymity
        $comment->update([
            'message' => $request->message,
            'is_anonymous' => $request->boolean('is_anonymous'),
            'is_nsfw' => $request->boolean('is_nsfw'),
        ]);

        $comment->load('user');
        $bleep = $comment->bleep;
        $viewerSeed = Auth::check() ? Auth::id() : $request->session()->getId();

        // Get updated comment data
        $commentData = $this->transformComment($comment, $bleep, $viewerSeed);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Comment updated successfully.',
                'display_name' => $commentData['display_name'],
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
        abort_unless($bleep->isVisibleTo(Auth::user()), 404);

        return response()->json([
            'count' => $bleep->comments()->count(),
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
        $validated = $request->validate([
            'reason' => 'required|in:spam,offensive,harassment,misinformation',
            'description' => 'nullable|string|max:500',
        ]);

        abort_unless($comment->bleep?->isVisibleTo(Auth::user()), 404);

        $category = match ($validated['reason']) {
            'spam' => 'spam',
            'offensive', 'harassment' => 'harassment',
            'misinformation' => 'other',
            default => 'other',
        };

        $comment->reports()->create([
            'reporter_id' => Auth::id(),
            'reason' => $validated['description'] ?? $validated['reason'],
            'category' => $category,
            'status' => 'pending',
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Comment reported successfully.',
            ]);
        }

        return redirect()->back()->with('success', 'Comment reported successfully.');
    }

    protected function transformComment(Comments $comment, ?Bleep $bleep = null, $viewerSeed = null): array
    {
        $user = $comment->user;
        $viewerSeed = $viewerSeed ?? (Auth::check() ? Auth::id() : request()->session()->getId());
        $bleepForName = $bleep ?? $comment->bleep;

        $displayName = $comment->is_anonymous
            ? ($bleepForName ? $bleepForName->anonymousDisplayNameFor($viewerSeed) : 'Anonymous')
            : (optional($user)->dname ?? 'Unknown');

        $likesCount = $comment->getAttribute('likes_count');
        $repliesCount = $comment->getAttribute('replies_count');
        $liked = $comment->getAttribute('liked');

        return [
            'id' => $comment->id,
            'parent_id' => $comment->parent_id,
            'message' => $comment->message,
            'created_at' => optional($comment->created_at)->toDateTimeString(),
            'created_at_iso' => optional($comment->created_at)->toIso8601String(),
            'diffTimestamp' => optional($comment->created_at)->diffForHumans(),
            'is_anonymous' => (bool) $comment->is_anonymous,
            'is_nsfw' => (bool) $comment->is_nsfw,
            'display_name' => $displayName,
            'canEdit' => Auth::check() && Auth::id() === $comment->user_id,
            'canDelete' => Auth::check() && Auth::id() === $comment->user_id,
            'media' => $comment->media_path,
            'likes_count' => $likesCount !== null ? (int) $likesCount : $comment->likes()->count(),
            'liked' => $liked !== null ? (bool) $liked : (Auth::check() && $comment->likes()->where('user_id', Auth::id())->exists()),
            'replies_count' => $repliesCount !== null ? (int) $repliesCount : $comment->replies()->count(),
            'isOP' => Auth::check() && $bleepForName && $comment->user_id === $bleepForName->user_id,
            'user' => [
                'id' => $comment->is_anonymous ? null : optional($user)->id,
                'username' => $comment->is_anonymous ? null : optional($user)->username,
                'profile_picture' => $comment->is_anonymous ? null : optional($user)->profile_picture_url,
                'dname' => $comment->is_anonymous ? null : optional($user)->dname,
                'role' => $comment->is_anonymous ? null : optional($user)->role,
                'is_verified' => $comment->is_anonymous ? null : (bool) optional($user)->is_verified,
            ],
        ];
    }
}
