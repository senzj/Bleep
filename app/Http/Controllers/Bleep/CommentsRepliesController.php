<?php

namespace App\Http\Controllers\Bleep;

use App\Http\Controllers\Controller;

use App\Models\Comments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\MediaUploadService;

class CommentsRepliesController extends Controller
{
    /**
     * Display a listing of the resource with pagination.
     */
    public function index(Request $request, Comments $comment)
    {
        $perPage = 5;
        $page    = max(1, (int) $request->integer('page', 1));
        $depth   = max(1, (int) $request->integer('depth', $comment->depth() + 1));

        $viewerSeed = Auth::check() ? Auth::id() : $request->session()->getId();

        $replies = $comment->replies()
            ->with(['user', 'likes'])
            ->paginate($perPage, ['*'], 'page', $page);

        // Transform replies to JSON format
        $transformedReplies = $replies->map(fn ($reply) => $this->transformComment($reply, $viewerSeed))->values();

        return response()->json([
            'replies'    => $transformedReplies,
            'has_more'   => $replies->hasMorePages(),
            'next_page'  => $replies->currentPage() + 1,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Comments $comment)
    {
        $data = $request->validate([
            'message'      => ['nullable', 'string', 'max:500'],
            'is_anonymous' => ['boolean'],
            'media'        => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,gif,webp,mp4,mov,webm,mp3,wav,ogg,m4a',
                'max:50480',
            ],
        ]);

        if (!$request->filled('message') && !$request->hasFile('media')) {
            return response()->json([
                'message' => 'Write something or attach media.',
            ], 422);
        }

        $reply = Comments::create([
            'user_id'      => Auth::id(),
            'bleep_id'     => $comment->bleep_id,
            'parent_id'    => $comment->id,
            'message'      => $data['message'],
            'is_anonymous' => $request->boolean('is_anonymous'),
        ]);

        if ($request->hasFile('media')) {
            try {
                $mediaMeta = MediaUploadService::saveCommentMedia(
                    $request->file('media'),
                    $reply->id
                );

                $reply->update([
                    'media_path' => $mediaMeta['path'],
                    'media_type' => $mediaMeta['type'],
                    'media_mime' => $mediaMeta['mime'],
                ]);
            } catch (\Exception $e) {
                $reply->delete();
                return response()->json([
                    'message' => 'Failed to upload media: ' . $e->getMessage(),
                ], 422);
            }
        }

        $reply->load(['user', 'likes']);

        $viewerSeed = Auth::check() ? Auth::id() : $request->session()->getId();
        $transformed = $this->transformComment($reply, $viewerSeed);

        return response()->json($transformed);
    }

    /**
     * Display the specified resource.
     */
    public function show()
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit()
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update()
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy()
    {
        //
    }

    /**
     * Transform a comment to API response format
     */
    protected function transformComment(Comments $comment, $viewerSeed = null): array
    {
        $user = $comment->user;
        $viewerSeed = $viewerSeed ?? (Auth::check() ? Auth::id() : request()->session()->getId());
        $bleep = $comment->bleep;

        $displayName = $comment->is_anonymous
            ? ($bleep ? $bleep->anonymousDisplayNameFor($viewerSeed) : 'Anonymous')
            : (optional($user)->dname ?? 'Unknown');

        return [
            'id' => $comment->id,
            'parent_id' => $comment->parent_id,
            'message' => $comment->message,
            'created_at' => optional($comment->created_at)->toDateTimeString(),
            'created_at_iso' => optional($comment->created_at)->toIso8601String(),
            'diffTimestamp' => optional($comment->created_at)->diffForHumans(),
            'is_anonymous' => (bool) $comment->is_anonymous,
            'display_name' => $displayName,
            'canEdit' => Auth::check() && Auth::id() === $comment->user_id,
            'canDelete' => Auth::check() && Auth::id() === $comment->user_id,
            'media' => $comment->media_path,
            'likes_count' => $comment->likes()->count(),
            'liked' => Auth::check() ? $comment->likes()->where('user_id', Auth::id())->exists() : false,
            'replies_count' => $comment->replies()->count(),
            'user' => [
                'id' => $comment->is_anonymous ? null : optional($user)->id,
                'username' => $comment->is_anonymous ? null : optional($user)->username,
                'profile_picture' => $comment->is_anonymous ? null : optional($user)->profile_picture_url,
                'dname' => $comment->is_anonymous ? null : optional($user)->dname,
                'email' => $comment->is_anonymous ? null : optional($user)->email,
                'timezone' => $comment->is_anonymous ? null : optional($user)->timezone,
                'role' => $comment->is_anonymous ? null : optional($user)->role,
                'is_verified' => $comment->is_anonymous ? null : (bool) optional($user)->is_verified,
            ],
        ];
    }
}
