<?php

namespace App\Http\Controllers;

use App\Models\Bleep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class BleepController extends Controller
{
    /**
     * Use authorizeResource to apply policies.
     */
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $bleeps = Bleep::with('user')
            ->latest()
            ->take(50)
            ->get();

        Log::info('Bleeps: ' . $bleeps->toJson());

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


        Auth::user()->bleeps()->create([
            'message' => $validated['message'],
        ]);

        return redirect('/')->with('success', 'Your bleep has been posted!');
    }

    /**
     * shows the Edit Bleep form.
     */
    public function edit(Bleep $bleep)
    {
        return view('bleeps.edit', compact('bleep'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Bleep $bleep)
    {
        $this->authorize('update', $bleep);

        $validated = $request->validate([
            'message' => 'required|string|max:255',
        ], [
            'message.required' => 'Thoughts cannot be empty! Write something to bleep about.',
            'message.max' => 'Your bleep is too long! Keep it under 255 characters.',
        ]);

        $bleep->update([
            'message' => $validated['message'],
        ]);

        return redirect('/')->with('success', 'Your bleep has been updated!');
    }

    /**
     * Delete the specified resource from storage.
     */
    public function destroy(Bleep $bleep)
    {
        $this->authorize('delete', $bleep);

        $bleep->delete();

        return redirect('/')->with('success', 'Your bleep has been deleted!');
    }

}
