<?php

namespace App\Http\Controllers\Bleep;

use App\Http\Controllers\Controller;

use App\Models\Comments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommentsLikesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Comments $comment)
    {
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
