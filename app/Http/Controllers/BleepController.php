<?php

namespace App\Http\Controllers;

use App\Models\Bleep;
use Illuminate\Http\Request;

class BleepController extends Controller
{
    public function index()
    {
        $bleeps = Bleep::with('user')
            ->latest()
            ->take(50)
            ->get();

        return view('home', ['bleeps' => $bleeps]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:255',
        ], [
            'message.required' => 'Thoughts cannot be empty! Write something to bleep about.',
            'message.max' => 'Your bleep is too long! Keep it under 255 characters.',
        ]);


        Bleep::create([
            'message' => $validated['message'],
        ]);

        return redirect('/')->with('success', 'Your bleep has been posted!');
    }
}
