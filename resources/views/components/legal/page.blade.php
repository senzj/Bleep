@props(['title', 'updated' => '3 November 2026'])

<x-layout>
    <x-slot:title>{{ $title }} | Bleep</x-slot:title>

    <div class="container mx-auto max-w-3xl px-4 py-8">
        <div class="rounded-xl border border-base-200 bg-base-100 p-6 shadow-sm sm:p-8">
            <nav class="mb-6 flex flex-wrap gap-2 text-xs" aria-label="Legal documents">
                <a href="{{ route('legal.terms') }}" class="btn btn-ghost btn-xs {{ request()->routeIs('legal.terms') ? 'btn-active' : '' }}">Terms of Service</a>
                <a href="{{ route('legal.privacy') }}" class="btn btn-ghost btn-xs {{ request()->routeIs('legal.privacy') ? 'btn-active' : '' }}">Privacy Policy</a>
                <a href="{{ route('legal.cookies') }}" class="btn btn-ghost btn-xs {{ request()->routeIs('legal.cookies') ? 'btn-active' : '' }}">Cookie Policy</a>
            </nav>

            <h1 class="text-2xl font-bold sm:text-3xl">{{ $title }}</h1>
            <p class="mt-1 text-xs text-base-content/60">Last updated: {{ $updated }}</p>

            <div class="mt-6 space-y-6 text-sm leading-relaxed text-base-content/80">
                {{ $slot }}
            </div>
        </div>
    </div>
</x-layout>
