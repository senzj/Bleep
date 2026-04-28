@push('scripts')
    @vite([
        'resources/js/social/people.js',
        'resources/js/bleep/users/follow.js',
    ])
@endpush

<x-layout>
    <x-slot:title>People</x-slot:title>

    @auth
        <div class="people-component bg-base-200 p-4 rounded-lg shadow-sm">
            <div class="flex items-center mb-4 mr-2">
                <i data-lucide="users" class="w-6 h-6 inline-block mr-2"></i>
                <h2 class="text-xl font-bold">People you may know</h2>
            </div>
            <div class="space-y-4">
                {{-- Search users input --}}
                <div>
                    <input
                        type="text"
                        placeholder="Search by username or display name..."
                        class="input input-bordered w-full people-search-input"
                    />
                </div>

                {{-- User suggestions --}}
                <div class="people-user-suggestions space-y-1">
                    <x-card.users :users="$suggestedUsers" :showMessage="true" />
                </div>

                {{-- No results message for search --}}
                <div class="people-no-results-message hidden text-center text-base-content/60 py-4"> {{-- changed id -> class --}}
                    <i data-lucide="search-x" class="h-8 w-8 shrink-0 stroke-current inline-block mb-2"></i>
                    <p>No users found. Try a different search term.</p>
                </div>
            </div>
        </div>
    @endauth
</x-layout>
