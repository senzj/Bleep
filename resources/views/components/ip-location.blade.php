@props(['ip' => null, 'align' => 'left'])

@php
    $location = $ip ? app(\App\Services\GeoIpService::class)->locate($ip) : null;
    $isPrivate = $ip && ! \App\Services\GeoIpService::isPublicAddress($ip);
    $alignment = $align === 'center' ? 'justify-center text-center' : 'justify-start text-left';
@endphp

@if ($location)
    <span
        class="mt-0.5 inline-flex max-w-full items-center gap-1 text-[11px] leading-tight text-base-content/60 {{ $alignment }}"
        title="{{ collect([$location['city'], $location['region'], $location['country'], $location['timezone']])->filter()->implode(' · ') }}"
    >
        @if ($location['flag'])
            <span aria-hidden="true">{{ $location['flag'] }}</span>
        @endif
        <span class="truncate">{{ $location['label'] }}</span>
    </span>
@elseif ($ip)
    <span class="mt-0.5 inline-flex max-w-full items-center gap-1 text-[11px] leading-tight text-base-content/35 {{ $alignment }}">
        {{ $isPrivate ? 'Local / private network' : 'Location unavailable' }}
    </span>
@endif
