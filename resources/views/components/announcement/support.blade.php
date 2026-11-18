{{--
    Support the project card.

    Shown inside the bleep feed on a random share of renders (see
    App\Support\SupportPromo). Replace the URL on the next line with your
    Ko-fi page — everything else here is display only.
--}}
@php
    $kofiUrl = 'https://ko-fi.com/REPLACE_WITH_YOUR_PAGE';
@endphp

<div class="relative overflow-hidden rounded-lg border border-[#ff5e5b]/40 bg-base-100 shadow-md" data-support-card>
    {{-- Coral edge so the card reads as its own block without shouting. --}}
    <span class="absolute inset-y-0 left-0 w-1 bg-[#ff5e5b]" aria-hidden="true"></span>

    <div class="flex flex-col gap-4 py-4 pl-5 pr-4 sm:flex-row sm:items-center">
        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-[#ff5e5b]/15 text-[#ff5e5b] ring-4 ring-[#ff5e5b]/10" aria-hidden="true">
            <i data-lucide="coffee" class="h-6 w-6"></i>
        </span>

        <div class="min-w-0 flex-1">
            <p class="text-base font-semibold">Support the project</p>
            <p class="mt-0.5 text-sm text-base-content/70">
                Bleep is free and has no ads. If it made your day a little better, you can buy it a coffee.
            </p>
        </div>

        <a href="{{ $kofiUrl }}"
           target="_blank"
           rel="noopener noreferrer"
           class="btn btn-sm shrink-0 self-start border-0 bg-[#ff5e5b] text-white shadow-sm hover:bg-[#f04b48] sm:self-center"
           aria-label="Support the project on Ko-fi">
            <i data-lucide="heart" class="h-4 w-4" aria-hidden="true"></i>
            Buy me a coffee
        </a>
    </div>
</div>
