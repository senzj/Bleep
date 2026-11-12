<div id="share-modal" x-cloak class="hidden fixed inset-0 z-50 items-center justify-center p-3" role="dialog" aria-modal="true" aria-labelledby="share-modal-title">

    <div id="share-modal-overlay" class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>

    <div class="relative z-10 flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-xl">
        {{-- Header --}}
        <div class="flex items-start justify-between gap-3 border-b border-base-200 px-5 py-4">
            <div class="space-y-1">
                <h3 id="share-modal-title" class="text-lg font-semibold">Share post</h3>
                <p class="text-sm text-base-content/70">
                    @auth
                        Copy the link, send it to people and group chats, or share it elsewhere.
                    @else
                        Copy the link or share it elsewhere.
                    @endauth
                </p>
            </div>

            <button id="share-close-btn" type="button" class="btn btn-ghost btn-sm btn-circle" aria-label="Close">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>

        <div class="min-h-0 flex-1 space-y-5 overflow-y-auto p-5">
            {{-- Link --}}
            <input id="share-url-input" type="hidden" />

            <div id="share-link-card" role="button" tabindex="0" class="flex items-center justify-between gap-3 rounded-xl border border-base-300 bg-base-200/70 px-4 py-3 cursor-pointer transition hover:border-primary hover:bg-primary/10 focus:outline-none focus:ring-2 focus:ring-primary/60">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <i data-lucide="link-2" class="h-4 w-4"></i>
                    </span>
                    <div class="min-w-0">
                        <span class="text-xs uppercase tracking-wide text-base-content/60">Share link</span>
                        <span id="share-url-display" class="mt-0.5 block truncate text-sm font-medium text-base-content">
                            This is a bleep link URL
                        </span>
                    </div>
                </div>

                <button id="share-copy-btn" type="button" class="btn btn-ghost btn-sm shrink-0 gap-2" title="Copy link" aria-label="Copy link">
                    <i data-lucide="copy" class="h-4 w-4"></i>
                    <span class="hidden sm:inline">Copy</span>
                </button>
            </div>

            @auth
                {{-- People and group chats --}}
                <div class="space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <h4 class="text-sm font-semibold">People and group chats</h4>
                        <span id="share-selected-count" class="text-xs text-base-content/50">0 selected</span>
                    </div>

                    <label class="input input-bordered input-sm flex w-full items-center gap-2">
                        <i data-lucide="search" class="h-4 w-4 text-base-content/50"></i>
                        <input id="share-chat-search" type="search" class="grow bg-transparent outline-none"
                            placeholder="Search people and chats" autocomplete="off" aria-label="Search people and chats" />
                    </label>

                    <div id="share-chat-list" class="flex items-center gap-2 overflow-x-auto pb-1 pr-1" role="listbox" aria-multiselectable="true" aria-label="People and group chats">
                        <p class="px-2 py-3 text-xs text-base-content/50">Loading your chats…</p>
                    </div>
                </div>
            @endauth

            {{-- Other platforms --}}
            <div class="space-y-2">
                <h4 class="text-sm font-semibold">Share to other platforms</h4>

                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @php
                        $platforms = [
                            ['key' => 'facebook', 'label' => 'Facebook', 'class' => 'bg-white text-[#1877F2] border-[#1877F2]/30 hover:bg-[#1877F2]/10'],
                            ['key' => 'messenger', 'label' => 'Messenger', 'class' => 'bg-white text-[#0084FF] border-[#0084FF]/30 hover:bg-[#0084FF]/10'],
                            ['key' => 'x', 'label' => 'X', 'class' => 'bg-neutral text-neutral-content border-neutral'],
                            ['key' => 'whatsapp', 'label' => 'WhatsApp', 'class' => 'bg-[#25D366] text-[#063d1e] border-[#25D366]'],
                            ['key' => 'telegram', 'label' => 'Telegram', 'class' => 'bg-[#229ED9] text-white border-[#229ED9]'],
                            ['key' => 'linkedin', 'label' => 'LinkedIn', 'class' => 'bg-[#0A66C2] text-white border-[#0A66C2]'],
                            ['key' => 'email', 'label' => 'Email', 'class' => 'bg-base-200 text-base-content border-base-300'],
                        ];
                    @endphp

                    @foreach ($platforms as $platform)
                        <button
                            type="button"
                            data-share-platform="{{ $platform['key'] }}"
                            class="btn btn-sm h-auto min-h-10 justify-start gap-2 border {{ $platform['class'] }}"
                            aria-label="Share on {{ $platform['label'] }}"
                        >
                            @switch($platform['key'])
                                @case('facebook')
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.77-3.89 1.09 0 2.23.2 2.23.2v2.45h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0 0 22 12Z"/></svg>
                                    @break
                                @case('messenger')
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C6.48 2 2 6.15 2 11.27c0 2.92 1.36 5.52 3.5 7.22V22l3.2-1.76c.99.27 2.04.42 3.3.42 5.52 0 10-4.15 10-9.27C22 6.15 17.52 2 12 2Zm1 12.5-2.5-2.7-4.87 2.7L11 9.24l2.55 2.7 4.82-2.7L13 14.5Z"/></svg>
                                    @break
                                @case('x')
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.9 2H22l-6.77 7.74L23.2 22h-6.23l-4.88-6.38L6.5 22H3.37l7.24-8.28L2.8 2h6.39l4.41 5.83L18.9 2Zm-1.09 18.1h1.72L8.27 3.8H6.42l11.39 16.3Z"/></svg>
                                    @break
                                @case('whatsapp')
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.04 2a9.9 9.9 0 0 0-8.4 15.14L2.1 22l4.98-1.5A9.9 9.9 0 1 0 12.04 2Zm0 18.1a8.2 8.2 0 0 1-4.18-1.14l-.3-.18-3.1.93.94-3.02-.2-.31a8.2 8.2 0 1 1 6.84 3.72Zm4.6-6.14c-.25-.13-1.49-.74-1.72-.82-.23-.08-.4-.13-.57.13-.17.25-.65.82-.8.99-.14.17-.29.19-.54.06-.25-.13-1.06-.39-2.02-1.25-.75-.67-1.25-1.5-1.4-1.75-.14-.25-.01-.39.11-.51.11-.11.25-.29.38-.44.12-.14.17-.25.25-.42.08-.17.04-.31-.02-.44-.06-.13-.57-1.37-.78-1.88-.2-.49-.41-.42-.57-.43h-.49c-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.1s.9 2.44 1.03 2.61c.13.17 1.78 2.72 4.32 3.82.6.26 1.08.42 1.45.54.61.19 1.16.16 1.6.1.49-.07 1.49-.61 1.7-1.2.21-.58.21-1.09.15-1.19-.06-.11-.23-.17-.48-.3Z"/></svg>
                                    @break
                                @case('telegram')
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.9 4.3 18.8 19c-.23 1.02-.84 1.27-1.7.79l-4.7-3.46-2.27 2.18c-.25.25-.46.46-.94.46l.33-4.78 8.7-7.86c.38-.34-.08-.53-.59-.19L7.04 12.8 2.4 11.35c-1.01-.32-1.03-1.01.21-1.5L20.3 2.7c.84-.31 1.58.2 1.3 1.6Z"/></svg>
                                    @break
                                @case('linkedin')
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5ZM3 9h4v12H3V9Zm6 0h3.83v1.64h.05c.53-1 1.83-2.06 3.77-2.06 4.03 0 4.78 2.65 4.78 6.1V21h-4v-5.5c0-1.31-.02-3-1.83-3-1.83 0-2.11 1.43-2.11 2.9V21H9V9Z"/></svg>
                                    @break
                                @case('email')
                                    <i data-lucide="mail" class="h-4 w-4"></i>
                                    @break
                            @endswitch
                            <span>{{ $platform['label'] }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <button id="share-native-btn" type="button" class="btn btn-primary btn-sm hidden w-full gap-2">
                <i data-lucide="share-2" class="h-4 w-4"></i>
                Share with device
            </button>
        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-end gap-2 border-t border-base-200 px-5 py-3">
            <button id="share-cancel-btn" type="button" class="btn btn-ghost btn-sm">Cancel</button>

            @auth
                <button id="share-submit-btn" type="button" class="btn btn-primary btn-sm gap-2" disabled aria-disabled="true">
                    <i data-lucide="send" class="h-4 w-4"></i>
                    <span>Share</span>
                </button>
            @endauth
        </div>
    </div>
</div>
