@push('scripts')
    @vite([
        'resources/js/bleep/modals/mediamodal.js',
    ])
@endpush

<x-layout>
    <x-slot:title>
        Chat
    </x-slot:title>

    <div
        id="chat-app"
        data-user-id="{{ auth()->id() }}"
        data-username="{{ auth()->user()->dname ?: auth()->user()->username }}"
        data-send-sound="{{ auth()->user()->getPreferences()->send_notification_sound ?? '/sounds/effects/bloop-1.mp3' }}"
        data-receive-sound="{{ auth()->user()->getPreferences()->recieve_notification_sound ?? '/sounds/effects/marimba-bloop-1.mp3' }}"
        class="h-[calc(100vh-6.7rem)] w-full"
    >
    </div>

    {{-- load modal once --}}
    @once
        {{-- Media Modal for images and videos --}}
        <x-subcomponents.bleeps.mediamodal />
    @endonce

</x-layout>
