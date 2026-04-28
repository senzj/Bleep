@once
    @push('scripts')
        @vite('resources/js/bleep/modals/posts/edit.js')
    @endpush
@endonce

<div id="edit-bleep-modal" class="hidden fixed inset-0 z-50 items-center justify-center">
    <div id="edit-bleep-modal-overlay" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>

    <div class="relative max-w-2xl w-full mx-4 bg-base-100 rounded-2xl shadow-2xl border border-base-200 overflow-hidden">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 pt-5 pb-3 border-b border-base-200">
            <h2 class="text-lg font-semibold flex items-center gap-2">
                <i data-lucide="edit-3" class="w-5 h-5"></i>
                Edit Bleep
            </h2>
            <button type="button" id="cancel-edit-bleep-x" class="btn btn-ghost btn-sm btn-circle">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <div class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">

            {{-- Generic form — JS sets action, populates fields --}}
            <form id="edit-bleep-form" method="POST" action="#" data-bleep-id="" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                {{-- Message --}}
                <div class="form-control w-full">
                    <textarea
                        name="message"
                        id="edit-bleep-message"
                        class="textarea textarea-bordered w-full resize-none"
                        rows="3"
                        maxlength="255"
                        placeholder="What's on your mind?"
                    ></textarea>
                    <div class="text-xs text-base-content/40 text-right mt-1">
                        <span id="edit-char-count">0</span>/255
                    </div>
                </div>

                {{-- ── Current Media ─────────────────────────────────────────── --}}
                <div id="edit-current-media" class="hidden space-y-2">
                    <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wide">Current Media</p>
                    <div id="edit-current-media-grid" class="grid grid-cols-2 sm:grid-cols-4 gap-2"></div>
                </div>

                {{-- ── New Media Upload ──────────────────────────────────────── --}}
                <div id="edit-new-media-section" class="space-y-2">
                    <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wide">Add Media</p>

                    {{-- Drop zone --}}
                    <div id="edit-drop-zone"
                         class="relative flex flex-col items-center justify-center gap-2 border-2 border-dashed border-base-300 rounded-xl p-5 cursor-pointer hover:border-primary hover:bg-primary/5 transition-colors">
                        <i data-lucide="image-plus" class="w-7 h-7 text-base-content/30 pointer-events-none"></i>
                        <p class="text-sm text-base-content/50 pointer-events-none">
                            Drag & drop or <span class="text-primary font-medium">browse</span>
                            <span class="block text-xs text-center mt-0.5">Images/Videos (max 4) · Audio (max 1, alone)</span>
                        </p>
                        <input type="file"
                               id="edit-media-input"
                               name="media[]"
                               multiple
                               accept="image/*,video/mp4,video/webm,audio/mpeg,audio/wav,audio/mp3"
                               class="absolute inset-0 opacity-0 cursor-pointer w-full h-full" />
                    </div>

                    {{-- New files preview grid --}}
                    <div id="edit-new-media-grid" class="grid grid-cols-2 sm:grid-cols-4 gap-2"></div>
                </div>

                {{-- ── Upload progress ───────────────────────────────────────── --}}
                <div id="edit-upload-progress" class="hidden space-y-1">
                    <progress id="edit-upload-bar" class="progress progress-primary w-full" value="0" max="100"></progress>
                    <p id="edit-upload-label" class="text-xs text-base-content/50 text-right">0%</p>
                </div>

                {{-- ── Toggles + actions ─────────────────────────────────────── --}}
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-2 border-t border-base-200">
                    <div class="flex items-center gap-4">
                        {{-- Anonymous --}}
                        @if (config('app.anonymity', true))
                            <div class="flex items-center gap-2">
                                <label for="edit-is-anonymous"
                                       id="edit-anon-icon"
                                       class="p-2 rounded-full bg-transparent cursor-pointer transition-colors duration-150"
                                       title="Post anonymously">
                                    <i data-lucide="hat-glasses" class="w-5 h-5"></i>
                                </label>
                                <input id="edit-is-anonymous"
                                       name="is_anonymous"
                                       type="checkbox"
                                       value="1"
                                       class="toggle toggle-sm">
                            </div>
                        @endif

                        {{-- NSFW --}}
                        <div class="flex items-center gap-2">
                            <label for="edit-is-nsfw"
                                   id="edit-nsfw-icon"
                                   class="p-2 rounded-full bg-transparent cursor-pointer transition-colors duration-150"
                                   title="Mark as NSFW">
                                <i data-lucide="eye-off" class="w-5 h-5"></i>
                            </label>
                            <input id="edit-is-nsfw"
                                   name="is_nsfw"
                                   type="checkbox"
                                   value="1"
                                   class="toggle toggle-sm">
                        </div>
                    </div>

                    <div class="flex gap-2 justify-end">
                        <button type="button" id="cancel-edit-bleep" class="btn btn-ghost btn-outline btn-sm">
                            Cancel
                        </button>
                        <button type="submit" id="submit-edit-bleep" class="btn btn-primary btn-sm">
                            <i data-lucide="check" class="w-4 h-4"></i>
                            Update
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>
