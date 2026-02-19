@if($path)
    <div data-comment-media-wrapper="{{ $commentId }}"
            src="{{ asset('storage/' . $path) }}"
            alt="Comment media"
            class="max-w-full max-h-64 rounded-lg object-cover mt-2">
    </div>
@endif
