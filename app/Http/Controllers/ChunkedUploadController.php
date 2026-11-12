<?php

namespace App\Http\Controllers;

use App\Models\MediaUpload;
use App\Services\MediaUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChunkedUploadController extends Controller
{
    /**
     * Start (or resume) a chunked upload.
     */
    public function init(Request $request): JsonResponse
    {
        @set_time_limit(0);

        $maxBytes = (int) config('media.max_bleep_file_kb', 2 * 1024 * 1024) * 1024;

        $validated = $request->validate([
            'original_name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1', 'max:'.$maxBytes],
            'mime_type' => ['nullable', 'string', 'max:150'],
            'total_chunks' => ['required', 'integer', 'min:1', 'max:100000'],
            'context' => ['nullable', 'in:bleep,chat,comment'],
        ]);

        $extension = strtolower((string) pathinfo($validated['original_name'], PATHINFO_EXTENSION));

        if (! in_array($extension, config('media.extensions', []), true)) {
            return response()->json([
                'message' => 'That file type is not supported.',
            ], 422);
        }

        $user = $request->user();

        $this->purgeExpiredUploads($user->id);

        $upload = MediaUpload::query()
            ->where('user_id', $user->id)
            ->where('original_name', $validated['original_name'])
            ->where('size', $validated['size'])
            ->where('context', $validated['context'] ?? 'bleep')
            ->where('status', MediaUpload::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $upload) {
            $upload = MediaUpload::create([
                'user_id' => $user->id,
                'upload_id' => (string) Str::uuid(),
                'context' => $validated['context'] ?? 'bleep',
                'original_name' => $validated['original_name'],
                'mime_type' => $validated['mime_type'] ?? null,
                'size' => $validated['size'],
                'total_chunks' => $validated['total_chunks'],
                'received_chunks' => 0,
                'chunk_indexes' => [],
                'temp_dir' => '',
                'status' => MediaUpload::STATUS_PENDING,
                'expires_at' => now()->addDay(),
            ]);

            $upload->forceFill([
                'temp_dir' => 'media-uploads/'.$upload->upload_id,
            ])->save();

            Storage::disk('local')->makeDirectory($upload->temp_dir.'/parts');
        }

        return response()->json([
            'upload_id' => $upload->upload_id,
            'received_chunks' => $upload->chunkIndexes(),
            'total_chunks' => $upload->total_chunks,
            'chunk_size_kb' => (int) config('media.chunk_size_kb', 8 * 1024),
            'status' => $upload->status,
        ]);
    }

    /**
     * Store a single chunk.
     */
    public function chunk(Request $request, MediaUpload $upload): JsonResponse
    {
        @set_time_limit(0);

        $this->authorizeUpload($request, $upload);

        abort_unless($upload->status === MediaUpload::STATUS_PENDING, 409, 'This upload is already complete.');

        $chunkSizeKb = (int) config('media.chunk_size_kb', 8 * 1024);

        $validated = $request->validate([
            'index' => ['required', 'integer', 'min:0', 'max:'.($upload->total_chunks - 1)],
            'chunk' => ['required', 'file', 'max:'.($chunkSizeKb + 256)],
        ]);

        $stored = $request->file('chunk')->storeAs(
            $upload->temp_dir.'/parts',
            sprintf('%06d.part', $validated['index']),
            'local'
        );

        if ($stored === false) {
            return response()->json(['message' => 'Could not store the uploaded chunk.'], 500);
        }

        // Chunks may arrive in parallel; lock the row so two requests cannot
        // overwrite each other's chunk index list.
        DB::transaction(function () use ($upload, $validated): void {
            $locked = MediaUpload::query()->lockForUpdate()->find($upload->id);
            $locked?->registerChunk((int) $validated['index']);
        });

        $upload->refresh();

        return response()->json([
            'upload_id' => $upload->upload_id,
            'received_chunks' => $upload->chunkIndexes(),
            'total_chunks' => $upload->total_chunks,
            'status' => $upload->status,
        ]);
    }

    /**
     * Combine every chunk into the final file.
     */
    public function complete(Request $request, MediaUpload $upload): JsonResponse
    {
        @set_time_limit(0);

        $this->authorizeUpload($request, $upload);

        if ($upload->status === MediaUpload::STATUS_COMPLETE) {
            return $this->statusPayload($upload);
        }

        abort_unless($upload->status === MediaUpload::STATUS_PENDING, 409, 'This upload is already complete.');

        if (! $upload->hasAllChunks()) {
            return response()->json([
                'message' => 'Some chunks are still missing.',
                'received_chunks' => $upload->chunkIndexes(),
                'total_chunks' => $upload->total_chunks,
            ], 409);
        }

        $disk = Storage::disk('local');
        $assembledPath = $upload->temp_dir.'/file';
        $absolutePath = $disk->path($assembledPath);

        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0775, true);
        }

        $output = fopen($absolutePath, 'wb');

        if ($output === false) {
            return response()->json(['message' => 'Could not assemble the upload.'], 500);
        }

        try {
            for ($index = 0; $index < $upload->total_chunks; $index++) {
                $partPath = $disk->path($upload->temp_dir.'/parts/'.sprintf('%06d.part', $index));

                if (! is_file($partPath)) {
                    fclose($output);

                    return response()->json([
                        'message' => "Chunk {$index} is missing.",
                        'received_chunks' => $upload->chunkIndexes(),
                        'total_chunks' => $upload->total_chunks,
                    ], 409);
                }

                $input = fopen($partPath, 'rb');

                if ($input === false) {
                    continue;
                }

                stream_copy_to_stream($input, $output);
                fclose($input);
            }
        } finally {
            fclose($output);
        }

        clearstatcache(true, $absolutePath);
        $assembledSize = filesize($absolutePath);

        if ($assembledSize !== $upload->size) {
            $disk->delete($assembledPath);
            $upload->forceFill([
                'status' => MediaUpload::STATUS_PENDING,
                'chunk_indexes' => [],
                'received_chunks' => 0,
            ])->save();

            return response()->json([
                'message' => 'The assembled upload did not match the expected size. Please retry.',
            ], 422);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($absolutePath) ?: $upload->mime_type;
        $type = MediaUploadService::mediaTypeFromMime((string) $mime);

        if ($type === 'file') {
            $disk->deleteDirectory($upload->temp_dir);

            return response()->json([
                'message' => 'Only image, video, or audio files are allowed.',
            ], 422);
        }

        $disk->deleteDirectory($upload->temp_dir.'/parts');

        $upload->forceFill([
            'mime_type' => $mime,
            'path' => $assembledPath,
            'status' => MediaUpload::STATUS_COMPLETE,
            'completed_at' => now(),
            'expires_at' => now()->addDay(),
        ])->save();

        return $this->statusPayload($upload);
    }

    /**
     * Report upload progress so a reloaded page can resume.
     */
    public function status(Request $request, MediaUpload $upload): JsonResponse
    {
        $this->authorizeUpload($request, $upload);

        return $this->statusPayload($upload);
    }

    /**
     * List the current user's uploads (used to restore the tray after reload).
     */
    public function index(Request $request): JsonResponse
    {
        $this->purgeExpiredUploads($request->user()->id);

        $uploads = MediaUpload::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', [MediaUpload::STATUS_PENDING, MediaUpload::STATUS_COMPLETE])
            ->where('expires_at', '>', now())
            ->latest('id')
            ->get()
            ->map(fn (MediaUpload $upload) => [
                'upload_id' => $upload->upload_id,
                'original_name' => $upload->original_name,
                'size' => $upload->size,
                'mime_type' => $upload->mime_type,
                'context' => $upload->context,
                'total_chunks' => $upload->total_chunks,
                'received_chunks' => $upload->chunkIndexes(),
                'status' => $upload->status,
                'completed_at' => optional($upload->completed_at)->toIso8601String(),
            ]);

        return response()->json(['uploads' => $uploads]);
    }

    /**
     * Discard a pending upload.
     */
    public function destroy(Request $request, MediaUpload $upload): JsonResponse
    {
        $this->authorizeUpload($request, $upload);

        Storage::disk('local')->deleteDirectory($upload->temp_dir);
        $upload->delete();

        return response()->json(['success' => true]);
    }

    private function authorizeUpload(Request $request, MediaUpload $upload): void
    {
        abort_unless($upload->user_id === $request->user()?->id, 404);
    }

    private function statusPayload(MediaUpload $upload): JsonResponse
    {
        return response()->json([
            'upload_id' => $upload->upload_id,
            'original_name' => $upload->original_name,
            'size' => $upload->size,
            'mime_type' => $upload->mime_type,
            'context' => $upload->context,
            'total_chunks' => $upload->total_chunks,
            'received_chunks' => $upload->chunkIndexes(),
            'status' => $upload->status,
            'completed_at' => optional($upload->completed_at)->toIso8601String(),
        ]);
    }

    private function purgeExpiredUploads(int $userId): void
    {
        MediaUpload::query()
            ->where('user_id', $userId)
            ->where('expires_at', '<', now())
            ->get()
            ->each(function (MediaUpload $upload): void {
                Storage::disk('local')->deleteDirectory($upload->temp_dir);
                $upload->delete();
            });
    }
}
