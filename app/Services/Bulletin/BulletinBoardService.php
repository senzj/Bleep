<?php

namespace App\Services\Bulletin;

use App\Enums\BulletinReactionType;
use App\Models\BulletinPost;
use App\Models\BulletinReaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Expiry-aware listing for the bulletin board.
 *
 * The public cursor is the post's public ULID. Internally pagination uses the
 * auto-incrementing id so ordering stays stable without exposing numeric ids.
 */
class BulletinBoardService
{
    public const DEFAULT_LIMIT = 30;

    public const MAX_LIMIT = 100;

    /**
     * @return array{
     *     posts: Collection<int, BulletinPost>,
     *     has_more: bool,
     *     next_cursor: string|null
     * }
     */
    public function activePosts(?string $beforePublicId = null, int $limit = self::DEFAULT_LIMIT): array
    {
        $limit = max(1, min($limit, self::MAX_LIMIT));

        $query = BulletinPost::query()
            ->active()
            ->orderByDesc('id');

        if ($beforePublicId !== null && $beforePublicId !== '') {
            $cursor = BulletinPost::query()
                ->where('public_id', $beforePublicId)
                ->firstOrFail(['id']);

            $query->where('id', '<', $cursor->id);
        }

        $posts = $query->limit($limit + 1)->get();
        $hasMore = $posts->count() > $limit;
        $posts = $posts->take($limit)->values();

        return [
            'posts' => $posts,
            'has_more' => $hasMore,
            'next_cursor' => $hasMore ? $posts->last()?->public_id : null,
        ];
    }

    /**
     * Attach per-type counts and the current visitor's reaction to each post.
     *
     * Two queries cover the whole page, so the board never runs a reaction
     * query per note.
     *
     * @param  Collection<int, BulletinPost>  $posts
     */
    public function attachReactionSummary(Collection $posts, string $visitorHash): void
    {
        if ($posts->isEmpty()) {
            return;
        }

        $postIds = $posts->pluck('id')->all();

        $counts = BulletinReaction::query()
            ->whereIn('bulletin_post_id', $postIds)
            ->selectRaw('bulletin_post_id, reaction_type, COUNT(*) as aggregate')
            ->groupBy('bulletin_post_id', 'reaction_type')
            ->get()
            ->groupBy('bulletin_post_id');

        $mine = BulletinReaction::query()
            ->whereIn('bulletin_post_id', $postIds)
            ->where('visitor_hash', $visitorHash)
            ->pluck('reaction_type', 'bulletin_post_id');

        foreach ($posts as $post) {
            /** @var array<string, int> $reactionCounts */
            $reactionCounts = array_fill_keys(BulletinReactionType::values(), 0);

            $counts->get($post->id, collect())
                ->each(function (object $row) use (&$reactionCounts): void {
                    $reactionCounts[$row->reaction_type] = (int) $row->aggregate;
                });

            $post->setAttribute('reaction_counts', $reactionCounts);
            $post->setAttribute('my_reaction', $mine->get($post->id));
        }
    }

    /**
     * Mark the notes pinned by the current visitor. The flag is transient and
     * never persisted; it lets the board show edit and delete only on their own
     * notes without revealing the private visitor hash.
     *
     * @param  Collection<int, BulletinPost>  $posts
     */
    public function attachOwnership(Collection $posts, string $visitorHash): void
    {
        foreach ($posts as $post) {
            $post->setAttribute('is_owner', hash_equals($post->visitor_hash, $visitorHash));
        }
    }

    /**
     * Archive notes whose expiry has passed, then delete notes that have been
     * archived for a while. Reactions and reports follow the foreign key
     * cascade.
     *
     * @return array{archived: int, deleted: int}
     */
    public function prune(int $keepArchivedDays = 30): array
    {
        $archived = BulletinPost::query()
            ->whereNull('archived_at')
            ->where('expires_at', '<=', now())
            ->update(['archived_at' => now()]);

        $deleted = 0;

        BulletinPost::query()
            ->whereNotNull('archived_at')
            ->where('archived_at', '<=', now()->subDays(max(0, $keepArchivedDays)))
            ->chunkById(100, function (Collection $posts) use (&$deleted): void {
                $posts->each(function (BulletinPost $post) use (&$deleted): void {
                    $post->delete();
                    $deleted++;
                });
            });

        return ['archived' => $archived, 'deleted' => $deleted];
    }

    /**
     * Hosts without a scheduler still get cleaned up: at most one sweep per
     * hour, triggered by a real board visit rather than every request.
     */
    public function pruneIfDue(): void
    {
        if (! Cache::add('bulletin:prune-sweep', true, now()->addHour())) {
            return;
        }

        $this->prune();
    }
}
