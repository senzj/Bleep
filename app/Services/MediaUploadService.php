<?php

namespace App\Services;

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
