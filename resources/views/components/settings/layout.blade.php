<x-layout>
    <x-slot:title>Settings</x-slot:title>

    <div class="max-w-6xl mx-auto grid grid-cols-1 md:grid-cols-12 gap-6">
        <aside class="md:col-span-4 lg:col-span-3">
            <div class="bg-base-100 rounded-lg p-3 shadow">
                <x-settings.nav />
            </div>
        </aside>

        <main class="md:col-span-8 lg:col-span-9">
            <div class="bg-base-100 rounded-lg p-6 shadow">
                {{ $slot }}
            </div>
        </main>
    </div>
</x-layout>
