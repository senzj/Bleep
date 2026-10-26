@props(['notification'])

@php
    $audienceLabels = [
        'user' => 'Activity',
        'moderator' => 'Moderation',
        'admin' => 'Administration',
    ];
@endphp

<a
    href="{{ $notification['url'] ?? '#' }}"
    data-notification-id="{{ $notification['id'] }}"
    data-notification-read="{{ $notification['read'] ? '1' : '0' }}"
    class="flex gap-4 border-b border-base-200/70 px-4 py-4 transition-colors last:border-b-0 hover:bg-base-200/60 {{ $notification['read'] ? '' : 'bg-primary/5' }}"
>
    @if ($notification['actor'])
        <img
            src="{{ $notification['actor']['profile_picture_url'] }}"
            alt="{{ $notification['actor']['dname'] ?? $notification['actor']['username'] }}"
            class="h-10 w-10 shrink-0 rounded-full object-cover"
        >
    @else
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-base-200">
            <i data-lucide="{{ $notification['icon'] }}" class="h-5 w-5 text-base-content/70"></i>
        </span>
    @endif

    <span class="min-w-0 flex-1">
        <span class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-medium leading-snug">{{ $notification['title'] }}</span>

            @if ($notification['audience'] !== 'user')
                <span class="badge badge-xs badge-outline">
                    {{ $audienceLabels[$notification['audience']] ?? ucfirst($notification['audience']) }}
                </span>
            @endif
        </span>

        @if ($notification['body'])
            <span class="mt-1 block text-sm text-base-content/70">{{ $notification['body'] }}</span>
        @endif

        <span class="mt-1 block text-xs text-base-content/50">{{ $notification['created_at_human'] }}</span>
    </span>

    @unless ($notification['read'])
        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-primary" aria-label="Unread"></span>
    @endunless
</a>
