@props([
    'id' => 'app-curtain',

    // Random "working" steps (3 are picked per load)
    'statuses' => [
        'Loading user client',
        'Preparing client data',
        'Fetching data from server',
        'Loading data from server',
        'Retrieving server data',
        'Fetching bleeps',
        'Fetching user data',
        'Fetching content',
        'Loading user data',
        'Loading bleeps',
        'Loading content data',
    ],

    // Always shown last (one is picked)
    'finishing' => [
        'Finishing setup',
        'Fetch complete',
        'Initiation complete',
        'Processing complete',
    ],

    // Occasionally slipped in the middle
    'easterEggs' => [
        // Minecraft
        'Punching trees',
        'Mining diamonds',
        'Avoiding creepers',
        'Sleeping through the night',
        // League of Legends
        'Waiting for minions to spawn',
        'Buying a control ward',
        'Recalling to base',
        // Overwatch
        'Charging ultimate',
        'Swapping to Mercy',
        'Heroes never die',
        // TV / movies / games
        'Winter is coming',
        'Assembling the Avengers',
        'Following the white rabbit',
        'Reaching 88 mph',
        'Hold the door',
        'Stay a while and listen',
        'The cake is a lie',
        "It's dangerous to go alone",
        'Doing a barrel roll',
        'Bazinga',
    ],

    'easterEggChance' => 0.35, // 0 disables, 1 = every load
])

{{-- NOTE: single-instance component. The script binds to the first
     [data-loading-curtain] on the page. --}}

@once
    {{-- Critical fallback: positions the overlay before the Tailwind bundle
         paints, and pauses animations while the curtain is hidden so the
         spinner/dots don't run forever behind the page. No colours here:
         unlayered CSS would beat the utility classes. --}}
    <style>
        [data-loading-curtain] {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: grid;
            place-items: center;
        }

        [data-loading-curtain][data-state="hidden"],
        [data-loading-curtain][data-state="hidden"] * {
            animation-play-state: paused !important;
        }
    </style>
@endonce

{{-- Starts hidden: with JS disabled/broken the page stays usable, and fast
     loads never flash the curtain. JS reveals it only if loading drags on. --}}
<div
    id="{{ $id }}"
    data-loading-curtain
    data-state="hidden"
    data-statuses='@json($statuses)'
    data-finishing='@json($finishing)'
    data-easter-eggs='@json($easterEggs)'
    data-easter-egg-chance="{{ $easterEggChance }}"
    class="pointer-events-none invisible fixed inset-0 z-9999 grid place-items-center bg-base-100/95 px-6 text-base-content opacity-0 backdrop-blur-sm transition-[opacity,visibility] duration-300"
    role="status"
    aria-hidden="true"
>
    {{-- Announced once; the rotating text below is decorative. --}}
    <span class="sr-only">Loading</span>

    <div class="mx-auto flex w-full max-w-sm flex-col items-center justify-center gap-3 text-center">
        <div class="relative grid size-20 place-items-center">
            <span class="absolute inset-0 rounded-full border-[3px] border-primary/20 border-t-primary motion-safe:animate-spin" aria-hidden="true"></span>
            <img
                src="{{ asset('Bleep_Icon.png') }}"
                alt=""
                width="52"
                height="52"
                decoding="async"
                class="relative size-12 rounded-xl drop-shadow-lg motion-safe:animate-curtain-float"
            >
        </div>

        <div class="text-base font-bold tracking-wide" aria-hidden="true">{{ config('app.name', 'Bleep') }}</div>

        <p class="flex items-center justify-center gap-1 text-sm text-base-content/70" aria-hidden="true">
            <span class="loading loading-dots loading-xs invisible" aria-hidden="true"></span>

            <span data-curtain-status class="text-center">{{ $statuses[0] ?? 'Loading' }}</span>

            <span class="loading loading-dots loading-xs translate-y-1" aria-hidden="true"></span>
        </p>
    </div>
</div>

@once
    <script>
        (() => {
            const curtain = document.querySelector('[data-loading-curtain]');
            if (!curtain) return;

            const statusEl = curtain.querySelector('[data-curtain-status]');
            const parse = (key) => JSON.parse(curtain.dataset[key] || '[]');

            const pools = {
                statuses: parse('statuses'),
                finishing: parse('finishing'),
                easterEggs: parse('easterEggs'),
                chance: Number(curtain.dataset.easterEggChance || 0),
            };

            const HIDDEN_CLASSES = ['opacity-0', 'invisible', 'pointer-events-none'];
            const INITIAL_DELAY_MS = 120;   // don't flash on fast loads
            const ROTATE_MS = 900;          // status text cadence
            const MAX_VISIBLE_MS = 4000;    // safety net for auto-hide
            const STEPS_PER_LOAD = 4;       // random working steps before the finish text

            let rotateTimer = null;
            let hideTimer = null;
            let initialTimer = null;
            let index = 0;

            const stopRotation = () => {
                window.clearInterval(rotateTimer);
                rotateTimer = null;
            };

            const setStatus = (text) => {
                if (statusEl && text) statusEl.textContent = text;
            };

            const pick = (items) => items[Math.floor(Math.random() * items.length)];

            const shuffle = (items) => {
                const copy = [...items];

                for (let i = copy.length - 1; i > 0; i--) {
                    const j = Math.floor(Math.random() * (i + 1));
                    [copy[i], copy[j]] = [copy[j], copy[i]];
                }

                return copy;
            };

            // Working steps -> (maybe) an easter egg in the middle -> a finishing line.
            const buildSequence = () => {
                const steps = shuffle(pools.statuses).slice(0, STEPS_PER_LOAD);

                if (pools.easterEggs.length && Math.random() < pools.chance) {
                    // Never first, so the opening always looks legit.
                    const at = 1 + Math.floor(Math.random() * Math.max(steps.length, 1));
                    steps.splice(at, 0, pick(pools.easterEggs));
                }

                if (pools.finishing.length) steps.push(pick(pools.finishing));

                return steps;
            };

            const startRotation = (sequence) => {
                stopRotation();
                if (!sequence.length) return;

                index = 0;
                setStatus(sequence[0]);

                if (sequence.length < 2) return;

                rotateTimer = window.setInterval(() => {
                    // Stop on the last (finishing) line instead of looping back to the start.
                    if (index >= sequence.length - 1) return stopRotation();

                    index += 1;
                    setStatus(sequence[index]);
                }, ROTATE_MS);
            };

            const hide = () => {
                window.clearTimeout(initialTimer);
                window.clearTimeout(hideTimer);
                stopRotation();

                curtain.classList.add(...HIDDEN_CLASSES);
                curtain.dataset.state = 'hidden';
                curtain.setAttribute('aria-hidden', 'true');
            };

            // Pass `statuses` to show an exact sequence; omit it for the generated one.
            const show = ({ statuses = null, autoHide = false } = {}) => {
                window.clearTimeout(initialTimer);
                window.clearTimeout(hideTimer);

                curtain.classList.remove(...HIDDEN_CLASSES);
                curtain.dataset.state = 'visible';
                curtain.removeAttribute('aria-hidden');

                startRotation(statuses ?? buildSequence());

                if (autoHide) {
                    hideTimer = window.setTimeout(hide, MAX_VISIBLE_MS);
                }
            };

                        window.LoadingCurtain = { show, hide };

            // Initial page load: only reveal the curtain if the document is
            // still parsing after a short delay. If the script runs after
            // DOMContentLoaded (e.g. injected by SPA-style navigation), the
            // page is already usable and there is nothing to show.
            if (document.readyState === 'loading') {
                initialTimer = window.setTimeout(() => show({ autoHide: true }), INITIAL_DELAY_MS);
                document.addEventListener('DOMContentLoaded', hide, { once: true });
            }

            // Back/forward cache restore: make sure we don't resurrect a visible curtain.
            window.addEventListener('pageshow', (event) => {
                if (event.persisted) hide();
            });

            // Keep the overlay up while an opted-in form is being processed.
            document.addEventListener('submit', (event) => {
                if (event.defaultPrevented) return;

                const form = event.target.closest?.('[data-loading-curtain-trigger]');
                if (!form) return;

                show({
                    statuses: [form.dataset.loadingCurtainTrigger || 'Loading'],
                    autoHide: false,
                });
            });

        })();
    </script>
@endonce
