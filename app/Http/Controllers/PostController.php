<?php

namespace App\Http\Controllers;

use App\Models\Bleep;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(Bleep $bleep)
    {
        // load relations used by the component and the comments list
        $bleep->load('user', 'likes', 'comments.user');

        return view('pages.post', compact('bleep'));
    }
}
