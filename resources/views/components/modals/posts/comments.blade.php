@php
    $UserAvatarUrl = null;
    if (Auth::check()) {
        $usr = Auth::user();
        $avatarPath = $usr->profile_picture ?? null;
        if ($avatarPath) {
            $UserAvatarUrl = asset('storage/' . $avatarPath);
        } else {
            $UserAvatarUrl = asset('images/avatar/default.jpg');
        }
    }
@endphp

{{-- Comments displayin for Bleep Post page --}}

<div id="floating-comments-modal" class="hidden fixed z-50 bg-base-300 rounded-2xl shadow-2xl border border-base-300/50 flex flex-col overflow-hidden transition-all duration-300 ease-out">
    {{-- Sticky Header --}}
    <div id="floating-comments-header" class="sticky top-0 z-10 flex items-center justify-between px-4 py-3 border-b border-base-200 bg-base-100/95 backdrop-blur-sm shrink-0">
        <h2 class="text-lg font-semibold flex items-center gap-2">
            <i data-lucide="message-circle-more" class="w-5 h-5"></i>
            {{ $bleep->user->username ?? 'User' }}'s Bleep
            Comments
        </h2>
        <button id="close-comments-btn" class="btn btn-ghost btn-sm btn-circle hover:bg-base-300 dark:hover:bg-base-200">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    </div>

    {{-- Comments content, populated by JavaScript (resources/js/bleep/posts/comment.js --}}
    <div id="floating-comments-scroll" class="flex-1 overflow-y-auto px-4 py-3 space-y-3 bg-base-300/80">
        {{-- Loading state will be injected by JavaScript --}}
        <div class="flex justify-center items-center py-10">
            <span class="loading loading-spinner loading-md"></span>
        </div>
    </div>

    {{-- Sticky Input Footer --}}
    @auth
        <div class="sticky bottom-0 z-10 bg-base-100 p-6 shrink-0">
            <div id="comment-media-preview" class="hidden mb-3">
                <div class="inline-flex max-w-44 w-full relative rounded-xl overflow-hidden bg-base-200 shadow">
                    <figure class="w-full"></figure>
                    <button type="button" id="comment-media-clear" class="absolute top-2 right-2 btn btn-xs btn-circle btn-error text-white">
                        <i data-lucide="x" class="w-3 h-3"></i>
                    </button>
                </div>
            </div>
            <form id="floating-comment-form" class="flex items-end gap-1" data-bleep-id="" enctype="multipart/form-data">
                @csrf
                {{-- Text Area --}}
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
                @if (env('ANONYMITY', true))
                    <div class="flex items-end gap-2 shrink-0">
                        <label class="relative inline-flex cursor-pointer">
                            <input type="checkbox" id="comment-anonymous-toggle" name="is_anonymous" value="1" class="peer sr-only">
                            <div class="w-15 h-9 bg-base-300 peer-checked:bg-base-300 rounded-full peer-focus:ring-2 peer-focus:ring-primary transition-all border border-gray-300"></div>
                            <div id="toggle-indicator"
                                class="absolute top-1 left-1 size-7 rounded-full transition-all duration-300 peer-checked:left-7 bg-cover bg-center flex items-center justify-center"
                                data-user-email="{{ Auth::user()->email ?? '' }}"
                                data-user-avatar="{{ $UserAvatarUrl }}"
                                style="background-image: url('{{ $UserAvatarUrl }}');">
                            </div>
                        </label>
                    </div>
                @endif

                {{-- Media Upload --}}
                <div class="relative flex items-center gap-2 rounded-full shadow-md border-base-100">
                    <button type="button" id="comment-media-trigger" class="btn btn-secondary btn-sm" aria-label="Attach media">
                        <i data-lucide="image" class="w-4 h-4"></i>
                        Media
                    </button>
                    <input type="file" id="comment-media-input" name="media" class="hidden" accept="image/*,video/mp4,video/quicktime,audio/mpeg,audio/mp3,audio/wav">
                </div>

                {{-- Submit Button --}}
                <button type="submit" class="btn btn-primary btn-sm">
                    <i data-lucide="send" class="w-4 h-4"></i>
                    Send
                </button>
            </form>
        </div>
    @else
        <div class="sticky bottom-0 z-10 border-t border-base-200 bg-base-100/95 backdrop-blur-sm p-4 text-center text-sm text-base-content/60 shrink-0">
            <a href="/login" class="link link-primary">Login</a> to comment
        </div>
    @endauth
</div>

{{-- Overlay for modals --}}
<div id="comments-overlay" class="hidden fixed inset-0 z-40"></div>
