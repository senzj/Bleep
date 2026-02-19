@php
    $UserAvatarUrl = null;
    if (Auth::check()) {
        $usr = Auth::user();
        $avatarPath = $usr->profile_picture ?? null;
        if ($avatarPath) {
            $UserAvatarUrl = asset('storage/' . $avatarPath);
        } else {
            $UserAvatarUrl = asset('images/avatar/default.jpg');
        }
    }
@endphp

{{-- Edit Comment Modal --}}
<dialog id="edit-comment-modal" class="modal">
    <div class="modal-box max-w-2xl">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-lg flex items-center gap-2">
                <i data-lucide="pencil" class="w-5 h-5"></i>
                Edit Comment
            </h3>
            <button type="button" class="btn btn-ghost btn-sm btn-circle" onclick="edit_comment_modal.close()">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <form id="edit-comment-form" class="space-y-4" enctype="multipart/form-data">
            @csrf

            {{-- Message Textarea --}}
            <div>
                <textarea
                    id="edit-comment-message"
                    name="message"
                    class="textarea textarea-bordered w-full resize-none"
                    maxlength="500"
                    rows="3"
                    placeholder="Edit your comment..."
                    required
                ></textarea>
                <div class="text-xs text-base-content/50 mt-1 text-right">
                    <span id="edit-comment-char-count">0</span>/500
                </div>
            </div>

            {{-- Current Media Display --}}
            <div id="edit-comment-current-media" class="hidden">
                <div class="text-sm font-medium mb-2">Current Media</div>
                <div class="relative inline-block max-w-xs">
                    <div id="edit-comment-media-preview" class="rounded-lg overflow-hidden bg-base-200"></div>
                    <button
                        type="button"
                        id="edit-comment-remove-media"
                        class="absolute top-2 right-2 btn btn-error btn-xs btn-circle"
                        title="Remove media">
                        <i data-lucide="trash-2" class="w-3 h-3"></i>
                    </button>
                </div>
            </div>

            {{-- New Media Upload --}}
            <div id="edit-comment-new-media" class="hidden">
                <div class="text-sm font-medium mb-2">New Media</div>
                <div class="relative inline-block max-w-xs">
                    <div id="edit-comment-new-media-preview" class="rounded-lg overflow-hidden bg-base-200"></div>
                    <button
                        type="button"
                        id="edit-comment-clear-new-media"
                        class="absolute top-2 right-2 btn btn-error btn-xs btn-circle"
                        title="Clear">
                        <i data-lucide="x" class="w-3 h-3"></i>
                    </button>
                </div>
            </div>

            {{-- Media Upload Button --}}
            <div>
                <button
                    type="button"
                    id="edit-comment-media-trigger"
                    class="btn btn-secondary btn-sm">
                    <i data-lucide="image" class="w-4 h-4"></i>
                    <span id="edit-comment-media-btn-text">Add Media</span>
                </button>
                <input
                    type="file"
                    id="edit-comment-media-input"
                    name="media"
                    class="hidden"
                    accept="image/*">
            </div>

            {{-- Anonymous Toggle (Server-side controlled) --}}
            @if(env('ANONYMITY', true))
                <div class="flex items-center gap-2">
                    <label class="relative inline-flex cursor-pointer">
                        <input type="checkbox" id="edit-comment-anonymous" name="is_anonymous" value="1" class="peer sr-only">
                        <div class="w-14 h-8 bg-base-300 peer-checked:bg-base-300 rounded-full peer-focus:ring-2 peer-focus:ring-primary transition-all border border-gray-300"></div>
                        <div id="edit-comment-toggle-indicator"
                            class="absolute top-1 left-1 size-6 rounded-full transition-all duration-300 peer-checked:left-6 bg-cover bg-center flex items-center justify-center"
                            data-user-avatar="{{ $UserAvatarUrl }}"
                            style="background-image: url('{{ $UserAvatarUrl }}');">
                        </div>
                    </label>
                    <span class="text-xs text-base-content/60">Post anonymously</span>
                </div>
            @endif

            {{-- Actions --}}
            <div class="modal-action">
                <button type="button" class="btn btn-ghost" onclick="edit_comment_modal.close()">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    Update Comment
                </button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>
