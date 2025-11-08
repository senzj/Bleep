<div id="floating-comments-modal" class="hidden fixed z-50 bg-base-100 rounded-2xl shadow-2xl border border-base-200 flex flex-col overflow-hidden transition-all duration-300 ease-out">
    {{-- Sticky Header --}}
    <div id="floating-comments-header" class="sticky top-0 z-10 flex items-center justify-between px-4 py-3 border-b border-base-200 bg-base-100/95 backdrop-blur-sm shrink-0">
        <h2 class="text-lg font-semibold flex items-center gap-2">
            <i data-lucide="message-circle-more" class="w-5 h-5"></i>
            Comments
        </h2>
        <button id="close-comments-btn" class="btn btn-ghost btn-sm btn-circle hover:bg-base-300 dark:hover:bg-base-200">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    </div>

    {{-- Scrollable Content --}}
    <div id="floating-comments-scroll" class="flex-1 overflow-y-auto px-4 py-3 space-y-3 bg-gray-200/80">
        @if($bleep && $bleep->comments)
            @forelse($bleep->comments->sortByDesc('created_at') as $comment)
                <x-subcomponents.comments.commentcard :comment="$comment" :bleep="$bleep" />
            @empty
                <div class="flex flex-col items-center justify-center py-10 text-base-content/60">
                    <i data-lucide="message-circle-off" class="w-8 h-8 mb-3"></i>
                    <p class="text-sm font-semibold">No comments yet</p>
                    <p class="text-xs">Be the first to share your thoughts.</p>
                </div>
            @endforelse
        @else
            <div class="flex flex-col items-center justify-center py-10 text-base-content/60">
                <i data-lucide="message-circle-off" class="w-8 h-8 mb-3"></i>
                <p class="text-sm font-semibold">No comments yet</p>
                <p class="text-xs">Be the first to share your thoughts.</p>
            </div>
        @endif
    </div>

    {{-- Sticky Input Footer --}}
    @auth
        <div class="sticky bottom-0 z-10 bg-base-100 p-6 shrink-0">
            <form id="floating-comment-form" class="flex items-end gap-3" data-bleep-id="">
                @csrf
                <div class="flex-1">
                    <textarea
                        name="message"
                        rows="1"
                        data-min-height="32"
                        class="textarea textarea-bordered w-full resize-none text-sm leading-snug min-h-9 max-h-20 rounded-xl"
                        placeholder="Write a comment..."
                        required
                    ></textarea>
                </div>

                {{-- Toggle anonymous --}}
                <div class="flex items-end gap-2 shrink-0">
                    <label class="relative inline-flex cursor-pointer">
                        <input type="checkbox" id="comment-anonymous-toggle" name="is_anonymous" value="1" class="peer sr-only">
                        <div class="w-15 h-9 bg-base-300 peer-checked:bg-base-300 rounded-full peer-focus:ring-2 peer-focus:ring-primary transition-all border border-gray-300"></div>
                        <div id="toggle-indicator"
                            class="absolute top-1 left-1 size-7 rounded-full transition-all duration-300 peer-checked:left-7 bg-cover bg-center flex items-center justify-center"
                            data-user-email="{{ Auth::user()->email }}"
                            style="background-image: url('https://avatars.laravel.cloud/{{ Auth::user()->email }}');">
                        </div>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-base btn-circle self-end shrink-0">
                    <i data-lucide="send" class="w-5 h-5"></i>
                </button>
            </form>
        </div>
    @else
        <div class="sticky bottom-0 z-10 border-t border-base-200 bg-base-100/95 backdrop-blur-sm p-4 text-center text-sm text-base-content/60 shrink-0">
            <a href="/login" class="link link-primary">Login</a> to comment
        </div>
    @endauth
</div>
