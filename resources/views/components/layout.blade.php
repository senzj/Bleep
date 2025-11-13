<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="lofi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        {{-- Prevent flash: set theme before CSS paints --}}
        @vite(['resources/js/themeinit.js','resources/css/app.css','resources/js/app.js'])
        <title>{{ isset($title) ? $title : 'Bleep' }}</title>
        <link rel="preconnect" href="<https://fonts.bunny.net>">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
        <link href="https://cdn.jsdelivr.net/npm/daisyui@5/themes.css" rel="stylesheet" type="text/css" />
        @stack('styles')

        {{-- meta data --}}
        <meta name="base_url" content="{{ url('') }}">
        <meta name="csrf-token" content="{{ csrf_token() }}">
    </head>

    <body class="min-h-screen flex flex-col font-sans bg-base-300/90">
        @include('components.include.navbar')

        {{-- success toast --}}
        @if (session('success'))
            <div class="toast toast-top toast-center">
                <div class="alert alert-success animate-fade-out">
                    <i data-lucide="check-circle" class="h-6 w-6 shrink-0 stroke-current"></i>
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @elseif (session('error'))
            <div class="toast toast-top toast-center">
                <div class="alert alert-success animate-fade-out">
                    <i data-lucide="check-alert" class="h-6 w-6 shrink-0 stroke-current"></i>
                    <span>{{ session('error') }}</span>
                </div>
            </div>
        @endif

        {{-- main content --}}
        <main class="flex-1 container mx-auto px-4 py-6">
            {{ $slot }}
        </main>

        @stack('scripts')
    </body>
</html>
