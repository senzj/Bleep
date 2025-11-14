<input type="checkbox" id="report_modal" class="modal-toggle" />
<div class="modal">
    <div class="modal-box relative max-w-md">
        {{-- Close button --}}
        <label for="report_modal" class="btn btn-sm btn-circle absolute right-2 top-2">✕</label>

        <h3 class="font-bold text-lg mb-4">Report Bleep</h3>

        <form id="report-form" class="space-y-4">
        @csrf
        <input type="hidden" id="report_bleep_id" name="bleep_id" />

        <div class="form-control">
            <label class="label" for="category">
            <span class="label-text">Category</span>
            </label>
            <select
            name="category"
            id="category"
            class="select select-bordered w-full"
            required
            >
            <option value="">Select a category</option>
            <option value="spam">Spam</option>
            <option value="harassment">Harassment</option>
            <option value="hate">Hate Speech</option>
            <option value="nsfw">NSFW Content</option>
            <option value="illegal">Illegal Content</option>
            <option value="other">Other</option>
            </select>
        </div>

        <div class="form-control">
            <label class="label" for="reason">
            <span class="label-text">Reason</span>
            </label>
            <textarea
            name="reason"
            id="reason"
            class="textarea textarea-bordered w-full"
            rows="4"
            maxlength="500"
            placeholder="Explain why you're reporting this..."
            required
            ></textarea>
            <p class="text-xs text-base-content/50 mt-1" id="reason-counter">0 / 500</p>
        </div>

        <div class="modal-action pt-2">
            <label for="report_modal" class="btn btn-ghost">Cancel</label>
            <button type="submit" class="btn btn-primary" id="submit-report-btn">
            Submit Report
            </button>
        </div>
        </form>
    </div>
</div>
