<div id="share-modal" class="hidden fixed inset-0 z-50 items-center justify-center">

    <div id="share-modal-overlay" class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>

    <div class="relative max-w-sm w-full mx-4 bg-base-100 rounded-2xl shadow-xl border border-base-300 p-5 z-10 space-y-4">
        <div class="space-y-1">
            <h3 class="text-lg font-semibold">Share Post</h3>
            <p class="text-sm text-base-content/70">Copy the link to share this post.</p>
        </div>

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

            <button id="share-copy-btn" type="button" class="btn btn-ghost btn-sm shrink-0" title="Copy link">
                <i data-lucide="copy" class="w-4 h-4"></i>
            </button>
        </div>

        <div class="flex items-center justify-between gap-3 text-xs text-base-content/70">
            <span>Sharing this post will create a link anyone can use to view it.</span>
            <button id="share-cancel-btn" type="button" class="btn btn-ghost btn-sm bg-gray-300">Cancel</button>
        </div>
    </div>
</div>
