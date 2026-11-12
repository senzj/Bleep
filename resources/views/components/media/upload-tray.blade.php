@auth
    <div
        id="media-upload-tray"
        class="fixed bottom-4 right-4 z-[85] hidden w-[min(92vw,22rem)]"
        aria-live="polite"
        aria-label="Background uploads"
        data-media-upload-tray
    >
        <div class="overflow-hidden rounded-xl border border-base-300 bg-base-100 shadow-2xl">
            <div class="flex items-center gap-2 border-b border-base-300 px-3 py-2">
                <span class="flex size-7 items-center justify-center rounded-lg bg-primary/15 text-primary">
                    <i data-lucide="upload-cloud" class="h-4 w-4"></i>
                </span>
                <span class="flex-1 text-sm font-semibold">Uploads</span>
                <span class="badge badge-primary badge-sm hidden" data-tray-count></span>
                <button
                    type="button"
                    class="btn btn-ghost btn-xs rounded-lg"
                    data-tray-toggle
                    aria-label="Collapse uploads"
                >
                    <i data-lucide="chevron-down" class="h-4 w-4"></i>
                </button>
            </div>
            <div class="max-h-72 space-y-2 overflow-y-auto p-3" data-tray-body data-tray-list></div>
        </div>
    </div>
@endauth
