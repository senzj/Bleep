<?php

namespace App\Http\Controllers;

use App\Models\Bleep;
use App\Models\BleepViews;
use App\Models\Logs;
use App\Services\FeedService;
use App\Services\MediaUploadService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BleepController extends Controller
{
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
        $bleeps = $this->fetchBleeps($request);

        $followingBleeps = Auth::check()
            ? $this->fetchBleeps($request, null, null, 'following')
            : null;

        $friendsBleeps = Auth::check()
            ? $this->fetchBleeps($request, null, null, 'friends')
            : null;

        $this->recordBleepsViews($bleeps);

        return view('home', [
            'bleeps'         => $bleeps,
            'followingBleeps' => $followingBleeps,
            'friendsBleeps'  => $friendsBleeps,
        ]);
    }

    /**
     * Record a view for a bleep (called via AJAX — keep for single post page)
     */
    public function recordView(Bleep $bleep)
    {
        if (Auth::check() && $bleep->user && Auth::user()->isBlockedOrHasBlocked($bleep->user)) {
            abort(404);
        }

        $bleep = $this->recordSingleView($bleep);

        return response()->json([
            'success' => true,
            'views'   => $bleep->views,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'message'    => 'max:255|required_without:media',
            'is_anonymous' => 'nullable|boolean',
            'is_nsfw'    => 'nullable|boolean',
            'media'      => 'nullable|array|max:4|required_without:message',
            'media.*'    => 'file|max:102400000|mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,audio/mp3,audio/mpeg,audio/wav',
        ], [
            'message.required_without' => 'Write something or attach media.',
            'media.required_without'   => 'Attach media or write a message.',
            'media.max'                => 'You can upload up to 4 files.',
            'media.*.mimetypes'        => 'Only images (jpg, png, webp, gif), videos (mp4, webm), or audio (mp3, wav) are allowed.',
            'media.*.max'              => 'Each file must be at most 100MB.',
        ]);

        $user  = Auth::user();
        $bleep = $user->bleeps()->create([
            'message'      => $request->input('message'),
            'is_anonymous' => $request->boolean('is_anonymous'),
            'is_nsfw'      => $request->boolean('is_nsfw'),
        ]);

        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $file) {
                if (!$file->isValid()) continue;
                $bleep->media()->create(
                    MediaUploadService::saveBleepMedia($file, $bleep->id)
                );
            }
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'bleep_id' => $bleep->id]);
        }

        return redirect('/')->with('success', 'Your bleep has been posted!');
    }

    /**
     * Show a single bleep's data (for JSON response in modals).
     *
     * Used by:
     *   - Comments modal → needs is_anonymous + user identity
     *   - Edit modal     → needs is_anonymous + user identity + media (with real DB IDs)
     *
     * Route: GET /bleeps/{bleep}/data
     */
    public function show(Bleep $bleep)
    {
        $bleep->load(['user', 'media']);

        if (Auth::check() && $bleep->user && Auth::user()->isBlockedOrHasBlocked($bleep->user)) {
            abort(404);
        }

        // Build media with real integer IDs — edit modal needs these for remove_media_ids[]
        $mediaPayload = $bleep->media->map(fn ($m) => [
            'id'       => $m->id,                                    // integer PK
            'type'     => $m->type,                                  // 'image'|'video'|'audio'
            'mime'     => $m->mime_type,                             // e.g. 'image/jpeg'
            'url'      => asset('storage/' . $m->path),
            'filename' => $m->original_name ?? basename($m->path),
        ])->values()->all();

        return response()->json([
            'id'           => $bleep->id,
            'is_anonymous' => (bool) $bleep->is_anonymous,
            'user'         => $bleep->is_anonymous ? null : [
                'id'       => $bleep->user?->id,
                'username' => $bleep->user?->username,
            ],
            'media'        => $mediaPayload,
        ]);
    }

    /**
     * Update the specified resource in storage.
     * Accepts multipart/form-data so media files can be attached.
     */
    public function update(Request $request, Bleep $bleep)
    {
        $this->authorize('update', $bleep);

        $validated = $request->validate([
            'message'            => 'nullable|string|max:255',
            'is_anonymous'       => 'nullable|boolean',
            'is_nsfw'            => 'nullable|boolean',
            'media'              => 'nullable|array|max:4',
            'media.*'            => 'file|max:102400000|mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,audio/mp3,audio/mpeg,audio/wav',
            'remove_media_ids'   => 'nullable|array',
            'remove_media_ids.*' => 'nullable|integer',  // integers only, nulls filtered in JS
        ], [
            'message.max'        => 'Your bleep is too long! Keep it under 255 characters.',
            'media.max'          => 'You can upload up to 4 files.',
            'media.*.mimetypes'  => 'Only images (jpg, png, webp, gif), videos (mp4, webm), or audio (mp3, wav) are allowed.',
            'media.*.max'        => 'Each file must be at most 100MB.',
        ]);

        // Must have at least a message OR existing/new media
        $hasNewMedia      = $request->hasFile('media');
        $removeIds        = array_filter(
            array_map('intval', $validated['remove_media_ids'] ?? []),
            fn ($id) => $id > 0
        );
        $removingAll      = !empty($removeIds)
                            && $bleep->media->pluck('id')->diff($removeIds)->isEmpty()
                            && !$hasNewMedia;
        $hasExistingMedia = $bleep->media->isNotEmpty();

        if (empty($validated['message']) && !$hasNewMedia && (!$hasExistingMedia || $removingAll)) {
            return response()->json([
                'errors' => ['message' => ['Write something or attach media.']],
            ], 422);
        }

        // Remove requested media
        if (!empty($removeIds)) {
            $toRemove = $bleep->media()->whereIn('id', $removeIds)->get();
            foreach ($toRemove as $media) {
                MediaUploadService::deleteBleepMedia($media->path);
                $media->delete();
            }
        }

        // Add new media
        if ($hasNewMedia) {
            $existingCount = $bleep->media()->count();
            $files         = $request->file('media');
            $audioFiles    = array_filter($files, fn ($f) => str_starts_with($f->getMimeType(), 'audio/'));
            $otherFiles    = array_filter($files, fn ($f) => !str_starts_with($f->getMimeType(), 'audio/'));

            if (count($audioFiles) > 0 && (count($otherFiles) > 0 || $existingCount > 0)) {
                return response()->json([
                    'errors' => ['media' => ['Audio cannot be combined with other media.']],
                ], 422);
            }

            if (count($audioFiles) > 1) {
                return response()->json([
                    'errors' => ['media' => ['Only one audio file is allowed.']],
                ], 422);
            }

            $cap = count($audioFiles) > 0 ? 1 : 4;
            if (($existingCount + count($files)) > $cap) {
                return response()->json([
                    'errors' => ['media' => ["You can upload up to {$cap} file(s)."]],
                ], 422);
            }

            foreach ($files as $file) {
                if (!$file->isValid()) continue;
                $bleep->media()->create(
                    MediaUploadService::saveBleepMedia($file, $bleep->id)
                );
            }
        }

        $bleep->update([
            'message'      => $validated['message'] ?? $bleep->message,
            'is_anonymous' => $request->boolean('is_anonymous'),
            'is_nsfw'      => $request->boolean('is_nsfw'),
        ]);

        $bleep->load(['user', 'media']);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'bleep'   => array_merge(
                    [
                        'id'             => $bleep->id,
                        'message'        => $bleep->message,
                        'is_anonymous'   => (bool) $bleep->is_anonymous,
                        'is_nsfw'        => (bool) $bleep->is_nsfw,
                        'updated_at_iso' => optional($bleep->updated_at)->toIso8601String(),
                        'media'          => $bleep->media->map(fn ($m) => [
                            'id'       => $m->id,
                            'type'     => $m->type,
                            'mime'     => $m->mime_type,
                            'url'      => asset('storage/' . $m->path),
                            'filename' => $m->original_name ?? basename($m->path),
                        ])->values()->all(),
                    ],
                    $this->identityPayload($bleep)
                ),
            ]);
        }

        return back()->with('success', 'Your bleep has been updated!');
    }

    /**
     * Soft delete the specified resource.
     */
    public function destroy(Bleep $bleep)
    {
        $this->authorize('delete', $bleep);

        $bleep->update(['deleted_by_author' => true]);

        $mediaPaths = $bleep->media->pluck('path')->toArray();
        MediaUploadService::deleteBleepMediaBatch($mediaPaths);

        $bleep->delete();

        Logs::record($bleep->user_id, 'bleep_deleted', ['bleep_id' => $bleep->id, 'by_user' => Auth::id()], request());

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Your bleep has been deleted!']);
        }

        return back()->with('success', 'Your bleep has been deleted!');
    }

    /**
     * Lazy load more bleeps (for infinite scroll).
     */
    public function lazyLoad(Request $request)
    {
        $t1 = microtime(true);
        $page   = (int) $request->get('page', 2);
        $tab    = $request->get('tab', 'for-you');
        $bleeps = $this->fetchBleeps($request, $page, null, $tab);
        $t2 = microtime(true);

        $html = view('components.bleepslist', ['bleeps' => $bleeps])->render();
        $t3 = microtime(true);

        dispatch(fn() => $this->recordBleepsViews($bleeps))->afterResponse();

        // Log::info('lazyLoad timing', [
        //     'fetchBleeps' => round(($t2 - $t1) * 1000) . 'ms',
        //     'renderView'  => round(($t3 - $t2) * 1000) . 'ms',
        // ]);

        return response()->json([
            'success'      => true,
            'html'         => $html,
            'has_more'     => $bleeps->hasMorePages(),
            'next_page'    => $bleeps->currentPage() + 1,
            'current_page' => $bleeps->currentPage(),
        ]);
    }

    // Private helpers

    /**
     * Build display name / username / avatar / role / verified payload.
     * Shared by update() to avoid duplication.
     */
    private function identityPayload(Bleep $bleep): array
    {
        $viewerSeed = Auth::id() ?? request()->session()->getId();

        return [
            'display_name'     => $bleep->is_anonymous
                ? $bleep->anonymousDisplayNameFor($viewerSeed)
                : ($bleep->user->dname ?? 'Unknown'),
            'username'         => $bleep->is_anonymous
                ? '@anonymous'
                : ('@' . ($bleep->user->username ?? 'Unknown')),
            'avatar_url'       => $bleep->is_anonymous
                ? null
                : ($bleep->user->profile_picture
                    ? asset('storage/' . $bleep->user->profile_picture)
                    : asset('images/avatar/default.jpg')),
            'user_role'        => $bleep->is_anonymous ? null : ($bleep->user->role ?? null),
            'user_is_verified' => $bleep->is_anonymous ? false : (bool) ($bleep->user->is_verified ?? false),
        ];
    }

    protected function fetchBleeps(Request $request, ?int $page = null, ?int $perPage = null, string $tab = 'for-you')
    {
        return $this->feedService->getFeed($request, $tab, $page, $perPage);
    }

    protected function recordBleepsViews($bleeps)
    {
        $user      = Auth::user();
        $sessionId = session()->getId();
        $bleepIds  = $bleeps->pluck('id')->toArray();

        if (empty($bleepIds)) return;

        $existingViews = \App\Models\BleepViews::whereIn('bleep_id', $bleepIds)
            ->where(function ($q) use ($user, $sessionId) {
                $user
                    ? $q->where('user_id', $user->id)
                    : $q->where('session_id', $sessionId);
            })
            ->pluck('bleep_id')
            ->toArray();

        $newViewBleepIds = array_diff($bleepIds, $existingViews);

        if (!empty($newViewBleepIds)) {
            $viewsData = array_map(fn ($id) => [
                'bleep_id'   => $id,
                'user_id'    => $user?->id,
                'session_id' => $user ? null : $sessionId,
                'viewed_at'  => now(),
            ], $newViewBleepIds);

            BleepViews::insert($viewsData);

            DB::table((new Bleep())->getTable())
                ->whereIn('id', $newViewBleepIds)
                ->update(['views' => DB::raw('COALESCE(views,0) + 1')]);
        }
    }

    protected function recordSingleView(Bleep $bleep): Bleep
    {
        $bleep->recordView(Auth::user(), session()->getId());
        $bleep->refresh();
        return $bleep;
    }
}
