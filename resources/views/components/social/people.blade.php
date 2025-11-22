@push('scripts')
    @vite('resources/js/social/people.js')
@endpush

@auth
    <div class="bg-base-200 p-4 rounded-lg shadow-sm">
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
                    class="input input-bordered w-full"
                    id="user-search-input"
                />
            </div>

            {{-- User suggestions --}}
            <div id="user-suggestions" class="space-y-4">
                @forelse($suggestedUsers as $user)
                    <div class="user-item flex items-center space-x-4 w-full min-w-0" data-username="{{ $user->username }}" data-display-name="{{ $user->dname }}" data-is-mutual="{{ isset($user->is_mutual) && $user->is_mutual ? '1' : '0' }}">
                        <img src="{{ $user->profile_picture_url }}" alt="{{ $user->dname }}'s Avatar" class="w-12 h-12 rounded-full shrink-0">

                        <div class="flex-1 min-w-0">
                            <p class="font-semibold truncate flex items-center">
                                <span class="truncate">{{ $user->dname }}</span>
                                @if(isset($user->is_mutual) && $user->is_mutual)
                                    <span class="ml-2 badge badge-sm badge-outline shrink-0">Mutual</span>
                                @endif
                            </p>
                            <p class="text-sm text-base-content/60 truncate">{{ "@" . $user->username }}</p>
                        </div>

                        <button class="follow-btn btn btn-sm btn-primary shrink-0" data-user-id="{{ $user->id }}" data-following="false" aria-pressed="false">
                            <i data-lucide="user-plus" class="w-4 h-4 mr-1"></i>
                            <span class="btn-label">Follow</span>
                        </button>
                    </div>
                @empty
                    <div class="text-center text-base-content/60">
                        <i data-lucide="frown" class="h-6 w-6 shrink-0 stroke-current inline-block mb-2"></i>
                        <p class="text-center text-base-content/60">Looks like you're on your own buddy..</p>
                    </div>
                @endforelse
            </div>

            {{-- No results message for search --}}
            <div id="no-results-message" class="hidden text-center text-base-content/60">
                <i data-lucide="circle-question-mark" class="h-6 w-6 shrink-0 stroke-current inline-block mb-2"></i>
                No user found. Are you sure this is the right person?
            </div>
        </div>
    </div>
@endauth
