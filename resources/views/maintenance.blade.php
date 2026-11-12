<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex">
        <title>Maintenance | {{ config('app.name', 'Bleep') }}</title>
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <style>
            :root {
                color-scheme: light dark;
                --bg: #f3f1ec;
                --card: #ffffff;
                --text: #1f2937;
                --muted: #6b7280;
                --accent: #2563eb;
                --border: #e5e7eb;
            }

            @media (prefers-color-scheme: dark) {
                :root {
                    --bg: #17181c;
                    --card: #22242a;
                    --text: #f3f4f6;
                    --muted: #9ca3af;
                    --accent: #7aa2ff;
                    --border: #343740;
                }
            }

            * { box-sizing: border-box; }

            body {
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                padding: 1.5rem;
                background: var(--bg);
                color: var(--text);
                font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            }

            main {
                width: 100%;
                max-width: 34rem;
                background: var(--card);
                border: 1px solid var(--border);
                border-radius: 1rem;
                padding: 2rem;
                text-align: center;
                box-shadow: 0 22px 50px -30px rgb(0 0 0 / 0.45);
            }

            .mark {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 3rem;
                height: 3rem;
                border-radius: 0.85rem;
                background: color-mix(in srgb, var(--accent) 12%, transparent);
                color: var(--accent);
                font-size: 1.5rem;
                margin-bottom: 1rem;
            }

            h1 {
                font-size: clamp(1.4rem, 3vw, 1.8rem);
                margin: 0 0 0.5rem;
                letter-spacing: -0.02em;
            }

            p {
                margin: 0;
                line-height: 1.6;
                color: var(--muted);
                font-size: 0.95rem;
            }

            .eta {
                margin-top: 1.25rem;
                padding: 0.85rem 1rem;
                border-radius: 0.7rem;
                border: 1px dashed var(--border);
                font-size: 0.9rem;
                color: var(--text);
            }

            a {
                color: var(--accent);
                text-decoration: none;
                font-weight: 600;
            }

            a:hover { text-decoration: underline; }

            footer {
                margin-top: 1.5rem;
                font-size: 0.8rem;
                color: var(--muted);
            }
        </style>
    </head>
    <body>
        <main>
            <div class="mark" aria-hidden="true">◐</div>
            <h1>{{ config('app.name', 'Bleep') }} is being updated</h1>
            <p>
                We are making a few improvements and will be back shortly.
                Thanks for your patience.
            </p>

            @if ($estimatedReturn)
                <p class="eta">
                    Expected back around
                    <strong>{{ $estimatedReturn->timezone(config('app.timezone'))->format('H:i') }}</strong>
                    ({{ $estimatedReturn->diffForHumans() }}).
                </p>
            @elseif ($duration)
                <p class="eta">Estimated downtime: {{ $duration }}</p>
            @endif

            <footer>
                If this takes longer than expected, try again in a few minutes.
            </footer>
        </main>
    </body>
</html>
