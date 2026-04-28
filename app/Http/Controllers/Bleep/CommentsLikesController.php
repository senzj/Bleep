<?php

namespace App\Http\Controllers\Bleep;

use App\Http\Controllers\Controller;

use App\Models\Comments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommentsLikesController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Comments $comment)
    {
        // redirect to login if user is not authenticated
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $comment->likes()->firstOrCreate(
            ['user_id' => Auth::id()],
            ['is_anonymous' => $request->boolean('is_anonymous')]
        );

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
}
