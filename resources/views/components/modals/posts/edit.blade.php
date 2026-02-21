@push('scripts')
    @vite('resources/js/bleep/modals/posts/edit.js')
@endpush

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

                <div class="mt-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center justify-start gap-4">
                        {{-- Anonymous icon + dot-toggle --}}
                        @if (config('app.anonymity', true))
                            <div class="flex items-center gap-2">
                                <label for="edit-is-anonymous" id="edit-anon-icon" class="p-2 rounded-full bg-transparent cursor-pointer transition-colors duration-150" title="Post anonymously" aria-hidden="true">
                                    <i data-lucide="hat-glasses" class="w-5 h-5"></i>
                                </label>
                                <input id="edit-is-anonymous"
                                    name="is_anonymous"
                                    type="checkbox"
                                    value="1"
                                    class="toggle toggle-sm"
                                    {{ old('is_anonymous') ? 'checked' : '' }}>
                            </div>
                        @endif

                        {{-- NSFW icon + dot-toggle --}}
                        <div class="flex items-center gap-2">
                            <label for="edit-is-nsfw" id="edit-nsfw-icon" class="p-2 rounded-full bg-transparent cursor-pointer transition-colors duration-150" title="Mark as NSFW" aria-hidden="true">
                                <i data-lucide="eye-off" class="w-5 h-5"></i>
                            </label>
                            <input id="edit-is-nsfw"
                                   name="is_nsfw"
                                   type="checkbox"
                                   value="1"
                                   class="toggle toggle-sm"
                                   {{ old('is_nsfw') ? 'checked' : '' }}>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button type="button" id="cancel-edit-bleep" class="btn btn-ghost btn-outline btn-sm">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm" id="submit-edit-bleep">Update</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
