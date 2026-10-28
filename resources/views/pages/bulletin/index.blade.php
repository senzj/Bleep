@push('scripts')
    @vite(['resources/js/bulletin.js', 'resources/css/bulletin.css'])
@endpush

<x-bulletin-layout title="Bulletin Board">
    <div
        id="bulletin-board"
        class="flex min-h-0 flex-1 flex-col"
        data-posts="{{ json_encode($initialPosts) }}"
        data-next-cursor="{{ $nextCursor }}"
        data-has-more="{{ $hasMore ? '1' : '0' }}"
        data-identity="{{ $identity }}"
        data-home-url="{{ route('home') }}"
        data-endpoint-posts="{{ route('bulletin.posts.index') }}"
        data-endpoint-store="{{ route('bulletin.posts.store') }}"
    ></div>

    <noscript>
        <div class="mx-auto w-full max-w-5xl flex-1 overflow-y-auto px-4 py-5">
            <a href="{{ route('home') }}" class="link link-hover text-sm opacity-70">← Back to Bleep</a>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">Bleep Bulletin</h1>
            <p class="mt-1 text-sm opacity-70">
                Anonymous notes pinned by visitors. JavaScript is needed to write a note.
            </p>

            <div class="mt-6 flex flex-wrap gap-4">
                @forelse ($posts as $post)
                    <article
                        class="w-56 rounded-sm p-4 text-neutral-900 shadow-lg"
                        style="background-color: {{ $post->paper_color ?? '#fef08a' }}"
                    >
                        <p class="whitespace-pre-line break-words text-sm leading-relaxed">{{ $post->content }}</p>
                        <footer class="mt-3 border-t border-black/10 pt-2 text-[11px]">
                            <span class="block font-semibold">{{ $post->anonymous_name }}</span>
                            <time datetime="{{ $post->created_at->toIso8601String() }}">
                                {{ $post->created_at->format('M d, Y | g:i a') }}
                            </time>
                        </footer>
                    </article>
                @empty
                    <p class="text-sm opacity-70">The board is empty.</p>
                @endforelse
            </div>
        </div>
    </noscript>
</x-bulletin-layout>
