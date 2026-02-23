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
     * @param UploadedFile $file
     * @param int $userId
     * @param string|null $oldPath Path to delete if exists
     * @return string Stored file path
     */
    public static function saveProfileImage(UploadedFile $file, int $userId, ?string $oldPath = null): string
    {
        // Delete old profile picture if exists
        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        // Generate filename: timestamp_random.ext
        $filename = time() . '_' . Str::random(8) . '.' . $file->extension();

        // Store in: profile/{user_id}/{filename}
        return $file->storeAs("user/{$userId}", $filename, 'public');
    }

    /**
     * Delete a profile picture from storage
     *
     * @param string|null $path
     * @return bool
     */
    public static function deleteProfileImage(?string $path): bool
    {
        if (!$path || !Storage::disk('public')->exists($path)) {
            return false;
        }

        return Storage::disk('public')->delete($path);
    }

    /**
     * Save a bleep media file (image, video, or audio)
     *
     * @param UploadedFile $file
     * @param int $bleepId
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

        // For audio files, preserve original name
        if ($type === 'audio') {
            $originalName = $file->getClientOriginalName();
            $filename = $originalName;
        } else {
            // For images and videos, use timestamp + random string
            $filename = time() . '_' . Str::random(8) . '.' . $file->extension();
        }

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
     *
     * @param string|null $path
     * @return bool
     */
    public static function deleteBleepMedia(?string $path): bool
    {
        if (!$path || !Storage::disk('public')->exists($path)) {
            return false;
        }

        return Storage::disk('public')->delete($path);
    }

    /**
     * Delete multiple bleep media files
     *
     * @param array $paths Array of file paths
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
     * @param UploadedFile $file
     * @param int $commentId
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

        // For audio files, preserve original name
        if ($type === 'audio') {
            $originalName = $file->getClientOriginalName();
            $filename = $originalName;
        } else {
            $filename = time() . '_' . Str::random(6) . '.' . $file->extension();
        }

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
     *
     * @param string|null $path
     * @return bool
     */
    public static function deleteCommentMedia(?string $path): bool
    {
        if (!$path || !Storage::disk('public')->exists($path)) {
            return false;
        }

        return Storage::disk('public')->delete($path);
    }
}
