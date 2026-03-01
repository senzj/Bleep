<x-layout>
    <x-slot:title>{{ ucwords(Auth::user()->role) }} Dashboard</x-slot:title>

    <div class="mx-auto">
        {{-- Mobile top nav --}}
        <div class="md:hidden mb-1">
            <x-admin.nav />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
            <aside class="hidden md:block md:col-span-4 lg:col-span-2 md:sticky md:top-20 self-start">
                <x-admin.nav />
            </aside>

            <main class="md:col-span-8 lg:col-span-10">
                <div class="bg-base-100 rounded-lg p-6 shadow-sm">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>
</x-layout>
