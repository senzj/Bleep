<?php

namespace App\Http\Controllers;

use App\Helpers\MediaPath;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaStreamController extends Controller
{
    /**
     * Stream a file from the public disk with HTTP range support.
     *
     * Range requests let the browser seek inside audio/video files without
     * downloading the entire file again. The route is registered outside the
     * `web` middleware group so a long stream never holds the session lock.
     */
    public function __invoke(Request $request, string $path): StreamedResponse|Response
    {
        // Flysystem throws on traversal segments instead of returning a path,
        // which would surface as a 500. Treat unsafe paths as missing media.
        if (! MediaPath::isSafe($path)) {
            abort(404);
        }

        $disk = Storage::disk('public');
        $root = realpath($disk->path(''));
        $fullPath = realpath($disk->path($path));

        $isInsidePublicDisk = $root !== false
            && $fullPath !== false
            && str_starts_with($fullPath, $root.DIRECTORY_SEPARATOR)
            && is_file($fullPath);

        abort_unless($isInsidePublicDisk, 404);

        $size = filesize($fullPath);
        $modifiedAt = filemtime($fullPath) ?: time();
        $etag = '"'.dechex($modifiedAt).'-'.dechex($size).'"';

        $headers = [
            'Content-Type' => mime_content_type($fullPath) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes(basename($fullPath)).'"',
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => $etag,
            'Last-Modified' => gmdate('D, d M Y H:i:s', $modifiedAt).' GMT',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response('', 304, $headers);
        }

        [$start, $end, $status] = $this->resolveRange($request->header('Range'), $size);

        if ($start === null) {
            return response('', 416, ['Content-Range' => "bytes */{$size}"]);
        }

        if ($status === 206) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }

        $headers['Content-Length'] = (string) ($end - $start + 1);

        if ($request->isMethod('HEAD')) {
            return response('', $status, $headers);
        }

        return response()->stream(function () use ($fullPath, $start, $end): void {
            $handle = fopen($fullPath, 'rb');

            if ($handle === false) {
                return;
            }

            fseek($handle, $start);

            $remaining = $end - $start + 1;
            $chunkSize = 1024 * 1024;

            while ($remaining > 0 && ! feof($handle)) {
                $chunk = fread($handle, min($chunkSize, $remaining));

                if ($chunk === false || $chunk === '') {
                    break;
                }

                echo $chunk;
                $remaining -= strlen($chunk);
                flush();
            }

            fclose($handle);
        }, $status, $headers);
    }

    /**
     * @return array{0: int|null, 1: int|null, 2: int}
     */
    private function resolveRange(?string $range, int $size): array
    {
        if ($size <= 0) {
            return [0, 0, 200];
        }

        $range = trim((string) $range);

        if ($range === '' || ! preg_match('/^bytes=(\d*)-(\d*)$/i', $range, $matches)) {
            return [0, $size - 1, 200];
        }

        $from = $matches[1];
        $to = $matches[2];

        if ($from === '' && $to === '') {
            return [null, null, 416];
        }

        if ($from === '') {
            $suffixLength = (int) $to;

            if ($suffixLength <= 0) {
                return [null, null, 416];
            }

            $start = max(0, $size - $suffixLength);
            $end = $size - 1;
        } else {
            $start = (int) $from;
            $end = $to === '' ? $size - 1 : min((int) $to, $size - 1);
        }

        if ($start >= $size || $start > $end) {
            return [null, null, 416];
        }

        return [$start, $end, 206];
    }
}
