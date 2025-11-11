<x-layout>
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

    <div class="max-w-4xl mx-auto my-2">
        <a href="/" class="text-md link link-ghost mb-4 inline-block">
            <i data-lucide="arrow-left" class="w-5 h-5 inline-block"></i>
            Back
        </a>

        {{-- Bleep Post (showCommentsButton is false by default on post route) --}}
        <div class="space-y-4">
            <x-bleep :bleep="$bleep" />
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
                @php
                    $comments = $bleep->comments->sortByDesc('created_at');
                    $groups = $comments->groupBy(function($c) {
                        $tz = $c->user?->timezone ?? config('app.timezone', 'UTC');
                        return $c->created_at->copy()->setTimezone($tz)->format('Y-m-d') . '|' . $tz;
                    });
                @endphp

                @forelse($groups as $key => $group)
                    @php
                        [$date, $tz] = explode('|', $key);
                        $dt = \Carbon\Carbon::createFromFormat('Y-m-d', $date, $tz);
                        $showYear = $dt->year !== now()->year;
                        $label = $dt->format('F j') . ($showYear ? ', ' . $dt->year : '');
                    @endphp

                    <div class="text-sm text-base-content/60 font-medium mt-4 mb-2">
                        {{ $label }}
                    </div>

                    @foreach($group as $comment)
                        <x-subcomponents.comments.commentcard :comment="$comment" :bleep="$bleep" />
                    @endforeach
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
    <x-modals.posts.report />

    {{-- Edit Bleep Modal (needed for edit button) --}}
    <x-modals.posts.edit />
</x-layout>
