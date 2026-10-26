<div id="share-modal" x-cloak class="hidden fixed inset-0 z-50 items-center justify-center" role="dialog" aria-modal="true" aria-labelledby="share-modal-title">

    <div id="share-modal-overlay" class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>

    <div class="relative max-w-md w-full mx-4 bg-base-100 rounded-2xl shadow-xl border border-base-300 p-5 z-10 space-y-4">
        <div class="flex items-start justify-between gap-3">
            <div class="space-y-1">
                <h3 id="share-modal-title" class="text-lg font-semibold">Share Post</h3>
                <p class="text-sm text-base-content/70">
                    @auth
                        Copy the link, or send it to a chat.
                    @else
                        Copy the link to share this post.
                    @endauth
                </p>
            </div>

            <button id="share-close-btn" type="button" class="btn btn-ghost btn-sm btn-circle" aria-label="Close">
                ✕
            </button>
        </div>

        @auth
            <div class="grid grid-cols-2 gap-1 rounded-xl bg-base-200 p-1" role="tablist" aria-label="Share options">
                <button id="share-tab-link" type="button" role="tab" aria-selected="true"
                    class="btn btn-sm border-0 shadow-none flex-1 btn-active">
                    Link
                </button>
                <button id="share-tab-chat" type="button" role="tab" aria-selected="false"
                    class="btn btn-sm border-0 shadow-none flex-1 gap-2">
                    <i data-lucide="message-circle" class="w-4 h-4"></i>
                    Send in chat
                </button>
            </div>
        @endauth

        {{-- Link panel --}}
        <div id="share-panel-link" class="space-y-4">
            <input id="share-url-input" type="hidden" />

            <div id="share-link-card" role="button" tabindex="0" class="flex items-center justify-between gap-4 rounded-xl border border-base-300 bg-base-200/70 px-4 py-3 cursor-pointer transition hover:border-primary hover:bg-primary/10 focus:outline-none focus:ring-2 focus:ring-primary/60">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="flex items-center justify-center rounded-full bg-primary/10 text-primary size-10">
                        <i data-lucide="link-2" class="w-4 h-4"></i>
                    </span>
                    <div class="min-w-0">
                        <span class="text-xs uppercase tracking-wide text-base-content/60">Share link</span>
                        <span id="share-url-display" class="mt-1 block text-sm font-medium text-base-content wrap-break-word">
                            This is a bleep link URL
                        </span>
                    </div>
                </div>

                <button id="share-copy-btn" type="button" class="btn btn-ghost btn-sm shrink-0" title="Copy link" aria-label="Copy link">
                    <i data-lucide="copy" class="w-4 h-4"></i>
                </button>
            </div>

            <button id="share-native-btn" type="button" class="btn btn-primary btn-sm w-full gap-2 hidden">
                <i data-lucide="share-2" class="w-4 h-4"></i>
                Share…
            </button>

            <div class="flex items-center justify-between gap-3 text-xs text-base-content/70">
                <span>Anyone with the link can view it.</span>
                <button id="share-cancel-btn" type="button" class="btn btn-ghost btn-sm">Close</button>
            </div>
        </div>

        {{-- Send-in-chat panel --}}
        @auth
            <div id="share-panel-chat" class="hidden space-y-3">
                <input id="share-chat-search" type="search" class="input input-bordered input-sm w-full"
                    placeholder="Search people and chats" autocomplete="off" aria-label="Search people and chats" />

                <div id="share-chat-list" class="max-h-72 overflow-y-auto space-y-1 pr-1">
                    <p class="px-2 py-3 text-xs text-base-content/50">Loading…</p>
                </div>

                <p class="text-xs text-base-content/60">
                    Sending in chat doesn't count as a link share.
                </p>
            </div>
        @endauth
    </div>
</div>
