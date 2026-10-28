<?php

namespace App\Models;

use App\Services\MediaUploadService;
use Database\Factories\BulletinPostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class BulletinPost extends Model
{
    /** @use HasFactory<BulletinPostFactory> */
    use HasFactory;

    /**
     * The internal id and visitor hash must never reach a public response.
     *
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'visitor_hash',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'anonymous_name',
        'content',
        'link_url',
        'image_url',
        'media_path',
        'media_mime',
        'media_kind',
        'width',
        'height',
        'paper_color',
        'paper_texture',
        'visitor_hash',
        'edited_at',
        'expires_at',
        'archived_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'edited_at' => 'datetime',
            'expires_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $post): void {
            $post->public_id ??= (string) Str::ulid();
            $post->expires_at ??= now()->addMonthsNoOverflow(8);
        });

        static::deleting(function (self $post): void {
            MediaUploadService::deleteBulletinMedia($post->media_path);
        });
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(BulletinReaction::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(BulletinReport::class);
    }

    /**
     * Notes that are allowed to appear on the public board.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNull('archived_at')
            ->where('expires_at', '>', now());
    }

    /**
     * Use the public ULID in URLs instead of the numeric id.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Visitors may correct their own note for one day after pinning it.
     */
    public function isEditable(): bool
    {
        return $this->created_at !== null
            && now()->lt($this->created_at->copy()->addDay());
    }
}
