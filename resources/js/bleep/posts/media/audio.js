let activeAudio = null;
const VOLUME_STORAGE_KEY = 'bleepAudioVolume';

function formatTime(seconds) {
    if (!Number.isFinite(seconds)) return '0:00';
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60).toString().padStart(2, '0');
    return `${mins}:${secs}`;
}

function getStoredVolume() {
    try {
        const raw = localStorage.getItem(VOLUME_STORAGE_KEY);
        const vol = parseFloat(raw);
        return Number.isFinite(vol) ? Math.min(Math.max(vol, 0), 1) : 1;
    } catch {
        return 1;
    }
}

function setStoredVolume(vol) {
    try {
        localStorage.setItem(VOLUME_STORAGE_KEY, String(vol));
    } catch { /* no-op */ }
}

function initAudioPlayers(container = document) {
    if (container instanceof Event) container = document;
    if (!container || typeof container.querySelectorAll !== 'function') {
        container = document;
    }

    const wrappers = container.querySelectorAll('[data-bleep-media][data-audio-player]');

    wrappers.forEach((wrapper) => {
        if (wrapper.dataset.playerReady === 'true') return;
        wrapper.dataset.playerReady = 'true';

        const audioEl = wrapper.querySelector('audio.audio-element');
        if (!audioEl) return;

        const audioId = audioEl.id;

        const btnPlay       = wrapper.querySelector(`.audio-play-btn[data-audio-id="${audioId}"]`);
        const progressTrack = wrapper.querySelector('[data-audio-progress-track]');
        const progressBar   = wrapper.querySelector('.audio-progress');
        const bufferedBar   = wrapper.querySelector('.audio-buffered');
        const timeCurrent   = wrapper.querySelector(`.audio-current-time[data-audio-id="${audioId}"]`);
        const timeTotal     = wrapper.querySelector(`.audio-total-time[data-audio-id="${audioId}"]`);
        const speedLabel    = wrapper.querySelector('.audio-speed-label');
        const speedOptions  = wrapper.querySelectorAll('.audio-speed-option');
        const speedBtn      = wrapper.querySelector(`.audio-speed-btn[data-audio-id="${audioId}"]`);

        // Volume elements — may be a range input or a button (Blade uses Alpine popup, Vue uses its own)
        const volumeSlider  = wrapper.querySelector(`.audio-volume-slider[data-audio-id="${audioId}"]`);

        if (!btnPlay || !progressTrack || !progressBar) return;

        const playIcon    = btnPlay.querySelector('.play-icon');
        const pauseIcon   = btnPlay.querySelector('.pause-icon');
        const loadingIcon = btnPlay.querySelector('.loading-icon');
        if (!playIcon || !pauseIcon || !loadingIcon) return;

        // ── Volume ─────────────────────────────────────────────────────────────
        const storedVolume = getStoredVolume();
        audioEl.volume = storedVolume;
        let lastVolume = storedVolume || 1;

        /** Sync any range slider inside this wrapper to the given 0-1 value */
        function syncSliderUI(vol) {
            wrapper.querySelectorAll('.audio-volume-slider').forEach(slider => {
                slider.value = Math.round(vol * 100);
            });
        }

        /**
         * Derive the correct Lucide icon name from a 0–1 volume value.
         * Rules: 0 → volume-x | 0.01–0.5 → volume-1 | >0.5 → volume-2
         */
        function volumeIconName(vol01) {
            if (vol01 === 0)   return 'volume-x';
            if (vol01 <= 0.5)  return 'volume-1';
            return 'volume-2';
        }

        /**
         * Sync all volume icons in this wrapper.
         * - For Blade/Alpine: dispatches 'bleep:volume-icon' on the wrapper so Alpine x-on can react.
         * - For Vue: Vue listens to the same event and updates its reactive ref.
         * - Also directly sets data-lucide on any static <i data-lucide> elements and re-renders.
         */
        function syncVolumeIcon(vol01) {
            const iconName = volumeIconName(vol01);

            // Dispatch a custom event so Alpine (Blade) and Vue can react declaratively
            wrapper.dispatchEvent(new CustomEvent('bleep:volume-icon', {
                detail: { icon: iconName, volume: vol01, volumePct: Math.round(vol01 * 100) },
                bubbles: false,
            }));

            // Also imperatively update any static data-lucide <i> elements
            // (covers Blade when Alpine hasn't re-rendered yet)
            wrapper.querySelectorAll('[data-audio-volume-icon]').forEach(el => {
                el.setAttribute('data-lucide', iconName);
            });
            // Re-render via lucide if available
            if (window.lucide?.createIcons) {
                wrapper.querySelectorAll('[data-audio-volume-icon]').forEach(el => {
                    window.lucide.createIcons({ el });
                });
            }
        }

        function applyVolume(vol01) {
            audioEl.volume = vol01;
            if (vol01 > 0) lastVolume = vol01;
            setStoredVolume(vol01);
            syncSliderUI(vol01);
            syncVolumeIcon(vol01);
        }

        // Listen to native range slider input (works for both Blade and Vue)
        wrapper.addEventListener('input', (e) => {
            if (!e.target.matches('.audio-volume-slider')) return;
            applyVolume(Number(e.target.value) / 100);
        });

        // Listen to the custom event dispatched by Alpine (Blade component).
        // Alpine's $dispatch bubbles, so listening on wrapper catches it.
        wrapper.addEventListener('audio-volume-change', (e) => {
            applyVolume(Number(e.detail) / 100);
        });

        // Initialise slider values + icon
        syncSliderUI(storedVolume);
        syncVolumeIcon(storedVolume);

        // ── Play state helpers ─────────────────────────────────────────────────
        function setPlayState(isPlaying) {
            playIcon.style.display    = isPlaying ? 'none' : '';
            pauseIcon.style.display   = isPlaying ? '' : 'none';
            loadingIcon.style.display = 'none';
        }

        function setLoadingState() {
            playIcon.style.display    = 'none';
            pauseIcon.style.display   = 'none';
            loadingIcon.style.display = '';
        }

        // ── Progress ───────────────────────────────────────────────────────────
        function updateProgressUI() {
            if (!progressBar || !timeCurrent) return;
            const pct = (audioEl.currentTime / audioEl.duration) * 100;
            progressBar.style.width = `${pct || 0}%`;
            timeCurrent.textContent = formatTime(audioEl.currentTime);
        }

        function updateBufferedUI() {
            if (!bufferedBar || !audioEl.duration || audioEl.buffered.length === 0) return;
            const bufferedEnd = audioEl.buffered.end(audioEl.buffered.length - 1);
            bufferedBar.style.width = `${Math.min((bufferedEnd / audioEl.duration) * 100, 100)}%`;
        }

        function canSeek() {
            return Number.isFinite(audioEl.duration) && audioEl.duration > 0;
        }

        function seekToClientX(clientX) {
            if (!canSeek()) return;
            const rect = progressTrack.getBoundingClientRect();
            const pct  = Math.min(Math.max((clientX - rect.left) / (rect.width || 1), 0), 1);
            audioEl.currentTime = pct * audioEl.duration;
            updateProgressUI();
        }

        let isScrubbing = false;
        let wasPlayingBeforeScrub = false;

        progressTrack.addEventListener('pointerdown', (e) => {
            if (!canSeek()) return;
            isScrubbing = true;
            wasPlayingBeforeScrub = !audioEl.paused;
            if (wasPlayingBeforeScrub) audioEl.pause();
            progressTrack.setPointerCapture(e.pointerId);
            seekToClientX(e.clientX);
        });

        progressTrack.addEventListener('pointermove', (e) => {
            if (!isScrubbing) return;
            e.preventDefault();
            seekToClientX(e.clientX);
        });

        ['pointerup', 'pointercancel', 'pointerleave'].forEach(evt => {
            progressTrack.addEventListener(evt, (e) => {
                if (!isScrubbing) return;
                isScrubbing = false;
                try { progressTrack.releasePointerCapture(e.pointerId); } catch {}
                if (wasPlayingBeforeScrub) audioEl.play().catch(() => setPlayState(false));
            });
        });

        progressTrack.addEventListener('click', (e) => {
            if (isScrubbing || !canSeek()) return;
            seekToClientX(e.clientX);
        });

        audioEl.addEventListener('seeked', updateProgressUI);

        // ── Play / Pause ───────────────────────────────────────────────────────
        btnPlay.addEventListener('click', () => {
            if (!audioEl.src && audioEl.dataset.src) {
                setLoadingState();
                audioEl.src = audioEl.dataset.src;
                audioEl.removeAttribute('data-src');
                audioEl.load();
            }

            if (audioEl.paused) {
                if (activeAudio && activeAudio !== audioEl) activeAudio.pause();
                if (window.pauseAllVideos) window.pauseAllVideos();
                audioEl.play().catch(() => setPlayState(false));
                activeAudio = audioEl;
            } else {
                audioEl.pause();
            }
        });

        audioEl.addEventListener('play',    () => setPlayState(true));
        audioEl.addEventListener('pause',   () => {
            if (activeAudio === audioEl && audioEl.paused) activeAudio = null;
            setPlayState(false);
        });
        audioEl.addEventListener('waiting', setLoadingState);
        audioEl.addEventListener('canplay', () => {
            if (timeTotal) timeTotal.textContent = formatTime(audioEl.duration);
            if (!audioEl.paused) setPlayState(true);
        });
        audioEl.addEventListener('loadedmetadata', () => {
            if (timeTotal) timeTotal.textContent = formatTime(audioEl.duration);
            updateProgressUI();
        });
        audioEl.addEventListener('timeupdate', updateProgressUI);
        audioEl.addEventListener('progress',   updateBufferedUI);
        audioEl.addEventListener('ended', () => {
            audioEl.currentTime = 0;
            updateProgressUI();
            setPlayState(false);
            if (activeAudio === audioEl) activeAudio = null;
        });

        // ── Speed ──────────────────────────────────────────────────────────────
        speedOptions.forEach(option => {
            option.addEventListener('click', () => {
                const rate = parseFloat(option.dataset.speed ?? '1');
                audioEl.playbackRate = rate;
                if (speedLabel) speedLabel.textContent = `${rate}x`;
                speedOptions.forEach(btn => btn.classList.remove('active', 'bg-primary/20', 'font-semibold'));
                option.classList.add('active', 'bg-primary/20', 'font-semibold');
            });
        });

        if (speedBtn) {
            speedBtn.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && speedOptions.length) {
                    const active = [...speedOptions].find(b => b.classList.contains('active')) || speedOptions[0];
                    if (active) active.click();
                }
            });
        }

        // ── Init UI ────────────────────────────────────────────────────────────
        if (timeCurrent) timeCurrent.textContent = '0:00';
        if (timeTotal)   timeTotal.textContent   = audioEl.duration ? formatTime(audioEl.duration) : '0:00';
        if (audioEl.readyState < 1) audioEl.load();
    });
}

function pauseAllAudio() {
    if (activeAudio && !activeAudio.paused) activeAudio.pause();
    document.querySelectorAll('audio').forEach(a => { if (!a.paused) a.pause(); });
}

window.initAudioPlayers = initAudioPlayers;
window.pauseAllAudio    = pauseAllAudio;

document.addEventListener('DOMContentLoaded', () => initAudioPlayers());
document.addEventListener('bleeps:media:hydrated', () => setTimeout(() => initAudioPlayers(), 100));
