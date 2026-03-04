<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaUploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:30720', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/webm,audio/mpeg,audio/mp4,audio/wav,audio/webm,audio/ogg,application/pdf'],
            'media_kind' => ['nullable', 'in:media,audio,voice'],
        ]);

        $file = $validated['file'];
        $kind = $validated['media_kind'] ?? 'media';

        $path = $file->store($kind === 'voice' ? 'chat/voice' : 'chat/media', 'public');

        return response()->json([
            'data' => [
                'media_path' => $path,
                'media_url' => asset('storage/' . ltrim($path, '/')),
                'media_type' => $file->getMimeType(),
                'media_kind' => $kind,
                'size' => $file->getSize(),
            ],
        ]);
    }
}
