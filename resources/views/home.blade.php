@vite([
    'resources/js/bleep/posts/like.js',
    'resources/js/bleep/posts/post.js',
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
    <x-modals.posts.comments :bleepId="$bleeps->first()?->id" />

    {{-- Overlay for closing modal --}}
    <div id="comments-overlay" class="hidden fixed inset-0 bg-gray-600/20 z-40"></div>

    {{-- Edit Bleep post Modal --}}
    <x-modals.posts.edit />

</x-layout>
