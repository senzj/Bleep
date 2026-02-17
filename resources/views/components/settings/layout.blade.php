<x-layout>
    <x-slot:title>Settings</x-slot:title>

    <div class="mx-auto grid grid-cols-1 md:grid-cols-12 gap-6">
        <aside class="md:col-span-4 lg:col-span-2 md:sticky md:top-20 self-start bg-base-100 rounded-lg shadow-sm p-3">
            <p class="mb-3 text-lg text-base-content">
                <span class="font-semibold">Profile Settings</span>
            </p>
            <x-settings.nav />
        </aside>

        <main class="md:col-span-8 lg:col-span-10">
            <div class="bg-base-100 rounded-lg p-6 shadow-sm">
                {{ $slot }}
            </div>
        </main>
    </div>
</x-layout>
