<?php

$extensions = [
    // Images
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif',
    // Video
    'mp4', 'mov', 'webm', 'mkv', 'm4v', 'avi', 'ogv', '3gp',
    // Audio
    'mp3', 'mpeg', 'wav', 'flac', 'aac', 'm4a', 'alac', 'ogg', 'oga', 'opus', 'aiff', 'aif', 'wma',
];

return [
    /*
    |--------------------------------------------------------------------------
    | Uploadable media extensions
    |--------------------------------------------------------------------------
    |
    | Kept in one place so bleeps, comments, chat and bulletins accept the same
    | set. `mimes` checks the client extension against the detected MIME type.
    |
    */
    'extensions' => $extensions,

    'mimes_rule' => 'mimes:'.implode(',', $extensions),

    'accept' => 'image/*,video/*,audio/*,.flac,.alac,.aac,.opus,.oga,.m4a,.aiff,.aif,.wma,.mkv,.mov,.m4v,.3gp',

    /*
    |--------------------------------------------------------------------------
    | Upload limits
    |--------------------------------------------------------------------------
    |
    | Values are in kilobytes because Laravel's `max` file rule uses KB. The
    | PHP engine also enforces `upload_max_filesize` and `post_max_size`; the
    | README explains those server settings.
    |
    */
    'max_bleep_file_kb' => (int) env('MEDIA_MAX_BLEEP_FILE_KB', 2 * 1024 * 1024),
    'max_chat_file_kb' => (int) env('MEDIA_MAX_CHAT_FILE_KB', 2 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Chunked uploads
    |--------------------------------------------------------------------------
    |
    | Files at or above this threshold use the resumable chunk uploader so a
    | page refresh does not lose the bytes that were already transferred.
    |
    */
    'chunked_upload_threshold_kb' => (int) env('MEDIA_CHUNKED_UPLOAD_THRESHOLD_KB', 8 * 1024),
    'chunk_size_kb' => (int) env('MEDIA_CHUNK_SIZE_KB', 8 * 1024),
];
