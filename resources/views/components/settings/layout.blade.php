<x-layout>
    <x-slot:title>Settings</x-slot:title>

    <div class="mx-auto">
        <div class="bg-base-100 rounded-lg shadow-sm">
            {{-- Settings header with nav --}}
            <div class="border-b border-base-200 p-4">
                <h1 class="text-xl font-semibold text-base-content mb-4">Profile Settings</h1>
                <x-settings.nav />
            </div>

            {{-- Settings content --}}
            <div class="p-6">
                {{ $slot }}
            </div>
        </div>
    </div>
</x-layout>
