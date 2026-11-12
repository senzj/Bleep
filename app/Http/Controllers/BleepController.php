<?php

namespace App\Http\Controllers;

use App\Models\Bleep;
use App\Models\BleepMedia;
use App\Models\BleepViews;
use App\Models\Logs;
use App\Models\MediaUpload;
use App\Services\FeedService;
use App\Services\MediaUploadService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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
        // Only the active tab is rendered server-side. The Following and
        // Friends tabs are fetched on demand from /bleeps/lazy-load, which
        // keeps the first payload (and its icon/media hydration) small.
        $bleeps = $this->fetchBleeps($request);

        $this->recordBleepsViews($bleeps);

        return view('home', [
            'bleeps' => $bleeps,
            'followingBleeps' => null,
            'friendsBleeps' => null,
        ]);
    }

    /**
     * Record a view for a bleep (called via AJAX — keep for single post page)
     */
    public function recordView(Bleep $bleep)
    {
        $bleep->loadMissing('user.preferences');

        abort_unless($bleep->isVisibleTo(Auth::user()), 404);

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
        $this->guardPhpUploadLimits($request);

        if ($request->hasFile('media')) {
            @set_time_limit(0);
        }

        $maxFileKb = (int) config('media.max_bleep_file_kb', 2 * 1024 * 1024);
        $maxFileLabel = $this->formatFileSizeLimit($maxFileKb);

        $validated = $request->validate([
            'message' => 'max:255|required_without_all:media,upload_ids',
            'is_anonymous' => 'nullable|boolean',
            'is_nsfw' => 'nullable|boolean',
            'media' => 'nullable|array|max:9|required_without_all:message,upload_ids',
            'media.*' => 'file|max:'.$maxFileKb.'|'.config('media.mimes_rule'),
            'upload_ids' => 'nullable|array|max:9',
            'upload_ids.*' => 'string|max:64',
        ], [
            'message.required_without_all' => 'Write something or attach media.',
            'media.required_without_all' => 'Attach media or write a message.',
            'media.max' => 'You can upload up to 9 files.',
            'media.*.mimetypes' => 'Only images (jpg, png, webp, gif), videos (mp4, webm), or audio (mp3, wav) are allowed.',
            'media.*.max' => "Each file must be at most {$maxFileLabel}.",
        ]);

        $uploads = $this->resolveCompletedUploads($validated['upload_ids'] ?? []);
        $files = $request->file('media', []);
        $newTypes = array_merge(
            array_map(fn ($file) => MediaUploadService::mediaTypeFromMime((string) $file->getMimeType()), $files),
            $uploads->map(fn (MediaUpload $upload) => MediaUploadService::mediaTypeFromMime((string) $upload->mime_type))->all(),
        );
        $this->assertBleepMediaRules($newTypes);

        $user = Auth::user();
        $bleep = $user->bleeps()->create([
            'message' => $request->input('message'),
            'is_anonymous' => $request->boolean('is_anonymous'),
            'is_nsfw' => $request->boolean('is_nsfw'),
        ]);

        foreach ($files as $file) {
            if (! $file->isValid()) {
                continue;
            }

            $media = MediaUploadService::saveBleepMedia($file, $bleep->id);
            $bleep->media()->create([
                'path' => $media['path'],
                'type' => $media['type'],
                'mime_type' => $media['mime'],
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ]);
        }

        $this->claimUploads($bleep, $uploads);

        if ($request->ajax() || $request->wantsJson()) {
            $bleep->load(['user.preferences', 'media']);

            return response()->json([
                'success' => true,
                'bleep_id' => $bleep->id,
                'html' => view('components.bleep', [
                    'bleep' => $bleep,
                    'justPosted' => true,
                ])->render(),
                'undo_url' => route('bleeps.destroy', ['bleep' => $bleep->id]),
            ]);
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
        $bleep->load(['user.preferences', 'media']);

        abort_unless($bleep->isVisibleTo(Auth::user()), 404);

        // Build media with real integer IDs — edit modal needs these for remove_media_ids[]
        $mediaPayload = $bleep->media->map(fn ($m) => [
            'id' => $m->id,                                    // integer PK
            'type' => $m->type,                                  // 'image'|'video'|'audio'
            'mime' => $m->mime_type,                             // e.g. 'image/jpeg'
            'url' => asset('storage/'.$m->path),
            'filename' => $m->original_name ?? basename($m->path),
        ])->values()->all();

        return response()->json([
            'id' => $bleep->id,
            'is_anonymous' => (bool) $bleep->is_anonymous,
            'user' => $bleep->is_anonymous ? null : [
                'id' => $bleep->user?->id,
                'username' => $bleep->user?->username,
            ],
            'media' => $mediaPayload,
        ]);
    }

    /**
     * Update the specified resource in storage.
     * Accepts multipart/form-data so media files can be attached.
     */
    public function update(Request $request, Bleep $bleep)
    {
        $this->authorize('update', $bleep);
        $this->guardPhpUploadLimits($request);

        if ($request->hasFile('media')) {
            @set_time_limit(0);
        }

        $maxFileKb = (int) config('media.max_bleep_file_kb', 2 * 1024 * 1024);
        $maxFileLabel = $this->formatFileSizeLimit($maxFileKb);

        $validated = $request->validate([
            'message' => 'nullable|string|max:255',
            'is_anonymous' => 'nullable|boolean',
            'is_nsfw' => 'nullable|boolean',
            'media' => 'nullable|array|max:9',
            'media.*' => 'file|max:'.$maxFileKb.'|'.config('media.mimes_rule'),
            'upload_ids' => 'nullable|array|max:9',
            'upload_ids.*' => 'string|max:64',
            'remove_media_ids' => 'nullable|array',
            'remove_media_ids.*' => 'nullable|integer',  // integers only, nulls filtered in JS
        ], [
            'message.max' => 'Your bleep is too long! Keep it under 255 characters.',
            'media.max' => 'You can upload up to 9 files.',
            'media.*.mimetypes' => 'Only images (jpg, png, webp, gif), videos (mp4, webm), or audio (mp3, wav) are allowed.',
            'media.*.max' => "Each file must be at most {$maxFileLabel}.",
        ]);

        $uploads = $this->resolveCompletedUploads($validated['upload_ids'] ?? []);
        $files = $request->file('media', []);
        $hasNewMedia = ! empty($files) || $uploads->isNotEmpty();

        // Must have at least a message OR existing/new media
        $removeIds = array_filter(
            array_map('intval', $validated['remove_media_ids'] ?? []),
            fn ($id) => $id > 0
        );
        $removingAll = ! empty($removeIds)
                            && $bleep->media->pluck('id')->diff($removeIds)->isEmpty()
                            && ! $hasNewMedia;
        $hasExistingMedia = $bleep->media->isNotEmpty();

        if (empty($validated['message']) && ! $hasNewMedia && (! $hasExistingMedia || $removingAll)) {
            return response()->json([
                'errors' => ['message' => ['Write something or attach media.']],
            ], 422);
        }

        // Remove requested media
        if (! empty($removeIds)) {
            $toRemove = $bleep->media()->whereIn('id', $removeIds)->get();
            foreach ($toRemove as $media) {
                MediaUploadService::deleteBleepMedia($media->path);
                $media->delete();
            }

            $bleep->unsetRelation('media');
        }

        // Add new media
        if ($hasNewMedia) {
            $existingTypes = $bleep->media()->pluck('type')->all();
            $newTypes = array_merge(
                array_map(fn ($file) => MediaUploadService::mediaTypeFromMime((string) $file->getMimeType()), $files),
                $uploads->map(fn (MediaUpload $upload) => MediaUploadService::mediaTypeFromMime((string) $upload->mime_type))->all(),
            );

            $this->assertBleepMediaRules($newTypes, $existingTypes);

            foreach ($files as $file) {
                if (! $file->isValid()) {
                    continue;
                }

                $media = MediaUploadService::saveBleepMedia($file, $bleep->id);
                $bleep->media()->create([
                    'path' => $media['path'],
                    'type' => $media['type'],
                    'mime_type' => $media['mime'],
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                ]);
            }

            $this->claimUploads($bleep, $uploads);
        }

        $bleep->update([
            'message' => $validated['message'] ?? $bleep->message,
            'is_anonymous' => $request->boolean('is_anonymous'),
            'is_nsfw' => $request->boolean('is_nsfw'),
        ]);

        $bleep->load(['user', 'media']);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'bleep' => array_merge(
                    [
                        'id' => $bleep->id,
                        'message' => $bleep->message,
                        'is_anonymous' => (bool) $bleep->is_anonymous,
                        'is_nsfw' => (bool) $bleep->is_nsfw,
                        'updated_at_iso' => optional($bleep->updated_at)->toIso8601String(),
                        'media' => $bleep->media->map(fn ($m) => [
                            'id' => $m->id,
                            'type' => $m->type,
                            'mime' => $m->mime_type,
                            'url' => asset('storage/'.$m->path),
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
        $page = (int) $request->get('page', 2);
        $tab = $request->get('tab', 'for-you');
        $bleeps = $this->fetchBleeps($request, $page, null, $tab);
        $t2 = microtime(true);

        $html = view('components.bleepslist', ['bleeps' => $bleeps])->render();
        $t3 = microtime(true);

        dispatch(fn () => $this->recordBleepsViews($bleeps))->afterResponse();

        // Log::info('lazyLoad timing', [
        //     'fetchBleeps' => round(($t2 - $t1) * 1000) . 'ms',
        //     'renderView'  => round(($t3 - $t2) * 1000) . 'ms',
        // ]);

        return response()->json([
            'success' => true,
            'html' => $html,
            'has_more' => $bleeps->hasMorePages(),
            'next_page' => $bleeps->currentPage() + 1,
            'current_page' => $bleeps->currentPage(),
        ]);
    }

    /**
     * Persist the measured duration of a post's audio/video so the player can
     * show the real length before metadata loads on later visits.
     */
    public function mediaDuration(Request $request, BleepMedia $media): JsonResponse
    {
        abort_unless((int) $media->bleep?->user_id === (int) Auth::id(), 403);

        $validated = $request->validate([
            'duration_seconds' => ['required', 'numeric', 'min:0', 'max:86400'],
        ]);

        $media->update([
            'duration_seconds' => (int) round($validated['duration_seconds']),
        ]);

        return response()->json([
            'success' => true,
            'duration_seconds' => $media->duration_seconds,
            'duration_label' => $media->durationLabel(),
        ]);
    }

    // Private helpers

    /**
     * Resolve completed chunk uploads that belong to the current user.
     *
     * @param  array<int, string>  $uploadIds
     * @return Collection<int, MediaUpload>
     */
    private function resolveCompletedUploads(array $uploadIds): Collection
    {
        $uploadIds = array_values(array_unique(array_filter(array_map('strval', $uploadIds))));

        if ($uploadIds === []) {
            return collect();
        }

        $uploads = MediaUpload::query()
            ->where('user_id', Auth::id())
            ->whereIn('upload_id', $uploadIds)
            ->where('status', MediaUpload::STATUS_COMPLETE)
            ->where('expires_at', '>', now())
            ->get();

        if ($uploads->count() !== count($uploadIds)) {
            throw ValidationException::withMessages([
                'upload_ids' => ['One or more uploads are missing or expired. Please attach the file again.'],
            ]);
        }

        return collect($uploadIds)
            ->map(fn (string $id) => $uploads->firstWhere('upload_id', $id))
            ->filter()
            ->values();
    }

    /**
     * Move completed uploads into the bleep's media directory.
     *
     * @param  Collection<int, MediaUpload>  $uploads
     */
    private function claimUploads(Bleep $bleep, Collection $uploads): void
    {
        foreach ($uploads as $upload) {
            $media = MediaUploadService::saveBleepMediaFromUpload($upload, $bleep->id);

            $bleep->media()->create([
                'path' => $media['path'],
                'type' => $media['type'],
                'mime_type' => $media['mime'],
                'original_name' => $media['original_name'],
                'size' => $media['size'],
            ]);

            MediaUploadService::deleteUploadTemp($upload);
            $upload->delete();
        }
    }

    /**
     * Enforce the audio-only and four-file limits across new and existing media.
     *
     * @param  array<int, string>  $newTypes
     * @param  array<int, string>  $existingTypes
     */
    private function assertBleepMediaRules(array $newTypes, array $existingTypes = []): void
    {
        $allTypes = array_merge($existingTypes, $newTypes);
        $audioCount = count(array_filter($allTypes, fn (string $type) => $type === 'audio'));
        $total = count($allTypes);

        if ($audioCount > 0 && $total > 1) {
            throw ValidationException::withMessages([
                'media' => ['Audio cannot be combined with other media.'],
            ]);
        }

        if ($audioCount > 1) {
            throw ValidationException::withMessages([
                'media' => ['Only one audio file is allowed.'],
            ]);
        }

        $cap = $audioCount > 0 ? 1 : 9;

        if ($total > $cap) {
            throw ValidationException::withMessages([
                'media' => ["You can upload up to {$cap} file(s)."],
            ]);
        }
    }

    /**
     * PHP silently drops the request body when it is larger than post_max_size.
     * Surface a useful message instead of the generic "attach media" error.
     */
    private function guardPhpUploadLimits(Request $request): void
    {
        $contentLength = (int) $request->server('CONTENT_LENGTH');
        $postMax = $this->phpSizeToBytes((string) ini_get('post_max_size'));

        if ($contentLength <= 0 || $postMax <= 0 || $contentLength <= $postMax) {
            return;
        }

        if (! empty($_POST) || ! empty($_FILES)) {
            return;
        }

        throw ValidationException::withMessages([
            'media' => [
                'This upload is larger than the server accepts (post_max_size = '.ini_get('post_max_size').'). '
                .'Increase post_max_size and upload_max_filesize in php.ini. See the README section "Large media uploads".',
            ],
        ]);
    }

    private function phpSizeToBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return 0;
        }

        $unit = strtolower($value[strlen($value) - 1]);
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private function formatFileSizeLimit(int $kilobytes): string
    {
        return $kilobytes >= 1024 * 1024
            ? rtrim(rtrim(number_format($kilobytes / (1024 * 1024), 1), '0'), '.').' GB'
            : round($kilobytes / 1024).' MB';
    }

    /**
     * Build display name / username / avatar / role / verified payload.
     * Shared by update() to avoid duplication.
     */
    private function identityPayload(Bleep $bleep): array
    {
        $viewerSeed = Auth::id() ?? request()->session()->getId();

        return [
            'display_name' => $bleep->is_anonymous
                ? $bleep->anonymousDisplayNameFor($viewerSeed)
                : ($bleep->user->dname ?? 'Unknown'),
            'username' => $bleep->is_anonymous
                ? '@anonymous'
                : ('@'.($bleep->user->username ?? 'Unknown')),
            'avatar_url' => $bleep->is_anonymous
                ? null
                : ($bleep->user->profile_picture
                    ? asset('storage/'.$bleep->user->profile_picture)
                    : asset('images/avatar/default.jpg')),
            'user_role' => $bleep->is_anonymous ? null : ($bleep->user->role ?? null),
            'user_is_verified' => $bleep->is_anonymous ? false : (bool) ($bleep->user->is_verified ?? false),
        ];
    }

    protected function fetchBleeps(Request $request, ?int $page = null, ?int $perPage = null, string $tab = 'for-you')
    {
        return $this->feedService->getFeed($request, $tab, $page, $perPage);
    }

    protected function recordBleepsViews($bleeps)
    {
        $user = Auth::user();
        $sessionId = session()->getId();
        $bleepIds = $bleeps->pluck('id')->toArray();

        if (empty($bleepIds)) {
            return;
        }

        $existingViews = BleepViews::whereIn('bleep_id', $bleepIds)
            ->where(function ($q) use ($user, $sessionId) {
                $user
                    ? $q->where('user_id', $user->id)
                    : $q->where('session_id', $sessionId);
            })
            ->pluck('bleep_id')
            ->toArray();

        $newViewBleepIds = array_diff($bleepIds, $existingViews);

        if (! empty($newViewBleepIds)) {
            $viewsData = array_map(fn ($id) => [
                'bleep_id' => $id,
                'user_id' => $user?->id,
                'session_id' => $user ? null : $sessionId,
                'viewed_at' => now(),
            ], $newViewBleepIds);

            BleepViews::insert($viewsData);

            DB::table((new Bleep)->getTable())
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
