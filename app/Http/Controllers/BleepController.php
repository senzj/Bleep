<?php

namespace App\Http\Controllers;

use App\Models\Bleep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            ->paginate(50);

        return view('home', ['bleeps' => $bleeps]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:255',
            'is_anonymous' => 'nullable|boolean',
        ], [
            'message.required' => 'Thoughts cannot be empty! Write something to bleep about.',
            'message.max' => 'Your bleep is too long! Keep it under 255 characters.',
        ]);


        $isAnonymous = $request->boolean('is_anonymous');

        Auth::user()->bleeps()->create([
            'message' => $validated['message'],
            'is_anonymous' => $isAnonymous,
        ]);

        return redirect('/')->with('success', 'Your bleep has been posted!');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Bleep $bleep)
    {
        $this->authorize('update', $bleep);

        $validated = $request->validate([
            'message' => 'required|string|max:255',
            'is_anonymous' => 'nullable|boolean',
        ], [
            'message.required' => 'Thoughts cannot be empty! Write something to bleep about.',
            'message.max' => 'Your bleep is too long! Keep it under 255 characters.',
        ]);

        $bleep->update([
            'message' => $validated['message'],
            'is_anonymous' => $request->boolean('is_anonymous'),
        ]);

        // reload relations
        $bleep->load('user');

        // viewer seed for deterministic display name
        $viewerSeed = Auth::check() ? Auth::id() : $request->session()->getId();

        $displayName = $bleep->is_anonymous
            ? $bleep->anonymousDisplayNameFor($viewerSeed)
            : ($bleep->user->dname ?? 'Unknown');

        $username = $bleep->is_anonymous
            ? '@anonymous'
            : ('@' . ($bleep->user->username ?? 'Unknown'));

        $avatarUrl = $bleep->is_anonymous
            ? null
            : 'https://avatars.laravel.cloud/' . urlencode($bleep->user->email);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'bleep' => [
                    'id' => $bleep->id,
                    'message' => $bleep->message,
                    'is_anonymous' => (bool) $bleep->is_anonymous,
                    'display_name' => $displayName,
                    'username' => $username,
                    'avatar_url' => $avatarUrl,
                    'updated_at_iso' => optional($bleep->updated_at)->toIso8601String(),
                ],
            ]);
        }

        return redirect('/')->with('success', 'Your bleep has been updated!');
    }

    /**
     * Soft delete the specified resource (marks as deleted by author)
     */
    public function destroy(Bleep $bleep)
    {
        $this->authorize('delete', $bleep);

        // Mark as deleted by author before soft deleting
        $bleep->update(['deleted_by_author' => true]);

        // Soft delete will trigger cascade deletes in boot method
        $bleep->delete();

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Your bleep has been deleted!'
            ]);
        }

        return redirect('/')->with('success', 'Your bleep has been deleted!');
    }
}
