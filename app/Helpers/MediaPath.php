<?php

namespace App\Helpers;

/**
 * Path checks shared by the media streaming and image resizing routes.
 */
class MediaPath
{
    /**
     * Reject paths that could escape the public disk before handing them to
     * Flysystem. Backslashes are normalised because Windows clients may send
     * them and the framework's normaliser rejects them differently.
     */
    public static function isSafe(string $path): bool
    {
        if ($path === '' || str_contains($path, "\0")) {
            return false;
        }

        foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
            if ($segment === '..') {
                return false;
            }
        }

        return true;
    }
}
