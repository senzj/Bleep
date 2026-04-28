<x-layout>
    <x-slot:title>Blocked Users</x-slot:title>

    <div class="container mx-auto px-4 max-w-5xl">
        <div class="bg-base-100 rounded-lg shadow-lg border border-base-300 p-6">
            <div class="flex items-center mb-6">
                <i data-lucide="ban" class="w-6 h-6 mr-3"></i>
                <h1 class="text-2xl font-bold">Blocked Users</h1>
            </div>

            {{-- Blocked Users List --}}
            <div class="space-y-4">
                @if($blockedUsers && $blockedUsers->isNotEmpty())
                    @foreach($blockedUsers as $blockedUser)
                        <div class="flex items-center gap-4 p-4 bg-base-100 rounded-md shadow-sm">
                            <img src="{{ $blockedUser->profile_picture_url }}" alt="{{ $blockedUser->username }}'s avatar" class="w-12 h-12 rounded-full">
                            <div>
                                <a href="{{ route('user.profile', ['username' => $blockedUser->username]) }}" class="font-semibold text-base-content hover:underline">{{ $blockedUser->dname ?? $blockedUser->username }}</a>
                                <p class="text-sm text-base-content/70">{{ '@' . $blockedUser->username }}</p>
                            </div>
                            <form method="POST"
                                action="{{ route('blocked.users.unblock', ['user' => $blockedUser->id]) }}"
                                class="ml-auto"
                                data-block-confirm="unblock"
                                data-username="{{ '@' . $blockedUser->username }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline rounded-lg hover:btn-error">Unblock</button>
                            </form>
                        </div>
                    @endforeach

                @else
                    <div class="text-center py-12">
                        <i data-lucide="user-x" class="w-10 h-10 mx-auto mb-4"></i>
                        <p class="text-base-content/60 mb-2">No Available Blocked Users</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @once
        @push('scripts')
            @vite([
                'resources/js/social/blockuser.js',
            ])
        @endpush

        {{-- Block/Unblock Confirmation Modal --}}
        <input type="checkbox" id="block_confirm_modal_toggle" class="modal-toggle" />
        <div class="modal">
            <div class="modal-box max-w-md relative">
                <label for="block_confirm_modal_toggle" class="btn btn-sm btn-circle absolute right-3 top-3">✕</label>

                <h3 class="font-bold text-lg mb-2" id="block-confirm-title">Confirm action</h3>
                <p class="text-sm text-base-content/70" id="block-confirm-message">Are you sure?</p>

                <div class="modal-action">
                    <label for="block_confirm_modal_toggle" class="btn btn-ghost">Cancel</label>
                    <button type="button" id="block-confirm-submit" class="btn btn-error">Confirm</button>
                </div>
            </div>
        </div>
    @endonce
</x-layout>
