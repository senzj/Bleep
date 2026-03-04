<x-layout>
    <x-slot:title>
        Chat
    </x-slot:title>

    <div
        id="chat-app"
        data-user-id="{{ auth()->id() }}"
        data-username="{{ auth()->user()->dname ?: auth()->user()->username }}"
        class="h-[86vh] w-full"
    >
    </div>

</x-layout>
