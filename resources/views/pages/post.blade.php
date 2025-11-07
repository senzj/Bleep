@vite([
        'resources/js/bleep/posts/posts',
        'resources/js/bleep/posts/likes',
        'resources/js/bleep/posts/comments',
        'resources/js/bleep/modals/posts/edit',
    ])
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
                                            data-user-email="{{ auth()->user()->email }}"
                                            style="background-image: url('https://avatars.laravel.cloud/{{ auth()->user()->email }}');">
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
                @foreach($bleep->comments->sortByDesc('created_at') as $comment)
                    @php
                        $isAnon = (bool) $comment->is_anonymous;
                        $displayName = $isAnon
                            ? $bleep->anonymousDisplayNameFor($viewerSeed)
                            : ($comment->user->dname ?? 'Unknown');
                        $username = $isAnon ? '@anonymous' : ('@' . ($comment->user->username ?? 'unknown'));
                    @endphp

                    <div class="p-4 bg-base-100 rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200" data-comment-id="{{ $comment->id }}">
                        <div class="flex items-start gap-3">
                            {{-- avatar (anonymous or user) --}}
                            @if(! $isAnon && $comment->user)
                                <div class="avatar">
                                    <div class="size-10 rounded-full overflow-hidden">
                                        <img src="https://avatars.laravel.cloud/{{ urlencode($comment->user->email) }}" alt="{{ $displayName }}">
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
                                {{-- Header: Name, Username, Time, Actions --}}
                                <div class="flex items-start justify-between gap-2 mb-2">
                                    <div class="flex flex-col min-w-0">
                                        <span class="font-semibold text-sm truncate">{{ $displayName }}</span>
                                        <span class="text-xs text-gray-500 truncate">{{ $username }}</span>
                                    </div>

                                    {{-- Time & Actions --}}
                                    <div class="flex items-center gap-2 shrink-0">
                                        {{-- Date and time --}}
                                        <div class="text-right">
                                            <div class="text-xs text-base-content/50 whitespace-nowrap">
                                                {{ $comment->created_at->timezone(Auth::user()->timezone ?? 'UTC')->format('M j, Y \| g:i:s A') }}
                                            </div>
                                            <div class="text-xs text-base-content/50 whitespace-nowrap">
                                                {{ $comment->created_at->diffForHumans() }}
                                            </div>
                                        </div>

                                        {{-- Action Dropdown --}}
                                        <div class="dropdown dropdown-end">
                                            <button tabindex="0" class="btn btn-ghost btn-xs btn-circle hover:bg-base-300" title="More options">
                                                <i data-lucide="more-vertical" class="w-4 h-4"></i>
                                            </button>

                                            <ul tabindex="0" class="dropdown-content z-10 shadow-lg bg-base-100 rounded-xl w-48 border border-base-200 p-2 space-y-1">
                                                {{-- Edit Comment --}}
                                                @can('update', $comment)
                                                    <li>
                                                        <button type="button"
                                                            class="cursor-pointer flex items-center gap-2 w-full px-3 py-2 text-sm text-gray-700 rounded-md hover:bg-base-200 transition edit-comment-btn"
                                                            data-comment-id="{{ $comment->id }}"
                                                            data-bleep-id="{{ $bleep->id }}"
                                                            data-comment-message="{{ htmlspecialchars($comment->message, ENT_QUOTES) }}"
                                                            data-is-anonymous="{{ $comment->is_anonymous ? '1' : '0' }}"
                                                            title="Edit this comment">
                                                            <i data-lucide="pencil" class="w-4 h-4"></i>
                                                            <span>Edit</span>
                                                        </button>
                                                    </li>
                                                @endcan

                                                {{-- Delete Comment --}}
                                                @can('delete', $comment)
                                                    <li>
                                                        <button type="button"
                                                            class="cursor-pointer flex items-center gap-2 w-full px-3 py-2 text-sm text-red-600 rounded-md hover:bg-red-50 transition delete-comment-btn"
                                                            data-comment-id="{{ $comment->id }}"
                                                            data-bleep-id="{{ $bleep->id }}"
                                                            title="Delete this comment">
                                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                            <span>Delete</span>
                                                        </button>
                                                    </li>
                                                @endcan

                                                {{-- Report Comment --}}
                                                <li>
                                                    <button type="button"
                                                        class="cursor-pointer flex items-center gap-2 w-full px-3 py-2 text-sm text-orange-500 rounded-md hover:bg-orange-50 transition report-comment-btn"
                                                        data-comment-id="{{ $comment->id }}"
                                                        data-bleep-id="{{ $bleep->id }}"
                                                        title="Report this comment">
                                                        <i data-lucide="flag" class="w-4 h-4"></i>
                                                        <span>Report</span>
                                                    </button>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                {{-- Comment Message --}}
                                <p class="text-sm break-words leading-snug text-base-content/90">
                                    {{ $comment->message }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach

                @if($bleep->comments->isEmpty())
                    <div class="text-center text-sm text-gray-500 py-6">
                        <i data-lucide="message-circle-off" class="w-5 h-5 inline-block mr-2"></i>
                        No comments yet. Be the first to comment.
                    </div>
                @endif
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

</x-layout>
