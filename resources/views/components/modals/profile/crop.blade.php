@push('scripts')
    @vite([
        'resources/js/profile/profile-crop.js',
        'resources/css/profile-crop.css',
    ])
@endpush

{{-- Crop modal --}}
<input type="checkbox" id="cropper_modal" class="modal-toggle" />
<div class="modal">
    <div class="modal-box p-0 bg-base-100 w-full max-w-sm sm:max-w-md md:max-w-lg rounded-2xl shadow-2xl overflow-hidden">
        <div class="p-4 sm:p-6 flex flex-col">
            <h3 class="font-semibold text-lg mb-1">Crop Profile Picture</h3>
            <p class="text-xs sm:text-sm text-base-content/70 mb-4">Drag to move, scroll/pinch to zoom.</p>

            {{-- Cropper Container --}}
            <div id="cropper_container" class="relative w-full aspect-square min-h-[320px] sm:min-h-[380px] md:min-h-[420px] bg-base-300 rounded-xl overflow-hidden">
                <img id="cropper_image" src="" alt="Crop source" class="select-none opacity-0 pointer-events-none">
            </div>

            {{-- Action Buttons --}}
            <div class="flex flex-col sm:flex-row justify-between items-center gap-3 mt-5">
                <div class="text-[11px] sm:text-xs text-base-content/60">Result is saved as a square PNG (512x512).</div>
                <div class="flex gap-2">
                    <label for="cropper_modal" id="cancel_crop" class="btn btn-ghost btn-xs sm:btn-sm">Cancel</label>
                    <button id="crop_button" type="button" class="btn btn-primary btn-xs sm:btn-sm">Save</button>
                </div>
            </div>
        </div>
    </div>
</div>
