@props([
    'users' => null,
    'emptyMessage' => 'No users found.',
    'showMessage' => true,
])

@php
    $items = $users ?? $suggestedUsers ?? collect();
@endphp

@forelse($items as $user)
    @php
        $isMutual = isset($user->is_mutual) && $user->is_mutual;
        $mutualType = $user->mutual_type ?? null;
        $mutualLabel = match ($mutualType) {
            'two-way' => 'Friend',
            'friend-of-friend' => 'Mutual Friend',
            'friend-of-friend-of-friend' => 'Mutual Friend of Friend',
            default => null,
        };
    @endphp

    <div class="user-item flex items-center gap-4 w-full min-w-0 flex-wrap p-4 rounded-lg hover:bg-base-100 transition"
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
        <x-button.follow :user="$user" :showMessage="$showMessage" :showFollowed="false" />
    </div>
@empty
    <div class="text-center text-base-content/60 py-4">
        <i data-lucide="user-x" class="h-8 w-8 shrink-0 stroke-current inline-block mb-2"></i>
        <p>{{ $emptyMessage }}</p>
    </div>
@endforelse
