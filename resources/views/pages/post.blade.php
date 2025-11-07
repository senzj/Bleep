@vite([
        'resources/js/bleep/posts/posts',
        'resources/js/bleep/posts/likes',
        'resources/js/bleep/posts/comments',
        'resources/js/bleep/modals/posts/edit',
    ])
<x-layout>
    <x-slot:title>Bleep Post</x-slot:title>

    @php
        $viewerSeed = auth()->check() ? auth()->id() : request()->session()->getId();
        $displayName = $bleep->is_anonymous
            ? $bleep->anonymousDisplayNameFor($viewerSeed)
            : ($bleep->user->dname ?? 'Unknown');
    @endphp

    <div class="max-w-3xl mx-auto my-8">
        <a href="/" class="text-sm link link-ghost mb-4 inline-block">&larr; Back</a>

        {{-- Single Bleep --}}
        <div class="space-y-4">
            {{-- re-use your bleep component --}}
            <x-bleep :bleep="$bleep" :show-comments-button="false" />
        </div>

        {{-- Comments input (textarea) + anonymity toggle + send button --}}
        @auth
            <div class="mt-6 border-t pt-6">
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
                                    <div class="w-15 h-9 bg-base-100 peer-checked:bg-base-300 rounded-full peer-focus:ring-2 peer-focus:ring-primary transition-all"></div>
                                    <div id="toggle-indicator"
                                        class="absolute top-1 left-1 size-7 rounded-full transition-all duration-300 peer-checked:left-7 bg-cover bg-center flex items-center justify-center"
                                        data-user-email="{{ auth()->user()->email }}"
                                        style="background-image: url('https://avatars.laravel.cloud/{{ auth()->user()->email }}');">
                                    </div>
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary btn-sm">Send</button>
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
            @foreach($bleep->comments->sortByDesc('created_at') as $comment)
                @php
                    $isAnon = (bool) $comment->is_anonymous;
                    $displayName = $isAnon
                        ? $bleep->anonymousDisplayNameFor($viewerSeed)
                        : ($comment->user->dname ?? 'Unknown');
                    $username = $isAnon ? '@anonymous' : ('@' . ($comment->user->username ?? 'unknown'));
                @endphp

                <div class="p-4 bg-base-100 rounded-lg shadow-sm">
                    <div class="flex items-start gap-3">
                        {{-- avatar (anonymous or user) --}}
                        @if(! $isAnon && $comment->user)
                            <div class="avatar">
                                <div class="size-10 rounded-full overflow-hidden">
                                    <img src="https://avatars.laravel.cloud/{{ urlencode($comment->user->email) }}" alt="">
                                </div>
                            </div>
                        @else
                            <div class="avatar">
                                <div class="size-10 rounded-full bg-base-300 flex items-center justify-center">
                                    <i data-lucide="hat-glasses" class="w-4 h-4 text-base-content/80"></i>
                                </div>
                            </div>
                        @endif

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <div class="truncate">
                                    <div class="font-semibold text-sm bleep-display-name">{{ $displayName }}</div>
                                    <div class="text-xs text-gray-500 bleep-username">{{ $username }}</div>
                                </div>
                                <div class="text-xs text-gray-400">
                                    {{ $comment->created_at->timezone(auth()->user()->timezone ?? 'UTC')->diffForHumans() }}
                                </div>
                            </div>

                            <p class="mt-2 text-sm break-words">{{ $comment->message }}</p>
                        </div>
                    </div>
                </div>
            @endforeach

            @if($bleep->comments->isEmpty())
                <div class="text-center text-sm text-gray-500 py-6">No comments yet. Be the first to comment.</div>
            @endif
        </div>
    </div>

    {{-- keep edit modal available on this page --}}
    <x-modals.bleeps.post.edit />
</x-layout>
