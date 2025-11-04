
<x-layout>
    <x-slot:title>
        Bleep
    </x-slot:title>

    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold mt-1">Latest on Bleep</h1>

        {{-- form --}}
        @auth
            <div class="card bg-base-100 shadow mt-8">
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

                        <div class="mt-4 flex items-center justify-end">
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
</x-layout>
