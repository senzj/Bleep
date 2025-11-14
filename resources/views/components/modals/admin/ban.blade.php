<input type="checkbox" id="ban_modal" class="modal-toggle" />
<div class="modal">
    <div class="modal-box max-w-md p-6 relative rounded-xl">
        <label for="ban_modal" class="btn btn-sm btn-circle absolute right-3 top-3">✕</label>

        <div class="flex items-center gap-2 mb-4">
            <i data-lucide="user-x" class="w-6 h-6 text-error"></i>
            <h3 class="font-bold text-xl">Ban User</h3>
        </div>

        <form id="ban-form" class="space-y-6">
            @csrf
            <input type="hidden" id="ban_report_id" name="report_id" />
            <input type="hidden" id="ban_user_id" name="user_id" />
            <input type="hidden" id="ban_action_type" name="action_type" />

            <div class="space-y-1">
                <label class="font-semibold text-sm">Reason for Ban</label>
                <textarea
                    id="ban_reason"
                    name="reason"
                    class="textarea textarea-bordered h-24 leading-relaxed"
                    maxlength="500"
                    required
                    placeholder="Explain why this user is being banned..."></textarea>
                <div class="text-right text-xs opacity-60" id="ban_reason_counter">0 / 500</div>
            </div>

            <div class="divider my-2"></div>

            <div class="space-y-1">
                <label class="font-semibold text-sm">Ban Type</label>
                <select id="ban_duration_type" name="duration_type" class="select select-bordered w-full">
                    <option value="temporary" selected>Temporary Ban (choose date)</option>
                    <option value="permanent">Permanent Ban</option>
                </select>
            </div>

            <div class="space-y-2" id="ban_date_wrapper">
                <label class="font-semibold text-sm block">Ban Until (your local time)</label>
                <input type="datetime-local"
                       id="ban_until"
                       name="banned_until"
                       class="input input-bordered w-full"
                       required />
                <div class="flex flex-wrap gap-1">
                    <button type="button" class="btn btn-xs preset-btn" data-hours="6">6h</button>
                    <button type="button" class="btn btn-xs preset-btn" data-hours="24">24h</button>
                    <button type="button" class="btn btn-xs preset-btn" data-hours="72">3d</button>
                    <button type="button" class="btn btn-xs preset-btn" data-hours="168">7d</button>
                    <button type="button" class="btn btn-xs preset-btn" data-hours="720">30d</button>
                </div>
                <p class="text-xs opacity-60">Stored as UTC internally.</p>
            </div>

            <div class="divider my-2"></div>

            <div class="space-y-1">
                <label class="font-semibold text-sm">Report Notes (Optional)</label>
                <textarea
                    id="ban_notes"
                    name="notes"
                    class="textarea textarea-bordered h-20 leading-relaxed"
                    maxlength="500"
                    placeholder="Report notes for other moderators (private)."></textarea>
            </div>

            <div class="modal-action mt-6">
                <label for="ban_modal" class="btn btn-ghost">Cancel</label>
                <button type="submit" class="btn btn-error gap-2">
                    <i data-lucide="shield-ban" class="w-4 h-4"></i>
                    Ban User
                </button>
            </div>
        </form>
    </div>
</div>
