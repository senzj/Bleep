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
     * @param string $username
     * @param string|null $oldPath Path to delete if exists
     * @return string Stored file path
     */
    public static function saveProfileImage(UploadedFile $file, string $username, ?string $oldPath = null): string
    {
        // Delete old profile picture if exists
        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        // Generate filename: username_profile_timestamp.ext
        $filename = "{$username}_profile_" . time() . '.' . $file->extension();

        // Store in: {username}/profile/{filename}
        return $file->storeAs("{$username}/profile", $filename, 'public');
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
     * @param string $username
     * @return array ['path' => string, 'type' => string, 'mime' => string]
     */
    public static function saveBleepMedia(UploadedFile $file, string $username): array
    {
        $mime = $file->getMimeType();
        $type = str_starts_with($mime, 'image/')
            ? 'image'
            : (str_starts_with($mime, 'video/')
                ? 'video'
                : (str_starts_with($mime, 'audio/') ? 'audio' : 'file'));

        // For audio files, preserve original name with username prefix
        if ($type === 'audio') {
            $originalName = $file->getClientOriginalName();
            $filename = "{$username}_{$originalName}";
        } else {
            // For images and videos, use timestamp + random string
            $filename = time() . '_' . Str::random(8) . '.' . $file->extension();
        }

        $path = $file->storeAs("{$username}/bleeps/{$type}", $filename, 'public');

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
     * @param string $username
     * @return array ['path' => string, 'type' => string, 'mime' => string]
     */
    public static function saveCommentMedia(UploadedFile $file, string $username): array
    {
        $mime = $file->getMimeType();
        $type = str_starts_with($mime, 'image/')
            ? 'image'
            : (str_starts_with($mime, 'video/')
                ? 'video'
                : (str_starts_with($mime, 'audio/') ? 'audio' : 'file'));

        // For audio files in comments, also preserve original name
        if ($type === 'audio') {
            $originalName = $file->getClientOriginalName();
            $filename = "{$username}_{$originalName}";
        } else {
            $filename = time() . '_' . Str::random(6) . '.' . $file->extension();
        }

        $path = $file->storeAs("{$username}/comments/{$type}", $filename, 'public');

        return [
            'path' => $path,
            'type' => $type,
            'mime' => $mime,
        ];
    }
}
