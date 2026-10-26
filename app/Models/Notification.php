<?php

namespace App\Models;

use App\Enums\NotificationAudience;
use App\Enums\NotificationCategory;
use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * In-app notification row.
 *
 * `data` is a render snapshot (actor name, excerpt, reason, ...) so the inbox
 * keeps rendering after the subject is deleted.
 *
 * @property NotificationType $type
 * @property NotificationAudience $audience
 * @property NotificationCategory $category
 * @property array<string, mixed>|null $data
 */
class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'actor_id',
        'type',
        'audience',
        'category',
        'subject_type',
        'subject_id',
        'url',
        'group_key',
        'group_count',
        'data',
        'read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'audience' => NotificationAudience::class,
            'category' => NotificationCategory::class,
            'data' => 'array',
            'group_count' => 'integer',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    public function markRead(): bool
    {
        if ($this->read_at !== null) {
            return false;
        }

        // Deliberately a mass update: saving the model would touch `updated_at`
        // and reshuffle the inbox, which is ordered by last activity.
        return static::query()
            ->whereKey($this->getKey())
            ->whereNull('read_at')
            ->update(['read_at' => now()]) > 0;
    }
}
