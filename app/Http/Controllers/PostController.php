<?php

namespace App\Http\Controllers;

use App\Models\Bleep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PostController extends Controller
{
    public function index($id)
    {
        $bleep = Bleep::with(['user', 'media', 'comments.user', 'likes'])
            ->withTrashed()
            ->findOrFail($id);

        // Record view for single post page
        $bleep->recordView(
            Auth::user(),
            session()->getId()
        );

        return view('pages.bleeps.post', compact('bleep'));
    }
}
