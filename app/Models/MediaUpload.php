<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaUpload extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETE = 'complete';

    public const STATUS_CLAIMED = 'claimed';

    protected $fillable = [
        'user_id',
        'upload_id',
        'context',
        'original_name',
        'mime_type',
        'size',
        'total_chunks',
        'received_chunks',
        'chunk_indexes',
        'temp_dir',
        'path',
        'status',
        'completed_at',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'total_chunks' => 'integer',
            'received_chunks' => 'integer',
            'chunk_indexes' => 'array',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isComplete(): bool
    {
        return $this->status === self::STATUS_COMPLETE;
    }

    /**
     * @return list<int>
     */
    public function chunkIndexes(): array
    {
        $indexes = array_map('intval', (array) ($this->chunk_indexes ?? []));
        sort($indexes);

        return $indexes;
    }

    public function registerChunk(int $index): void
    {
        $indexes = $this->chunkIndexes();

        if (! in_array($index, $indexes, true)) {
            $indexes[] = $index;
            sort($indexes);
        }

        $this->forceFill([
            'chunk_indexes' => $indexes,
            'received_chunks' => count($indexes),
        ])->save();
    }

    public function hasAllChunks(): bool
    {
        return $this->received_chunks >= $this->total_chunks;
    }
}
