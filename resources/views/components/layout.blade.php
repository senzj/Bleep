<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        {{-- icon --}}
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        {{-- optional PNG fallback (add public/favicon-32.png if you want) --}}
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('Bleep_Icon.png') }}">

        {{-- meta data --}}
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="base_url" content="{{ url('') }}">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="media-chunked-threshold" content="{{ (int) config('media.chunked_upload_threshold_kb', 8192) * 1024 }}">
        <meta name="media-chunk-size" content="{{ (int) config('media.chunk_size_kb', 8192) * 1024 }}">
        <meta name="media-max-file-size" content="{{ (int) config('media.max_bleep_file_kb', 2097152) * 1024 }}">
        @stack('meta')
        {{-- Prevent flash: set theme before CSS paints --}}
        <script>
            // Run before ANY styles load to prevent flash
            (() => {
                const DEFAULT = 'lofi';
                // Check for server-provided theme (logged-in user)
                const serverTheme = '{{ Auth::check() ? Auth::user()->getPreferences()->theme : '' }}';
                let t = serverTheme || localStorage.getItem('theme') || DEFAULT;

                // Sync localStorage with server theme
                if (serverTheme && serverTheme !== localStorage.getItem('theme')) {
                    localStorage.setItem('theme', serverTheme);
                }

                if (t === 'system') {
                    try {
                        t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                    } catch (e) {}
                }
                document.documentElement.setAttribute('data-theme', t);
            })();
        </script>
        @vite([
            'resources/js/init.js',
            'resources/css/app.css',
            'resources/js/app.js',
        ])
        <title>{{ isset($title) ? $title : '' }} | Bleep</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
        @stack('styles')
    </head>

    @php
        $navLayout = 'horizontal';
        $preferences = null;
        if (Auth::check()) {
            $navLayout = Auth::user()->getNavLayout();
            $preferences = Auth::user()->getPreferences();
        }

        $showNsfw = $preferences?->show_nsfw ?? false;
        $blurNsfw = $preferences?->blur_nsfw_media ?? true;
        $autoplayVideos = $preferences?->autoplay_videos ?? true;
        $autoplayAudio = $preferences?->autoplay_audio ?? false;
        $mediaOffscreen = $preferences?->media_offscreen ?? 'pause';
        $mediaDownloads = $preferences?->media_downloads ?? false;
        $resumeMediaOnView = $preferences?->resume_media_on_view ?? false;
        $rememberMediaPosition = $preferences?->remember_media_position ?? true;
    @endphp

    <body class="min-h-screen font-sans bg-base-300 text-base-content"
          data-authenticated="{{ Auth::check() ? '1' : '0' }}"
          data-nav-layout="{{ $navLayout }}"
          data-show-nsfw="{{ $showNsfw ? '1' : '0' }}"
          data-blur-nsfw="{{ $blurNsfw ? '1' : '0' }}"
          data-autoplay-videos="{{ $autoplayVideos ? '1' : '0' }}"
          data-autoplay-audio="{{ $autoplayAudio ? '1' : '0' }}"
          data-media-offscreen="{{ $mediaOffscreen }}"
          data-media-downloads="{{ $mediaDownloads ? '1' : '0' }}"
          data-resume-media-on-view="{{ $resumeMediaOnView ? '1' : '0' }}"
          data-remember-media-position="{{ $rememberMediaPosition ? '1' : '0' }}">

        <x-loading.loading-curtain />

        {{-- toast --}}
        @if (session('success'))
            <div class="toast toast-top toast-center z-100">
                <div class="alert alert-success animate-fade-out">
                    <i data-lucide="check-circle" class="h-6 w-6 shrink-0 stroke-current"></i>
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @elseif (session('error'))
            <div class="toast toast-top toast-center z-100">
                <div class="alert alert-error animate-fade-out">
                    <i data-lucide="circle-alert" class="h-6 w-6 shrink-0 stroke-current"></i>
                    <span>{{ session('error') }}</span>
                </div>
            </div>
        @endif

        @if($navLayout === 'vertical')
            {{-- Vertical nav layout: flex container with sidebar + content --}}
            <div class="flex min-h-screen">
                @include('components.include.navbar')

                <div class="flex-1 flex flex-col">
                    <main class="flex-1 w-full px-2 py-4 sm:px-4 lg:px-6">
                        {{ $slot }}
                    </main>
                </div>
            </div>
        @else
            {{-- Horizontal nav layout: traditional stacked layout --}}
            @include('components.include.navbar')

            <main class="flex-1 w-full px-2 py-4 sm:px-4 lg:px-6">
                {{ $slot }}
            </main>
        @endif

        <x-media.upload-tray />

        @stack('scripts')
    </body>
</html>
