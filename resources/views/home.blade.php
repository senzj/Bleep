@push('scripts')
    @vite([
        'resources/js/bleep/posts/post.js',
        'resources/js/bleep/modals/posts/edit.js',
        'resources/js/bleep/posts/infinitescroll.js',
    ])
@endpush

<x-layout>
    <x-slot:title>
        Bleep
    </x-slot:title>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {{-- Left panel --}}
        <div class="hidden lg:block lg:col-span-3" id="left-panel">
            {{-- Online mutuals --}}
        </div>

        {{-- Center panel --}}
        <div class="lg:block lg:col-span-6">
            <h1 class="text-3xl font-bold mt-1">What's new on Bleep?</h1>

            {{-- Post Form --}}
            @auth
                <div class="card bg-base-100 shadow mt-3">
                    <div class="card-body">
                        <form method="POST" action="/bleeps" enctype="multipart/form-data" id="bleep-form">
                            @csrf
                            <div class="form-control w-full">
                                <textarea
                                    name="message"
                                    placeholder="What's on your mind? Share them with a bleep!"
                                    class="textarea textarea-bordered w-full resize-none @error('message') textarea-error @enderror"
                                    rows="2"
                                    maxlength="255"
                                >{{ old('message') }}</textarea>
                            </div>

                            {{-- hidden media input (single trigger button will open this) --}}
                            <input
                                id="bleep-media-input"
                                type="file"
                                name="media[]"
                                class="hidden"
                                multiple
                                accept="image/*,video/mp4,video/webm"
                            />
                            @error('media') <div class="text-error text-xs mt-1">{{ $message }}</div> @enderror
                            @error('media.*') <div class="text-error text-xs mt-1">{{ $message }}</div> @enderror

                            {{-- preview grid --}}
                            <div id="bleep-media-preview" class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-2"></div>

                            {{-- upload progress --}}
                            <div id="upload-progress" class="mt-3 hidden">
                                <div class="flex items-center gap-2">
                                    <progress id="upload-progress-bar" class="progress progress-primary flex-1" value="0" max="100"></progress>
                                    <span id="upload-progress-percent" class="text-xs w-10 text-right">0%</span>
                                </div>
                                <div id="upload-status" class="text-xs mt-1 text-base-content/60">Starting upload...</div>
                            </div>

                            <div class="mt-1 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">

                                {{-- Left buttons (keep as first column on desktop, but render inline controls on mobile) --}}
                                <div class="flex w-full sm:w-auto items-center justify-end sm:space-y-5 gap-3">
                                    <div class="flex items-center gap-8">
                                        {{-- Anonymous: icon/label + toggle --}}
                                        <div class="flex items-center gap-1">
                                            <label for="post-anonymous-toggle" class="flex items-center gap-2 cursor-pointer select-none">
                                                <span id="post-anonymous-icon" class="p-2 rounded-full bg-base-400 transition-colors duration-150" title="Post anonymously" aria-hidden="true">
                                                    <i data-lucide="hat-glasses" class="w-5 h-5"></i>
                                                </span>
                                            </label>
                                            <input id="post-anonymous-toggle"
                                                   name="is_anonymous"
                                                   type="checkbox"
                                                   value="1"
                                                   class="toggle toggle-sm"
                                                   {{ old('is_anonymous') ? 'checked' : '' }}>
                                        </div>

                                        {{-- NSFW: icon/label + toggle --}}
                                        <div class="flex items-center gap-1">
                                            <label for="post-nsfw-toggle" class="flex items-center gap-2 cursor-pointer select-none">
                                                <span id="post-nsfw-icon" class="p-2 rounded-full bg-base-400 transition-colors duration-150" title="Mark as NSFW" aria-hidden="true">
                                                    <i data-lucide="eye-off" class="w-5 h-5"></i>
                                                </span>
                                            </label>
                                            <input id="post-nsfw-toggle"
                                                   name="is_nsfw"
                                                   type="checkbox"
                                                   value="1"
                                                   class="toggle toggle-sm"
                                                   {{ old('is_nsfw') ? 'checked' : '' }}>
                                        </div>
                                    </div>
                                </div>

                                {{-- Right buttons (remains second row on mobile because parent is flex-col on small screens) --}}
                                <div class="flex flex-col sm:flex-row items-center justify-end gap-2 w-full sm:w-auto">
                                    <div class="flex w-full sm:w-auto justify-between gap-2">
                                        {{-- Add media button --}}
                                        <button type="button" id="open-media-picker" class="btn btn-ghost btn-sm flex-1 sm:flex-none justify-center">
                                            <i data-lucide="image-plus" class="w-4 h-4 mr-1"></i>
                                            Add media
                                            <span id="bleep-media-count" class="badge badge-neutral badge-sm ml-2 hidden">0/4</span>
                                        </button>

                                        {{-- Submit post --}}
                                        <button type="submit" class="btn btn-primary btn-sm flex-1 sm:flex-none justify-center" id="post-submit-btn">
                                            <i data-lucide="send" class="w-4 h-4"></i>
                                            Post
                                        </button>
                                    </div>
                                </div>

                            </div>

                        </form>
                    </div>
                </div>
            @endauth

            {{-- bleeps container --}}
            <div id="bleeps-container" class="space-y-4 mt-8">
                @forelse ($bleeps as $bleep)
                    <x-bleep :bleep="$bleep" />
                @empty
                    <div class="hero py-12">
                        <div class="hero-content text-center">
                            <div>
                                <i data-lucide="inbox" class="w-16 h-16 mx-auto text-base-content/40"></i>
                                <p class="mt-4 text-base-content/60">No bleeps yet. Be the first to share!</p>
                            </div>
                        </div>
                    </div>
                @endforelse
            </div>

            {{-- Loading indicator --}}
            <div id="loading-indicator" class="hidden text-center py-8">
                <span class="loading loading-spinner loading-lg text-primary"></span>
                <p class="mt-2 text-base-content/60">Loading more bleeps...</p>
            </div>

            {{-- End of content indicator --}}
            <div id="end-of-content" class="hidden text-center py-8">
                <i data-lucide="circle-check" class="w-8 h-8 mx-auto text-base-content/40"></i>
                <p class="mt-2 text-base-content/60">You've reached the end of the bleeps!</p>
            </div>

            {{-- Infinite scroll trigger --}}
            <div id="infinite-scroll-trigger" data-page="2" data-has-more="{{ $bleeps->hasMorePages() ? 'true' : 'false' }}"></div>
        </div>

        {{-- Right panel --}}
        <div class="hidden lg:block lg:col-span-3" id="right-panel">
            {{-- hosting limitations announcement --}}
            <div class="alert alert-warning ml-5">
                <i data-lucide="alert-triangle" class="h-6 w-6 shrink-0 stroke-current"></i>
                <span>There may be some features unavailable due to hosting limitations.</span>
            </div>
            {{-- Suggested to follow (friend's following) --}}
        </div>
    </div>

</x-layout>

{{-- Media View Modal --}}
<x-subcomponents.bleeps.mediamodal />

{{-- Edit Bleep post Modal --}}
<x-modals.posts.edit />

{{-- Comments Modal --}}
<x-modals.posts.comments />

{{-- Share Modal (from bleep component, but ensuring it's available) --}}
<x-modals.posts.share />
