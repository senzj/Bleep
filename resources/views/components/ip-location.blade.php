@props(['ip' => null, 'align' => 'left'])

@php
    $location = $ip ? app(\App\Services\GeoIpService::class)->locate($ip) : null;
    $isPrivate = $ip && ! \App\Services\GeoIpService::isPublicAddress($ip);
    $alignment = $align === 'center' ? 'justify-center text-center' : 'justify-start text-left';

    // Columns are narrow, so the visible label is city + country only. The
    // region and timezone stay available in the tooltip.
    $place = $location
        ? collect([$location['city'] ?? null, $location['country'] ?? null])->filter()->unique()->implode(', ')
        : null;
    $place = $place ?: ($location['label'] ?? null);

    $details = $location
        ? collect([
            $location['city'] ?? null,
            $location['region'] ?? null,
            $location['country'] ?? null,
            $location['timezone'] ?? null,
        ])->filter()->unique()->implode(' · ')
        : null;
@endphp

@if ($location)
    <span
        class="mt-0.5 inline-flex max-w-full items-center gap-1 text-[11px] leading-tight text-base-content/60 {{ $alignment }}"
        title="{{ $details }}"
    >
        <span class="truncate">{{ $place }}</span>
    </span>
@elseif ($ip)
    <span class="mt-0.5 inline-flex max-w-full items-center gap-1 text-[11px] leading-tight text-base-content/35 {{ $alignment }}">
        {{ $isPrivate ? 'Local / private network' : 'Location unavailable' }}
    </span>
@endif
