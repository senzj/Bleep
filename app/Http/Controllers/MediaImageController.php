<?php

namespace App\Http\Controllers;

use App\Helpers\MediaPath;
use GdImage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaImageController extends Controller
{
    /**
     * Longest edge, in pixels, served for each request size. Thumbnails feed
     * the grids; the viewer gets a larger (but still far smaller than the
     * original camera file) copy so zooming stays smooth.
     *
     * @var array<string, int>
     */
    private const SIZES = [
        'thumb' => 640,
        'view' => 1800,
    ];

    /**
     * Serve a downscaled copy of an image from the public disk.
     *
     * Resizing happens once and is cached on disk. Serving the original made
     * 2400x3000 uploads expensive to download, decode and zoom.
     */
    public function __invoke(string $size, string $path): BinaryFileResponse
    {
        $maxEdge = self::SIZES[$size] ?? null;

        if ($maxEdge === null || ! MediaPath::isSafe($path)) {
            abort(404);
        }

        $disk = Storage::disk('public');
        $root = realpath($disk->path(''));
        $source = realpath($disk->path($path));

        $isInsidePublicDisk = $root !== false
            && $source !== false
            && str_starts_with($source, $root.DIRECTORY_SEPARATOR)
            && is_file($source);

        abort_unless($isInsidePublicDisk, 404);

        $info = @getimagesize($source);

        abort_if($info === false, 404);

        [$width, $height, $type] = $info;

        // Animated GIFs are passed through untouched: resizing flattens the
        // animation. Images that already fit are served as-is.
        if ($type === IMAGETYPE_GIF || max($width, $height) <= $maxEdge) {
            return response()->file($source, $this->headers($source));
        }

        // Guard against enormous uploads exhausting GD memory; serving the
        // original is safer than a 500. The same applies when GD is missing.
        if ($width * $height > 50_000_000 || ! function_exists('imagecreatetruecolor')) {
            return response()->file($source, $this->headers($source));
        }

        $webp = function_exists('imagewebp');
        $cacheRelative = sprintf(
            'media-cache/%s/%s.%s',
            $size,
            sha1($path.'|'.(filemtime($source) ?: 0)),
            $webp ? 'webp' : 'jpg'
        );
        $cachedPath = $disk->path($cacheRelative);

        if (! is_file($cachedPath)) {
            $disk->makeDirectory(dirname($cacheRelative));

            $this->resize($source, $cachedPath, $maxEdge, $type, $webp);
        }

        abort_unless(is_file($cachedPath), 404);

        return response()->file($cachedPath, $this->headers($cachedPath));
    }

    private function resize(string $source, string $target, int $maxEdge, int $type, bool $webp): void
    {
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG => @imagecreatefrompng($source),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
            default => false,
        };

        abort_unless($image instanceof GdImage, 404);

        if ($type === IMAGETYPE_JPEG) {
            $image = $this->applyExifOrientation($image, $source);
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = $maxEdge / max($width, $height);

        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $resized = imagecreatetruecolor($targetWidth, $targetHeight);

        abort_unless($resized instanceof GdImage, 404);

        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        imagedestroy($image);

        // Write to a temporary file first so a concurrent request can never
        // serve a half-written image.
        $temporary = $target.'.'.getmypid().'.tmp';
        $written = $webp
            ? imagewebp($resized, $temporary, 82)
            : imagejpeg($resized, $temporary, 82);

        imagedestroy($resized);

        if (! $written) {
            @unlink($temporary);
            abort(404);
        }

        if (! @rename($temporary, $target)) {
            @unlink($temporary);
        }
    }

    /**
     * Bake the EXIF orientation into the pixels. Browsers honour the tag on the
     * original, but the generated copy no longer carries it.
     */
    private function applyExifOrientation(GdImage $image, string $source): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($source);
        $orientation = (int) ($exif['Orientation'] ?? 1);

        $angle = match ($orientation) {
            3, 4 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };

        if ($angle !== 0) {
            $rotated = imagerotate($image, $angle, 0);

            if ($rotated instanceof GdImage) {
                imagedestroy($image);
                $image = $rotated;
            }
        }

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, $orientation === 4 ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
        }

        return $image;
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $file): array
    {
        return [
            'Content-Type' => mime_content_type($file) ?: 'application/octet-stream',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }
}
