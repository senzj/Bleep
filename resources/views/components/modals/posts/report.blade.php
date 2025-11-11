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
