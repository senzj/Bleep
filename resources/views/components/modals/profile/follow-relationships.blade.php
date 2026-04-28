<div id="follow-relationships-modal" class="hidden fixed inset-0 z-50 items-center justify-center px-4">
    <div id="follow-relationships-modal-overlay" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>

    <div class="relative z-10 w-full max-w-2xl rounded-2xl border border-base-300 bg-base-100 shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between gap-2 border-b border-base-300 px-5 py-2">
            <div class="min-w-0 flex items-center">
                <h3 class="text-lg font-semibold" data-relationship-modal-title>Followers</h3>
            </div>

            <button type="button" class="btn btn-ghost btn-sm btn-circle" data-relationship-modal-close aria-label="Close modal">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <div class="px-5 py-4 space-y-4">
            <label class="form-control">
                <div class="relative">
                    <input
                        type="text"
                        class="input input-bordered w-full pr-10"
                        placeholder="Search"
                        data-relationship-modal-search
                    />
                    <button
                        type="button"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-base-content/50 hover:text-base-content hidden"
                        data-relationship-modal-clear
                        aria-label="Clear search"
                    >
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
            </label>

            <div data-relationship-modal-loading class="hidden flex items-center justify-center py-10 text-base-content/60">
                <span class="loading loading-spinner loading-md"></span>
            </div>

            <div data-relationship-modal-results class="space-y-1 max-h-[60vh] overflow-y-auto pr-1"></div>
        </div>
    </div>
</div>
