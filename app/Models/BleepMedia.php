<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BleepMedia extends Model
{
    protected $table = 'bleep_media';

    protected $fillable = [
        'bleep_id',
        'path',
        'type',
        'original_name',
        'mime_type',
        'size',
    ];

    public function bleep(): BelongsTo
    {
        return $this->belongsTo(Bleep::class);
    }

    public function isImage(): bool
    {
        return $this->type === 'image';
    }

    public function isVideo(): bool
    {
        return $this->type === 'video';
    }

    public function url(): string
    {
        return asset('storage/' . ltrim($this->path, '/'));
    }
}
