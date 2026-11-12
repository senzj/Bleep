<?php

namespace App\Services;

use App\Models\MediaUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaUploadService
{
    /**
     * Save a profile picture for a user
     *
     * @param  string|null  $oldPath  Path to delete if exists
     * @return string Stored file path
     */
    public static function saveProfileImage(UploadedFile $file, int $userId, ?string $oldPath = null): string
    {
        // Delete old profile picture if exists
        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        // Generate filename: timestamp_random.ext
        $filename = self::randomFilename($file);

        // Store in: profile/{user_id}/{filename}
        return $file->storeAs("user/{$userId}", $filename, 'public');
    }

    /**
     * Build a safe, non-guessable filename using only the detected extension.
     */
    private static function randomFilename(UploadedFile $file, int $randomLength = 10): string
    {
        $extension = strtolower((string) $file->extension());

        if (! preg_match('/^[a-z0-9]{1,10}$/', $extension)) {
            $extension = 'bin';
        }

        return time().'_'.Str::random($randomLength).'.'.$extension;
    }

    /**
     * Delete a profile picture from storage
     */
    public static function deleteProfileImage(?string $path): bool
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return false;
        }

        return Storage::disk('public')->delete($path);
    }

    /**
     * Save a bleep media file (image, video, or audio)
     *
     * @return array ['path' => string, 'type' => string, 'mime' => string]
     */
    public static function saveBleepMedia(UploadedFile $file, int $bleepId): array
    {
        $mime = $file->getMimeType();
        $type = str_starts_with($mime, 'image/')
            ? 'image'
            : (str_starts_with($mime, 'video/')
                ? 'video'
                : (str_starts_with($mime, 'audio/') ? 'audio' : 'file'));

        // Always generate the filename server-side. Never trust the client's
        // original name, which can contain executable extensions or traversal.
        $filename = self::randomFilename($file);

        // Store in: bleep/{bleep_id}/{filename}
        $path = $file->storeAs("bleep/{$bleepId}", $filename, 'public');

        return [
            'path' => $path,
            'type' => $type,
            'mime' => $mime,
        ];
    }

    /**
     * Map a detected MIME type to an application media type.
     */
    public static function mediaTypeFromMime(string $mime): string
    {
        $mime = strtolower(trim($mime));

        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            default => 'file',
        };
    }

    /**
     * Move an assembled chunk upload into a bleep directory.
     *
     * @return array{path: string, type: string, mime: string, original_name: string, size: int}
     */
    public static function saveBleepMediaFromUpload(MediaUpload $upload, int $bleepId): array
    {
        return self::storeAssembledUpload($upload, "bleep/{$bleepId}");
    }

    /**
     * Move an assembled chunk upload into a comment directory.
     *
     * @return array{path: string, type: string, mime: string, original_name: string, size: int}
     */
    public static function saveCommentMediaFromUpload(MediaUpload $upload, int $commentId): array
    {
        return self::storeAssembledUpload($upload, "comments/{$commentId}");
    }

    /**
     * @return array{path: string, type: string, mime: string, original_name: string, size: int}
     */
    private static function storeAssembledUpload(MediaUpload $upload, string $directory): array
    {
        $source = $upload->path;

        if (! $source || ! Storage::disk('local')->exists($source)) {
            throw new \RuntimeException('The uploaded file is no longer available.');
        }

        $mime = (string) ($upload->mime_type ?: 'application/octet-stream');
        $type = self::mediaTypeFromMime($mime);

        if ($type === 'file') {
            throw new \InvalidArgumentException('Only image, video, or audio files are allowed.');
        }

        $extension = strtolower((string) pathinfo($upload->original_name, PATHINFO_EXTENSION));

        if (! preg_match('/^[a-z0-9]{1,10}$/', $extension)) {
            $extension = 'bin';
        }

        $filename = time().'_'.Str::random(10).'.'.$extension;
        $destination = trim($directory, '/').'/'.$filename;

        $stream = Storage::disk('local')->readStream($source);

        if ($stream === false) {
            throw new \RuntimeException('The uploaded file could not be read.');
        }

        try {
            Storage::disk('public')->writeStream($destination, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return [
            'path' => $destination,
            'type' => $type,
            'mime' => $mime,
            'original_name' => $upload->original_name,
            'size' => (int) $upload->size,
        ];
    }

    public static function deleteUploadTemp(MediaUpload $upload): void
    {
        if ($upload->temp_dir) {
            Storage::disk('local')->deleteDirectory($upload->temp_dir);
        }
    }

    /**
     * Delete a bleep media file from storage
     */
    public static function deleteBleepMedia(?string $path): bool
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return false;
        }

        return Storage::disk('public')->delete($path);
    }

    /**
     * Delete multiple bleep media files
     *
     * @param  array  $paths  Array of file paths
     * @return int Number of files deleted
     */
    public static function deleteBleepMediaBatch(array $paths): int
    {
        $deleted = 0;

        foreach ($paths as $path) {
            if (self::deleteBleepMedia($path)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Save a comment media file (image, video, or audio)
     *
     * @return array ['path' => string, 'type' => string, 'mime' => string]
     */
    public static function saveCommentMedia(UploadedFile $file, int $commentId): array
    {
        $mime = $file->getMimeType();
        $type = str_starts_with($mime, 'image/')
            ? 'image'
            : (str_starts_with($mime, 'video/')
                ? 'video'
                : (str_starts_with($mime, 'audio/') ? 'audio' : 'file'));

        if ($type === 'file') {
            throw new \InvalidArgumentException('Only image, video, or audio files are allowed for comment media.');
        }

        // Always generate the filename server-side. Never trust the client's
        // original name, which can contain executable extensions or traversal.
        $filename = self::randomFilename($file, 8);

        // Store in: comments/{comment_id}/{filename}
        $path = $file->storeAs("comments/{$commentId}", $filename, 'public');

        return [
            'path' => $path,
            'type' => $type,
            'mime' => $mime,
        ];
    }

    /**
     * Delete a comment media file from storage
     */
    public static function deleteCommentMedia(?string $path): bool
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return false;
        }

        return Storage::disk('public')->delete($path);
    }

    /**
     * Save a single public bulletin attachment (image, GIF, video, or audio).
     *
     * @return array{path: string, mime: string, kind: string}
     */
    public static function saveBulletinMedia(UploadedFile $file, string $publicId): array
    {
        $mime = (string) $file->getMimeType();
        $kind = str_starts_with($mime, 'image/')
            ? 'image'
            : (str_starts_with($mime, 'video/') ? 'video' : 'audio');

        $filename = self::randomFilename($file, 12);
        $path = $file->storeAs("bulletin/{$publicId}", $filename, 'public');

        return [
            'path' => $path,
            'mime' => $mime,
            'kind' => $kind,
        ];
    }

    /**
     * Delete a bulletin attachment from storage.
     */
    public static function deleteBulletinMedia(?string $path): bool
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return false;
        }

        return Storage::disk('public')->delete($path);
    }
}
