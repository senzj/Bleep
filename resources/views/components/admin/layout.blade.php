<x-layout>
    <x-slot:title>{{ ucwords(Auth::user()->role) }} Dashboard</x-slot:title>

    <div class="mx-auto grid grid-cols-1 md:grid-cols-12 gap-6">
        <aside class="md:col-span-4 lg:col-span-2 md:sticky md:top-20 self-start">
            <x-admin.nav />
        </aside>

        <main class="md:col-span-8 lg:col-span-10">
            @if (session('success') || session('status') || $errors->any())
                <div class="mb-4 space-y-2">
                    @if (session('success') || session('status'))
                        <div class="alert alert-success">
                            <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                            <span>{{ session('success') ?? session('status') }}</span>
                        </div>
                    @endif
                    @if ($errors->any())
                        <div class="alert alert-error">
                            <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                            <span>There were some problems with your submission. Please review the fields below.</span>
                        </div>
                    @endif
                </div>
            @endif

            <div class="bg-base-100 rounded-lg p-6 shadow-sm">
                {{ $slot }}
            </div>
        </main>
    </div>
</x-layout>
