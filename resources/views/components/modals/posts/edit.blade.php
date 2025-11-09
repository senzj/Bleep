@vite([
    'resources/js/bleep/modals/posts/edit.js',
])

<div id="edit-bleep-modal" class="hidden fixed inset-0 z-50 items-center justify-center">
    <div id="edit-bleep-modal-overlay" class="absolute inset-0 bg-black/40"></div>

    <div class="relative max-w-2xl w-full mx-auto bg-base-100 rounded-2xl shadow-2xl border border-base-200 overflow-hidden p-6">
        <div id="edit-bleep-modal-content" class="min-h-5">
            {{-- title --}}
            <h2 class="text-xl font-semibold mb-4">
                <i data-lucide="edit-3" class="w-5 h-5 inline-block mr-2"></i>
                Edit Bleep
            </h2>

            {{-- Generic form — JS will set action, message and anonymous state --}}
            <form id="edit-bleep-form" method="POST" action="#" data-bleep-id="">
                @csrf
                @method('PUT')

                <div class="form-control w-full">
                    <textarea
                        name="message"
                        class="textarea textarea-bordered w-full resize-none"
                        rows="4"
                        maxlength="255"
                        required
                    ></textarea>
                </div>

                <div class="mt-3 flex items-center justify-between">
                    <label class="cursor-pointer label flex items-center">
                        <span class="label-text mr-2">Post anonymously</span>
                        <input id="edit-is-anonymous" type="checkbox" name="is_anonymous" value="1" class="toggle toggle-primary" />
                        <div id="edit-toggle-indicator" class="ml-2 w-7 h-7 rounded-full transition-all duration-200 flex items-center justify-center overflow-hidden" aria-hidden="true"></div>
                    </label>

                    <div class="flex gap-2">
                        <button type="button" id="cancel-edit-bleep" class="btn btn-ghost btn-sm">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm" id="submit-edit-bleep">Update</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
