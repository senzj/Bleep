<?php

namespace App\Http\Controllers;

use App\Http\Requests\Bulletin\BulletinPostsIndexRequest;
use App\Http\Requests\Bulletin\StoreBulletinPostRequest;
use App\Http\Requests\Bulletin\StoreBulletinReactionRequest;
use App\Http\Requests\Bulletin\StoreBulletinReportRequest;
use App\Http\Requests\Bulletin\UpdateBulletinPostRequest;
use App\Http\Resources\BulletinPostResource;
use App\Models\BulletinPost;
use App\Models\BulletinReaction;
use App\Models\BulletinReport;
use App\Services\Bulletin\BulletinBoardService;
use App\Services\Bulletin\BulletinVisitor;
use App\Services\MediaUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class BulletinController extends Controller
{
    /**
     * Anonymous visitors may shuffle their display name a few times a day, with
     * a short cooldown so the board cannot be renamed in a burst.
     */
    private const NAME_CHANGES_PER_DAY = 3;

    private const NAME_CHANGES_PER_IP_PER_DAY = 30;

    private const NAME_CHANGE_COOLDOWN = 300;

    public function __construct(
        private BulletinVisitor $visitor,
        private BulletinBoardService $board,
    ) {}

    public function index(Request $request): View
    {
        $this->board->pruneIfDue();

        $result = $this->board->activePosts(null, BulletinBoardService::DEFAULT_LIMIT);

        $visitorHash = $this->visitor->hash($request);
        $this->board->attachReactionSummary($result['posts'], $visitorHash);
        $this->board->attachOwnership($result['posts'], $visitorHash);

        return view('pages.bulletin.index', [
            'identity' => $this->visitor->identity($request),
            'posts' => $result['posts'],
            'initialPosts' => BulletinPostResource::collection($result['posts'])->resolve(),
            'nextCursor' => $result['next_cursor'],
            'hasMore' => $result['has_more'],
            'nameChangesLeft' => $this->remainingNameChanges($visitorHash),
            'nameCooldown' => RateLimiter::availableIn($this->nameCooldownKey($visitorHash)),
        ]);
    }

    public function posts(BulletinPostsIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $limit = (int) ($validated['limit'] ?? BulletinBoardService::DEFAULT_LIMIT);
        $result = $this->board->activePosts($validated['before'] ?? null, $limit);

        $visitorHash = $this->visitor->hash($request);
        $this->board->attachReactionSummary($result['posts'], $visitorHash);
        $this->board->attachOwnership($result['posts'], $visitorHash);

        return response()->json([
            'data' => BulletinPostResource::collection($result['posts'])->resolve(),
            'meta' => [
                'limit' => max(1, min($limit, BulletinBoardService::MAX_LIMIT)),
                'has_more' => $result['has_more'],
                'next_cursor' => $result['next_cursor'],
            ],
        ]);
    }

    public function store(StoreBulletinPostRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        $post = BulletinPost::create([
            'anonymous_name' => $this->visitor->identity($request),
            'content' => $validated['content'],
            'link_url' => $validated['link_url'] ?? null,
            'image_url' => $validated['image_url'] ?? null,
            'width' => (int) $validated['width'],
            'height' => (int) $validated['height'],
            'paper_color' => $validated['paper_color'] ?? null,
            'paper_texture' => $validated['paper_texture'] ?? null,
            'visitor_hash' => $this->visitor->hash($request),
        ]);

        $media = $request->file('media');

        if ($media?->isValid()) {
            $saved = MediaUploadService::saveBulletinMedia($media, $post->public_id);

            $post->forceFill([
                'media_path' => $saved['path'],
                'media_mime' => $saved['mime'],
                'media_kind' => $saved['kind'],
            ])->save();
        }

        if ($request->expectsJson()) {
            $post->setAttribute('is_owner', true);

            return response()->json([
                'data' => (new BulletinPostResource($post))->resolve(),
                'message' => 'Note pinned to the board.',
            ], 201);
        }

        return redirect()
            ->route('bulletin.index')
            ->with('success', 'Your note is on the board.');
    }

    public function renameIdentity(Request $request): JsonResponse
    {
        $visitorHash = $this->visitor->hash($request);
        $cooldownKey = $this->nameCooldownKey($visitorHash);
        $dailyKey = $this->nameDailyKey($visitorHash);
        $ipKey = $this->nameIpKey($this->visitor->ipHash($request));

        if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
            $seconds = RateLimiter::availableIn($cooldownKey);

            return $this->tooManyNameChanges(
                'You can change your name again in '.$this->humanizeSeconds($seconds).'.',
                $seconds,
                $this->remainingNameChanges($visitorHash),
            );
        }

        if (RateLimiter::tooManyAttempts($dailyKey, self::NAME_CHANGES_PER_DAY)) {
            $seconds = RateLimiter::availableIn($dailyKey);

            return $this->tooManyNameChanges(
                'You can change your name '.self::NAME_CHANGES_PER_DAY.' times a day. Try again in '
                    .$this->humanizeSeconds($seconds).'.',
                $seconds,
                0,
            );
        }

        if (RateLimiter::tooManyAttempts($ipKey, self::NAME_CHANGES_PER_IP_PER_DAY)) {
            $seconds = RateLimiter::availableIn($ipKey);

            return $this->tooManyNameChanges(
                'Too many name changes from this network. Try again in '
                    .$this->humanizeSeconds($seconds).'.',
                $seconds,
                $this->remainingNameChanges($visitorHash),
            );
        }

        RateLimiter::hit($cooldownKey, self::NAME_CHANGE_COOLDOWN);
        RateLimiter::hit($dailyKey, 86400);
        RateLimiter::hit($ipKey, 86400);

        $identity = $this->visitor->rotateIdentity($request);

        return response()->json([
            'data' => [
                'identity' => $identity,
                'changes_left' => $this->remainingNameChanges($visitorHash),
                'retry_after' => self::NAME_CHANGE_COOLDOWN,
            ],
            'message' => 'Your name is now '.$identity.'. New notes will use it.',
        ]);
    }

    public function update(UpdateBulletinPostRequest $request, string $post): JsonResponse
    {
        $target = BulletinPost::query()
            ->active()
            ->where('public_id', $post)
            ->firstOrFail();

        if (! hash_equals($target->visitor_hash, $this->visitor->hash($request))) {
            return response()->json([
                'message' => 'Only the visitor who pinned this note can edit it.',
            ], 403);
        }

        if (! $target->isEditable()) {
            return response()->json([
                'message' => 'Notes can only be edited within 24 hours of pinning.',
            ], 403);
        }

        $validated = $request->validated();

        $target->forceFill([
            'content' => $validated['content'],
            'link_url' => $validated['link_url'] ?? null,
            'edited_at' => now(),
        ])->save();

        $visitorHash = $this->visitor->hash($request);
        $this->board->attachReactionSummary(collect([$target]), $visitorHash);
        $target->setAttribute('is_owner', true);

        return response()->json([
            'data' => (new BulletinPostResource($target))->resolve(),
            'message' => 'Note updated.',
        ]);
    }

    public function destroy(Request $request, string $post): JsonResponse
    {
        $target = BulletinPost::query()
            ->active()
            ->where('public_id', $post)
            ->firstOrFail();

        if (! hash_equals($target->visitor_hash, $this->visitor->hash($request))) {
            return response()->json([
                'message' => 'Only the visitor who pinned this note can delete it.',
            ], 403);
        }

        $target->delete();

        return response()->json([
            'data' => ['id' => $target->public_id],
            'message' => 'Note deleted.',
        ]);
    }

    public function react(StoreBulletinReactionRequest $request, string $post): JsonResponse
    {
        $target = BulletinPost::query()
            ->active()
            ->where('public_id', $post)
            ->firstOrFail();

        $visitorHash = $this->visitor->hash($request);
        $type = (string) $request->validated('reaction_type');

        $existing = BulletinReaction::query()
            ->where('bulletin_post_id', $target->id)
            ->where('visitor_hash', $visitorHash)
            ->first();

        if ($existing?->reaction_type === $type) {
            $existing->delete();
        } else {
            BulletinReaction::query()->updateOrCreate(
                [
                    'bulletin_post_id' => $target->id,
                    'visitor_hash' => $visitorHash,
                ],
                ['reaction_type' => $type],
            );
        }

        $this->board->attachReactionSummary(collect([$target]), $visitorHash);

        return response()->json([
            'data' => [
                'id' => $target->public_id,
                'reactions' => [
                    'counts' => $target->reaction_counts,
                    'mine' => $target->my_reaction,
                ],
            ],
        ]);
    }

    public function report(StoreBulletinReportRequest $request, string $post): JsonResponse
    {
        $target = BulletinPost::query()
            ->active()
            ->where('public_id', $post)
            ->firstOrFail();

        $report = BulletinReport::query()->firstOrCreate(
            [
                'bulletin_post_id' => $target->id,
                'visitor_hash' => $this->visitor->hash($request),
            ],
            ['reason' => (string) $request->validated('reason')],
        );

        return response()->json([
            'data' => [
                'id' => $target->public_id,
                'reported' => true,
                'already_reported' => ! $report->wasRecentlyCreated,
            ],
        ], $report->wasRecentlyCreated ? 201 : 200);
    }

    private function nameCooldownKey(string $visitorHash): string
    {
        return 'bulletin-name-cooldown:'.$visitorHash;
    }

    private function nameDailyKey(string $visitorHash): string
    {
        return 'bulletin-name-changes:'.$visitorHash;
    }

    private function nameIpKey(string $ipHash): string
    {
        return 'bulletin-name-changes-ip:'.$ipHash;
    }

    private function remainingNameChanges(string $visitorHash): int
    {
        return max(
            0,
            self::NAME_CHANGES_PER_DAY - RateLimiter::attempts($this->nameDailyKey($visitorHash)),
        );
    }

    private function tooManyNameChanges(string $message, int $retryAfter, int $changesLeft): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'retry_after' => $retryAfter,
            'changes_left' => $changesLeft,
        ], 429, ['Retry-After' => (string) $retryAfter]);
    }

    /**
     * Short, human wording for a cooldown: "45 seconds", "5 minutes", "2 hours".
     */
    private function humanizeSeconds(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.' second'.($seconds === 1 ? '' : 's');
        }

        if ($seconds < 3600) {
            $minutes = (int) ceil($seconds / 60);

            return $minutes.' minute'.($minutes === 1 ? '' : 's');
        }

        $hours = (int) ceil($seconds / 3600);

        return $hours.' hour'.($hours === 1 ? '' : 's');
    }
}
