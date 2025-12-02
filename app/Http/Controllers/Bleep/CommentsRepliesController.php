<?php

namespace App\Http\Controllers\Bleep;

use App\Http\Controllers\Controller;

use App\Models\Comments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        $replies = $comment->replies()
            ->with(['user', 'likes'])
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'html'      => view('components.subcomponents.comments.replies', [
                'replies' => $replies,
                'parent'  => $comment,
                'depth'   => $depth,
            ])->render(),
            'has_more'  => $replies->hasMorePages(),
            'next_page' => $replies->currentPage() + 1,
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
            'media'        => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,mp4,quicktime,mp3,wav', 'max:20480'],
        ]);

        if (!$request->filled('message') && !$request->hasFile('media')) {
            return response()->json([
                'message' => 'Write something or attach media.',
            ], 422);
        }

        $mediaPath = $request->hasFile('media')
            ? $request->file('media')->store('comments/media', 'public')
            : null;

        $reply = Comments::create([
            'user_id'      => Auth::id(),
            'bleep_id'     => $comment->bleep_id,
            'parent_id'    => $comment->id,
            'message'      => $data['message'],
            'media_path'   => $mediaPath,
            'is_anonymous' => $request->boolean('is_anonymous'),
        ])->load(['user', 'likes']);

        $depth = $comment->depth() + 1;

        return response()->json([
            'html'          => view('components.subcomponents.comments.commentcard', [
                'comment' => $reply,
                'bleep'   => $comment->bleep,
                'depth'   => $depth,
            ])->render(),
            'replies_count' => $comment->replies()->count(),
        ]);
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
}
