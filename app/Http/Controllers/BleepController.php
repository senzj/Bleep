<?php

namespace App\Http\Controllers;

use App\Models\Logs;
use App\Models\Bleep;

use App\Models\BleepViews;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Services\MediaUploadService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Services\FeedService;

class BleepController extends Controller
{
    /**
     * Use authorizeResource to apply policies.
     */
    use AuthorizesRequests;

    protected FeedService $feedService;

    public function __construct(FeedService $feedService)
    {
        $this->feedService = $feedService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // fetch first page via reusable fetchBleeps
        $bleeps = $this->fetchBleeps($request);

        $followingBleeps = Auth::check()
            ? $this->fetchBleeps($request, null, null, 'following')
            : null;

        $friendsBleeps = Auth::check()
            ? $this->fetchBleeps($request, null, null, 'friends')
            : null;

        // Record views for the fetched bleeps (active tab only)
        $this->recordBleepsViews($bleeps);

        // Remove the AJAX check since lazyLoad handles it
        return view('home', [
            'bleeps' => $bleeps,
            'followingBleeps' => $followingBleeps,
            'friendsBleeps' => $friendsBleeps,
        ]);
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
        Log::info("Request", $request->all());

        $validated = $request->validate([
            'message' => 'string|max:255|required_without:media',
            'is_anonymous' => 'nullable|boolean',
            'is_nsfw' => 'nullable|boolean',
            'media' => 'nullable|array|max:4|required_without:message',
            'media.*' => 'file|max:102400000|mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,audio/mp3,audio/mpeg,audio/wav',
        ], [
            'message.required_without' => 'Write something or attach media.',
            'media.required_without' => 'Attach media or write a message.',
            'media.max' => 'You can upload up to 4 files.',
            'media.*.mimetypes' => 'Only images (jpg, png, webp, gif), videos (mp4, webm), or audio (mp3, wav) are allowed.',
            'media.*.max' => 'Each file must be at most 100MB.',
        ]);

        $user = Auth::user();

        // Create the bleep first to get ID for media storage path
        $bleep = $user->bleeps()->create([
            'message'      => $request->input('message'),
            'is_anonymous' => $request->boolean('is_anonymous'),
            'is_nsfw'      => $request->boolean('is_nsfw'),
        ]);

        // Handle media uploads with bleep ID
        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $file) {
                if (!$file->isValid()) continue;

                $mediaData = MediaUploadService::saveBleepMedia($file, $bleep->id);

                $bleep->media()->create($mediaData);
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
            'is_nsfw' => 'nullable|boolean',
        ], [
            'message.required' => 'Thoughts cannot be empty! Write something to bleep about.',
            'message.max' => 'Your bleep is too long! Keep it under 255 characters.',
        ]);

        $bleep->update([
            'message' => $validated['message'],
            'is_anonymous' => $request->boolean('is_anonymous'),
            'is_nsfw' => $request->boolean('is_nsfw'),
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
                    'is_nsfw' => (bool) $bleep->is_nsfw,
                    'display_name' => $displayName,
                    'username' => $username,
                    'avatar_url' => $avatarUrl,
                    'updated_at_iso' => optional($bleep->updated_at)->toIso8601String(),

                    // NEW: expose minimal user metadata so client can render badges/icons
                    'user_role' => $bleep->is_anonymous ? null : ($bleep->user->role ?? null),
                    'user_is_verified' => $bleep->is_anonymous ? false : (bool) ($bleep->user->is_verified ?? false),
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

        $bleep->update(['deleted_by_author' => true]);

        // Delete media files - SIMPLIFIED!
        $mediaPaths = $bleep->media->pluck('path')->toArray();
        MediaUploadService::deleteBleepMediaBatch($mediaPaths);

        $bleep->delete();

        Logs::record($bleep->user_id, 'bleep_deleted', ['bleep_id' => $bleep->id, 'by_user' => Auth::id()], request());

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
        $tab = $request->get('tab', 'for-you');

        // fetch requested page via reusable fetchBleeps
        $bleeps = $this->fetchBleeps($request, $page, null, $tab);

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
    protected function fetchBleeps(Request $request, ?int $page = null, ?int $perPage = null, string $tab = 'for-you')
    {
        return $this->feedService->getFeed($request, $tab, $page, $perPage);
    }

    /**
     * Show a single bleep's data (for JSON response in modals)
     */
    public function show(Bleep $bleep)
    {
        return response()->json([
            'id' => $bleep->id,
            'user' => [
                'id' => $bleep->user?->id,
                'username' => $bleep->user?->username,
            ]
        ]);
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
            BleepViews::insert($viewsData);

            // Increment view counters WITHOUT updating updated_at
            DB::table((new Bleep())->getTable())
                ->whereIn('id', $newViewBleepIds)
                ->update(['views' => DB::raw('COALESCE(views,0) + 1')]);
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
