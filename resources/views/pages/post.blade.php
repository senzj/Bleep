<x-layout>
    {{-- Store user email for avatar display --}}
    @auth
        <meta name="user-email" content="{{ Auth::User()->email }}">
    @endauth

    <x-slot:title>Bleep Post</x-slot:title>

    @php
        $viewerSeed = Auth::check() ? Auth::id() : request()->session()->getId();
        $displayName = $bleep->is_anonymous
            ? $bleep->anonymousDisplayNameFor($viewerSeed)
            : ($bleep->user->dname ?? 'Unknown');

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

    <div class="max-w-3xl mx-auto my-8 ">
        <a href="/" class="text-sm link link-ghost mb-4 inline-block">&larr; Back</a>

        {{-- Bleep Post --}}
        <div class="space-y-4">
            {{-- bleep component --}}
            <x-bleep :bleep="$bleep" :show-comments-button="false" />
        </div>

        {{-- Comments Section --}}
        <div class="bg-base-100/70 rounded-lg shadow-md p-6">
            {{-- Comments input (textarea) + anonymity toggle + send button --}}
            @auth
                <div class="mt-1">
                    <form action="/bleeps/comments/{{ $bleep->id }}/post" method="POST" class="flex flex-col gap-3">
                        @csrf

                        <div class="flex gap-3">
                            <textarea name="message"
                                    required
                                    maxlength="255"
                                    rows="3"
                                    class="textarea textarea-bordered w-full resize-none"
                                    placeholder="Write a comment..."></textarea>

                            <div class="flex flex-col items-end gap-2">

                                <div class="flex items-end gap-2 shrink-0">
                                    <label class="relative inline-flex cursor-pointer">
                                        <input type="checkbox" id="comment-anonymous-toggle" name="is_anonymous" value="1" class="peer sr-only">
                                        <div class="w-18 h-9 bg-base-100 peer-checked:bg-base-300 rounded-full peer-focus:ring-2 peer-focus:ring-primary transition-all border border-gray-300"></div>
                                        <div id="toggle-indicator"
                                            class="absolute top-1 left-1 size-7 rounded-full transition-all duration-300 peer-checked:left-10 bg-cover bg-center flex items-center justify-center"
                                            data-profile-url="{{ $UserAvatarUrl ?? '' }}"
                                            data-user-avatar="{{ $UserAvatarUrl ?? '' }}"
                                            style="background-image: url('{{ $UserAvatarUrl ?? asset('images/avatar/default.jpg') }}');">
                                         </div>
                                    </label>
                                </div>

                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i data-lucide="send" class="w-3 h-3 inline-block"></i>
                                    Send
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            @else
                <div class="mt-1 text-center text-sm text-gray-500 rounded-lg p-4 shadow-md bg-base-100">
                    <i data-lucide="message-circle-more" class="w-4 h-4 inline-block mr-1"></i>
                    <a href="/login" class="link link-primary">Log in</a> to post a comment.
                </div>
            @endauth

            {{-- Comments display --}}
            <div class="mt-6 space-y-4">
                @forelse($bleep->comments->sortByDesc('created_at') as $comment)
                    <x-subcomponents.comments.commentcard :comment="$comment" :bleep="$bleep" />
                @empty
                    <div class="flex flex-col items-center justify-center py-10 text-base-content/60">
                        <i data-lucide="message-circle-off" class="w-8 h-8 mb-3"></i>
                        <p class="text-sm font-semibold">No comments yet</p>
                        <p class="text-xs">Be the first to share your thoughts.</p>
                    </div>
                @endforelse
            </div>
        </div>

    </div>

    {{-- Report Comment Modal --}}
    <div id="report-comment-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm close-report-comment-modal"></div>
        <div class="relative bg-base-100 rounded-2xl shadow-xl border border-base-300 p-6 max-w-md w-full mx-4 space-y-4 z-10">
            <h3 class="text-lg font-semibold">Report Comment</h3>
            <form method="POST" class="space-y-4">
                @csrf
                <div class="space-y-3">
                    <label class="flex items-center gap-2 cursor-pointer p-2 rounded hover:bg-base-200">
                        <input type="radio" name="reason" value="spam" class="radio" checked>
                        <span class="text-sm">Spam</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer p-2 rounded hover:bg-base-200">
                        <input type="radio" name="reason" value="offensive" class="radio">
                        <span class="text-sm">Offensive content</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer p-2 rounded hover:bg-base-200">
                        <input type="radio" name="reason" value="harassment" class="radio">
                        <span class="text-sm">Harassment</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer p-2 rounded hover:bg-base-200">
                        <input type="radio" name="reason" value="misinformation" class="radio">
                        <span class="text-sm">Misinformation</span>
                    </label>
                </div>

                <textarea name="description" maxlength="500" rows="3" class="textarea textarea-bordered w-full resize-none text-sm" placeholder="Tell us more (optional)..."></textarea>

                <div class="flex gap-2 justify-end">
                    <button type="button" class="btn btn-ghost btn-sm close-report-comment-modal">Cancel</button>
                    <button type="submit" class="btn btn-error btn-sm">Submit Report</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Ensure edit modal is available on the post page so the Edit button works --}}
    <x-modals.posts.edit />

</x-layout>
