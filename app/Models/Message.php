<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    const MAX_IMAGES = 10;

    const MAX_VIDEOS = 5;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
        'reply_to_id',
        'shared_bleep_id',
        'is_edited',
        'edited_at',
        'client_uuid',
    ];

    protected function casts(): array
    {
        return [
            'is_edited' => 'boolean',
            'edited_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    /**
     * Bleep attached to the message via "Send in chat".
     *
     * Kept with trashed rows so a deleted bleep still renders as a stable
     * "no longer available" card instead of vanishing from the bubble.
     */
    public function sharedBleep(): BelongsTo
    {
        return $this->belongsTo(Bleep::class, 'shared_bleep_id')->withTrashed();
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(MessageDelivery::class);
    }

    public function edits(): HasMany
    {
        return $this->hasMany(MessageEdit::class);
    }

    public function mediaItems(): HasMany
    {
        return $this->hasMany(MessageMedia::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    public function getImageCountAttribute(): int
    {
        return $this->mediaItems()
            ->where('media_type', 'like', 'image/%')
            ->count();
    }

    public function getVideoCountAttribute(): int
    {
        return $this->mediaItems()
            ->where('media_type', 'like', 'video/%')
            ->count();
    }

    public function canAddImage(): bool
    {
        return $this->getImageCountAttribute() < self::MAX_IMAGES;
    }

    public function canAddVideo(): bool
    {
        return $this->getVideoCountAttribute() < self::MAX_VIDEOS;
    }
}
