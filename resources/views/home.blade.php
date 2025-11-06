@vite([
    'resources/js/bleep/posts',
])

<x-layout>
    <x-slot:title>
        Bleep
    </x-slot:title>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {{-- Left panel --}}
        <div class="hidden lg:block lg:col-span-2">
            {{-- Left sidebar content --}}
        </div>

        {{-- Center panel --}}
        <div class="lg:block lg:col-span-8">
            <h1 class="text-3xl font-bold mt-1">What's new on Bleep?</h1>

            {{-- Post Form --}}
            @auth
                <div class="card bg-base-100 shadow mt-3">
                    <div class="card-body">
                        <form method="POST" action="/bleeps">
                            @csrf
                            <div class="form-control w-full">
                                <textarea
                                    name="message"
                                    placeholder="What's on your mind? Share them with a bleep!"
                                    class="textarea textarea-bordered w-full resize-none @error('message') textarea-error @enderror"
                                    rows="2"
                                    maxlength="255"
                                    required
                                >{{ old('message') }}</textarea>
                            </div>

                            @error('message')
                                <div class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </div>
                            @enderror

                            <div class="mt-4 flex items-center justify-between">
                                {{-- anonymous toggle --}}
                                <label class="cursor-pointer label flex items-center">
                                    <span class="label-text mr-2">Post anonymously</span>
                                    <input id="post-anonymous-toggle" type="checkbox" name="is_anonymous" value="1" class="toggle toggle-primary" {{ old('is_anonymous') ? 'checked' : '' }} />
                                    <!-- Indicator: empty when unchecked, shows hat-glasses when checked -->
                                    <div id="post-toggle-indicator" class="ml-2 w-7 h-7 rounded-full transition-all duration-200 flex items-center justify-center overflow-hidden" aria-hidden="true"></div>
                                </label>

                                {{-- submit post --}}
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i data-lucide="send" class="w-4 h-4"></i> Post
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endauth

            {{-- bleeps --}}
            <div class="space-y-4 mt-8">
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
        </div>

        {{-- Right panel --}}
        <div class="hidden lg:block lg:col-span-2" id="right-panel">
            {{-- Right sidebar content --}}
        </div>
    </div>

    {{-- Floating Comments Modal --}}
    <div id="floating-comments-modal" class="hidden fixed z-50 bg-base-100 rounded-2xl shadow-2xl border border-base-200 flex flex-col overflow-hidden transition-all duration-300 ease-out">
        {{-- Sticky Header --}}
        <div id="floating-comments-header" class="sticky top-0 z-10 flex items-center justify-between px-4 py-3 border-b border-base-200 bg-base-100/95 backdrop-blur-sm shrink-0">
            <h2 class="text-lg font-semibold flex items-center gap-2">
                <i data-lucide="message-circle-more" class="w-5 h-5"></i>
                Comments
            </h2>
            <button id="close-comments-btn" class="btn btn-ghost btn-sm btn-circle hover:bg-base-300 dark:hover:bg-base-200">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        {{-- Scrollable Content (fills remaining space) --}}
        <div id="floating-comments-scroll" class="flex-1 overflow-y-auto px-4 py-3 space-y-3 bg-gray-200/80">
            <div class="flex justify-center items-center py-10">
                <span class="loading loading-spinner loading-md"></span>
            </div>
        </div>

        {{-- Sticky Input Footer --}}
        @auth
            <div class="sticky bottom-0 z-10 bg-base-100 p-6 shrink-0">
                <form id="floating-comment-form" class="flex items-end gap-3" data-bleep-id="">
                    @csrf
                    <div class="flex-1">
                        <textarea
                            name="message"
                            rows="1"
                            data-min-height="32"
                            class="textarea textarea-bordered w-full resize-none text-sm leading-snug min-h-9 max-h-20 rounded-xl"
                            placeholder="Write a comment..."
                            required
                        ></textarea>
                    </div>

                    {{-- Toggle anonymous --}}
                    <div class="flex items-end gap-2 shrink-0">
                        <label class="relative inline-flex cursor-pointer">
                            <input type="checkbox" id="comment-anonymous-toggle" name="is_anonymous" value="1" class="peer sr-only">
                            <div class="w-15 h-9 bg-base-300 peer-checked:bg-base-300 rounded-full peer-focus:ring-2 peer-focus:ring-primary transition-all"></div>
                            <div id="toggle-indicator"
                                class="absolute top-1 left-1 size-7 rounded-full transition-all duration-300 peer-checked:left-7 bg-cover bg-center flex items-center justify-center"
                                data-user-email="{{ auth()->user()->email }}"
                                style="background-image: url('https://avatars.laravel.cloud/{{ auth()->user()->email }}');">
                            </div>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary btn-base btn-circle self-end shrink-0">
                        <i data-lucide="send" class="w-5 h-5"></i>
                    </button>
                </form>
            </div>
        @else
            <div class="sticky bottom-0 z-10 border-t border-base-200 bg-base-100/95 backdrop-blur-sm p-4 text-center text-sm text-base-content/60 shrink-0">
                <a href="/login" class="link link-primary">Login</a> to comment
            </div>
        @endauth
    </div>

    {{-- Overlay for closing modal --}}
    <div id="comments-overlay" class="hidden fixed inset-0 bg-gray-600/20 z-40"></div>
</x-layout>
