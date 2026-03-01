@push('scripts')
    @vite([
        'resources/js/social/people.js',
        'resources/js/bleep/users/follow.js',
    ])
@endpush

<x-layout>
    <x-slot:title>Socials</x-slot:title>

    @auth
        <div class="people-component bg-base-200 p-4 rounded-lg shadow-sm"> {{-- added class wrapper --}}
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
                        class="input input-bordered w-full people-search-input" {{-- changed id -> class --}}
                    />
                </div>

                {{-- User suggestions --}}
                <div class="people-user-suggestions space-y-4"> {{-- changed id -> class --}}
                    @forelse($suggestedUsers as $user)
                        @php
                            $isFollowing = Auth::user()->following()->where('followed_id', $user->id)->exists();
                            $isMutual = isset($user->is_mutual) && $user->is_mutual;
                            $mutualType = $user->mutual_type ?? null;
                            $mutualLabel = match ($mutualType) {
                                'two-way' => 'Friend',
                                'friend-of-friend' => '',
                                'friend-of-friend-of-friend' => '',
                                default => null,
                            };
                        @endphp
                        <div class="user-item flex items-center gap-4 w-full min-w-0 flex-wrap"
                            data-user-id="{{ $user->id }}"
                            data-username="{{ $user->username }}"
                            data-display-name="{{ $user->dname }}"
                            data-is-mutual="{{ $isMutual ? '1' : '0' }}">
                            <a href="/bleeper/{{ $user->username }}" class="shrink-0">
                                <img src="{{ $user->profile_picture_url }}"
                                    alt="{{ $user->dname }}'s Avatar"
                                    class="size-10 rounded-full hover:ring-2 hover:ring-primary transition-all">
                            </a>

                            <div class="flex-1 min-w-0">
                                <a href="/bleeper/{{ $user->username }}" class="block hover:text-primary transition-colors">
                                    <p class="font-semibold truncate flex items-center gap-2">
                                        <span class="truncate">{{ $user->dname }}</span>
                                        @if($mutualLabel)
                                            <span class="ml-2 badge badge-sm badge-outline shrink-0">{{ $mutualLabel }}</span>
                                        @endif
                                    </p>
                                    <p class="text-sm text-base-content/60 truncate">{{ "@" . $user->username }}</p>
                                </a>
                            </div>

                            {{-- follow/unfollow button (use same markup as bleep follow.js expects) --}}
                            <x-button.follow :user="$user" :showMessage="true" :showFollowed="false" />
                        </div>
                    @empty
                        <div class="text-center text-base-content/60 py-4">
                            <i data-lucide="user-x" class="h-8 w-8 shrink-0 stroke-current inline-block mb-2"></i>
                            <p>No suggestions available at the moment.</p>
                        </div>
                    @endforelse
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
