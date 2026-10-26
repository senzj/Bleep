<input type="checkbox" id="warn_modal" class="modal-toggle" />

<div class="modal">
    <div class="modal-box max-w-md p-6 relative rounded-xl">
        <label for="warn_modal" class="btn btn-sm btn-circle absolute right-3 top-3">✕</label>

        <div class="flex items-center gap-2 mb-4">
            <i data-lucide="triangle-alert" class="w-6 h-6 text-warning"></i>
            <h3 class="font-bold text-xl">Warn User</h3>
        </div>

        <form id="warn-form" class="space-y-5">
            @csrf
            <input type="hidden" id="warn_report_id" name="report_id" />
            <input type="hidden" id="warn_user_id" name="user_id" />

            <div class="space-y-1">
                <label class="font-semibold text-sm" for="warn_reason">Reason</label>
                <select id="warn_reason" name="reason" class="select select-bordered w-full" required>
                    @foreach(config('moderation.warning_reasons', []) as $reason)
                        <option value="{{ $reason }}">{{ ucfirst($reason) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="space-y-1">
                <label class="font-semibold text-sm" for="warn_notes">Notes (Optional)</label>
                <textarea
                    id="warn_notes"
                    name="notes"
                    class="textarea textarea-bordered h-20 leading-relaxed"
                    maxlength="500"
                    placeholder="Private note for other moderators."></textarea>
                <div class="text-right text-xs opacity-60" id="warn_notes_counter">0 / 500</div>
            </div>

            <p class="text-xs opacity-60 leading-relaxed">
                Warnings stay active for {{ (int) config('moderation.warning_expiry_days') }} days.
                @if((int) config('moderation.auto_ban_at_strikes') > 0)
                    {{ (int) config('moderation.auto_ban_at_strikes') }} active strikes trigger an automatic ban.
                @else
                    Automatic bans are currently disabled.
                @endif
            </p>

            <div class="modal-action mt-6">
                <label for="warn_modal" class="btn btn-ghost">Cancel</label>
                <button type="submit" class="btn btn-warning gap-2">
                    <i data-lucide="triangle-alert" class="w-4 h-4"></i>
                    Issue Warning
                </button>
            </div>
        </form>
    </div>
</div>
