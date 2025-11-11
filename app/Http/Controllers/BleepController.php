<?php

namespace App\Http\Controllers;

use App\Models\Bleep;
use App\Models\BleepMedia;
use App\Models\Repost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class BleepController extends Controller
{
    /**
     * Use authorizeResource to apply policies.
     */
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // fetch first page via reusable fetchBleeps
        $bleeps = $this->fetchBleeps($request);

        // Record views for the fetched bleeps
        $this->recordBleepsViews($bleeps);

        // Remove the AJAX check since lazyLoad handles it
        return view('home', ['bleeps' => $bleeps]);
    }


    /**
     * Record a view for a bleep (called via AJAX - keep for single post page)
     */
    public function recordView(Bleep $bleep)
    {
        $bleep = $this->recordSingleView($bleep);

        return response()->json([
            'success' => true,
            'views' => $bleep->views,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            // either message or media is required
            'message' => 'nullable|string|max:255|required_without:media',
            'is_anonymous' => 'nullable|boolean',
            'media' => 'nullable|array|max:4|required_without:message',
            'media.*' => 'file|max:102400|mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm',
        ], [
            'message.required_without' => 'Write something or attach media.',
            'media.required_without' => 'Attach media or write a message.',
            'media.max' => 'You can upload up to 4 files.',
            'media.*.mimetypes' => 'Only images (jpg, png, webp, gif) or videos (mp4, webm) are allowed.',
            'media.*.max' => 'Each file must be at most 100MB.',
        ]);

        $user = Auth::user();
        $isAnonymous = $request->boolean('is_anonymous');

        // Create the bleep first to get its ID
        $bleep = $user->bleeps()->create([
            'message' => $validated['message'] ?? null,
            'is_anonymous' => $isAnonymous,
        ]);

        // Handle media files (if any)
        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $file) {
                if (!$file->isValid()) continue;

                $mime = $file->getClientMimeType() ?? '';
                // enum-safe mapping: only 'image' or 'video'
                $type = str_starts_with($mime, 'video/') ? 'video' : 'image';

                $username = $user->username ?? 'user';
                $originalNameNoExt = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $ext = strtolower($file->getClientOriginalExtension());

                $fileName = sprintf(
                    '%d_%s_%s.%s',
                    $bleep->id,
                    Str::slug($originalNameNoExt),
                    Str::slug($user->username ?? 'op'),
                    $ext
                );

                $dir = $username . '/bleep_post/' . $type;
                $storedPath = $file->storeAs($dir, $fileName, 'public'); // returns relative path

                // persist media row
                BleepMedia::create([
                    'bleep_id' => $bleep->id,
                    'path' => $storedPath,
                    'type' => $type,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $mime,
                    'size' => $file->getSize(),
                ]);

                // keep first media in legacy column for backwards compatibility
                if (!$bleep->media_path) {
                    $bleep->update(['media_path' => $storedPath]);
                }
            }
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'bleep_id' => $bleep->id]);
        }

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
            : ($bleep->user->profile_picture
                ? asset('storage/' . $bleep->user->profile_picture)
                : asset('images/avatar/default.jpg'));

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

    /**
     * Lazy load more bleeps (for infinite scroll)
     */
    public function lazyLoad(Request $request)
    {
        $page = (int) $request->get('page', 2);

        // fetch requested page via reusable fetchBleeps
        $bleeps = $this->fetchBleeps($request, $page);

        // Record views for the fetched bleeps
        $this->recordBleepsViews($bleeps);

        return response()->json([
            'success' => true,
            'html' => view('components.bleepslist', ['bleeps' => $bleeps])->render(),
            'has_more' => $bleeps->hasMorePages(),
            'next_page' => $bleeps->currentPage() + 1,
            'current_page' => $bleeps->currentPage(),
        ]);
    }

    /**
     * Fetch bleeps with relations, pagination and attach reposts for auth users.
     *
     * @param Request $request
     * @param int|null $page
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    protected function fetchBleeps(Request $request, ?int $page = null, int $perPage = 10)
    {
        $query = Bleep::with(['user', 'media'])->latest();

        if ($page) {
            $bleeps = $query->paginate($perPage, ['*'], 'page', $page);
        } else {
            $bleeps = $query->paginate($perPage);
        }

        if (Auth::check()) {
            $bleeps->getCollection()->transform(function ($bleep) {
                $bleep->followedReposts = Repost::visibleToUser(Auth::id(), $bleep->id);
                return $bleep;
            });
        }

        return $bleeps;
    }

    /**
     * Record views for a collection of bleeps
     */
    protected function recordBleepsViews($bleeps)
    {
        $user = Auth::user(); // null for guests
        $sessionId = session()->getId(); // Always available for guests
        $bleepIds = $bleeps->pluck('id')->toArray();

        if (empty($bleepIds)) {
            return;
        }

        // Get existing views for this user/session
        $existingViews = \App\Models\BleepViews::whereIn('bleep_id', $bleepIds)
            ->where(function($q) use ($user, $sessionId) {
                if ($user) {
                    $q->where('user_id', $user->id); // Authenticated user
                } else {
                    $q->where('session_id', $sessionId); // Guest user
                }
            })
            ->pluck('bleep_id')
            ->toArray();

        // Only record views for bleeps not yet viewed
        $newViewBleepIds = array_diff($bleepIds, $existingViews);

        if (!empty($newViewBleepIds)) {
            $viewsData = [];
            foreach ($newViewBleepIds as $bleepId) {
                $viewsData[] = [
                    'bleep_id' => $bleepId,
                    'user_id' => $user?->id, // null for guests
                    'session_id' => $user ? null : $sessionId, // only for guests
                    'viewed_at' => now(),
                ];
            }

            // Bulk insert new views
            \App\Models\BleepViews::insert($viewsData);

            // Increment view counters
            Bleep::whereIn('id', $newViewBleepIds)->increment('views');
        }
    }

    /**
     * Centralized single-bleep view recording so other controllers/actions can reuse.
     *
     * @param Bleep $bleep
     * @return Bleep
     */
    protected function recordSingleView(Bleep $bleep): Bleep
    {
        $bleep->recordView(
            Auth::user(),
            session()->getId()
        );

        // refresh to get latest views count
        $bleep->refresh();

        return $bleep;
    }
}
