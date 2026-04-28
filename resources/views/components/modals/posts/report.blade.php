@once
    @push('scripts')
        @vite(['resources/js/bleep/modals/posts/reports.js'])
    @endpush
@endonce

{{-- Unified Report Modal --}}
<dialog id="report-modal" class="modal modal-bottom sm:modal-middle">
    <div class="modal-box max-w-md relative overflow-visible">

        {{-- Close --}}
        <form method="dialog">
            <button class="btn btn-sm btn-circle btn-ghost absolute right-3 top-3">✕</button>
        </form>

        {{-- Header --}}
        <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 rounded-full bg-warning/15 flex items-center justify-center shrink-0">
                <i data-lucide="flag" class="w-5 h-5 text-warning"></i>
            </div>
            <div>
                <h3 class="font-bold text-lg leading-tight" id="report-modal-title">Report</h3>
                <p class="text-xs text-base-content/50" id="report-modal-subtitle">Help us keep the community safe</p>
            </div>
        </div>

        {{-- Hidden state --}}
        <input type="hidden" id="report-type" value="bleep" />
        <input type="hidden" id="report-target-id" value="" />

        {{-- Reported content preview --}}
        <div id="report-content-preview" class="hidden mb-4 p-3 bg-base-200 rounded-xl border border-base-300 text-sm text-base-content/70 italic line-clamp-3"></div>

        {{-- Category --}}
        <p class="text-sm font-medium mb-2">What's the issue?</p>
        <div class="grid grid-cols-2 gap-2 mb-4">
            @foreach([
                ['value' => 'spam',       'label' => 'Spam',        'icon' => 'zap',            'color' => 'text-yellow-500'],
                ['value' => 'harassment', 'label' => 'Harassment',  'icon' => 'user-x',         'color' => 'text-red-500'],
                ['value' => 'hate',       'label' => 'Hate Speech', 'icon' => 'shield-alert',   'color' => 'text-red-600'],
                ['value' => 'nsfw',       'label' => 'NSFW',        'icon' => 'eye-off',        'color' => 'text-orange-500'],
                ['value' => 'illegal',    'label' => 'Illegal',     'icon' => 'scale',          'color' => 'text-purple-500'],
                ['value' => 'other',      'label' => 'Other',       'icon' => 'more-horizontal','color' => 'text-base-content/50'],
            ] as $cat)
                <label class="flex items-center gap-2.5 p-3 rounded-xl border border-base-300 cursor-pointer
                              hover:border-warning hover:bg-warning/5 transition-all
                              has-checked:border-warning has-checked:bg-warning/10 has-checked:shadow-sm">
                    <input type="radio" name="report-category" value="{{ $cat['value'] }}" class="sr-only" />
                    <i data-lucide="{{ $cat['icon'] }}" class="w-4 h-4 shrink-0 {{ $cat['color'] }}"></i>
                    <span class="text-sm font-medium">{{ $cat['label'] }}</span>
                </label>
            @endforeach
        </div>

        {{-- Reason --}}
        <div class="mb-5">
            <p class="text-sm font-medium mb-1.5">
                Additional details
                <span class="text-base-content/40 font-normal text-xs">(optional)</span>
            </p>
            <textarea
                id="report-reason"
                class="textarea textarea-bordered w-full resize-none text-sm"
                rows="3"
                maxlength="500"
                placeholder="Tell us more about the issue..."></textarea>
            <div class="flex justify-end mt-1">
                <span id="report-char-count" class="text-xs text-base-content/40">0 / 500</span>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-2 justify-end">
            <form method="dialog">
                <button class="btn btn-ghost btn-sm">Cancel</button>
            </form>
            <button id="report-submit-btn" class="btn btn-warning btn-sm gap-2" disabled>
                <i data-lucide="flag" class="w-4 h-4"></i>
                Submit Report
            </button>
        </div>

        {{-- Loading overlay --}}
        <div id="report-loading" class="hidden absolute inset-0 rounded-2xl bg-base-100/80 backdrop-blur-sm flex items-center justify-center z-10">
            <span class="loading loading-spinner loading-md text-warning"></span>
        </div>

    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>
