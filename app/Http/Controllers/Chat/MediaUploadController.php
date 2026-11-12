<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaUploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        @set_time_limit(0);

        $maxFileKb = (int) config('media.max_chat_file_kb', 2 * 1024 * 1024);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:'.$maxFileKb, config('media.mimes_rule')],
            'media_kind' => ['nullable', 'in:media,audio,voice'],
        ]);

        $file = $validated['file'];
        $kind = $validated['media_kind'] ?? 'media';

        $path = $file->store($kind === 'voice' ? 'chat/voice' : 'chat/media', 'public');

        return response()->json([
            'data' => [
                'media_path' => $path,
                'media_url' => asset('storage/'.ltrim($path, '/')),
                'media_type' => $file->getMimeType(),
                'media_kind' => $kind,
                'size' => $file->getSize(),
            ],
        ]);
    }
}
