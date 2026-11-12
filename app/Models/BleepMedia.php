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
        'duration_seconds',
    ];

    protected $casts = [
        'duration_seconds' => 'integer',
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

    public function isAudio(): bool
    {
        return $this->type === 'audio';
    }

    public function url(): string
    {
        return asset('storage/'.ltrim($this->path, '/'));
    }

    /**
     * Grid-sized copy used for the inline thumbnails.
     */
    public function thumbnailUrl(): string
    {
        return route('media.image', ['size' => 'thumb', 'path' => $this->path]);
    }

    /**
     * Larger resized copy used by the media viewer. Zooming a 4 MB camera
     * original was the main source of viewer stutter.
     */
    public function viewerUrl(): string
    {
        return route('media.image', ['size' => 'view', 'path' => $this->path]);
    }

    /**
     * Uppercase file extension shown as a type tag in the bleep footer.
     */
    public function extension(): ?string
    {
        $name = $this->original_name ?: $this->path;
        $extension = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));

        return $extension !== '' ? $extension : null;
    }

    /**
     * Stored duration formatted as m:ss, or "0:00" when unknown.
     */
    public function durationLabel(): string
    {
        $seconds = max(0, (int) ($this->duration_seconds ?? 0));

        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
