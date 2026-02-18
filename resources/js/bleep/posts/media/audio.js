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
    } catch {
        /* no-op */
    }
}

function initAudioPlayers(container = document) {
    // If container is an event object (from addEventListener), use document instead
    if (container instanceof Event) {
        container = document;
    }

    // Ensure container has querySelectorAll method
    if (!container || typeof container.querySelectorAll !== 'function') {
        console.warn('Invalid container passed to initAudioPlayers, using document');
        container = document;
    }

    // console.log('initAudioPlayers called, container:', container);

    // Find all audio player wrappers in the specified container
    const wrappers = container.querySelectorAll('[data-bleep-media][data-audio-player]');

    // console.log('Found audio wrappers:', wrappers.length);

    wrappers.forEach((wrapper, index) => {
        // console.log(`Processing wrapper ${index + 1}/${wrappers.length}`);

        // Skip if already initialized
        if (wrapper.dataset.playerReady === 'true') {
            // console.log(`Wrapper ${index + 1} already initialized, skipping`);
            return;
        }
        wrapper.dataset.playerReady = 'true';

        const audioEl = wrapper.querySelector('audio.audio-element');
        if (!audioEl) {
            console.warn(`No audio element found in wrapper ${index + 1}`);
            return;
        }

        const audioId = audioEl.id;
        // console.log(`Initializing audio player: ${audioId}`);

        const btnPlay = wrapper.querySelector(`.audio-play-btn[data-audio-id="${audioId}"]`);
        const progressTrack = wrapper.querySelector('[data-audio-progress-track]') ?? wrapper.querySelector('.relative.cursor-pointer');
        const progressBar = wrapper.querySelector('.audio-progress');
        const bufferedBar = wrapper.querySelector('.audio-buffered');
        const hoverBar = wrapper.querySelector('.audio-hover-progress');
        const timeCurrent = wrapper.querySelector(`.audio-current-time[data-audio-id="${audioId}"]`);
        const timeTotal = wrapper.querySelector(`.audio-total-time[data-audio-id="${audioId}"]`);
        const volumeSlider = wrapper.querySelector(`.audio-volume-slider[data-audio-id="${audioId}"]`);
        const volumeBtn = wrapper.querySelector(`.audio-volume-btn[data-audio-id="${audioId}"]`);
        const speedBtn = wrapper.querySelector(`.audio-speed-btn[data-audio-id="${audioId}"]`);
        const speedOptions = wrapper.querySelectorAll('.audio-speed-option');
        const speedLabel = wrapper.querySelector('.audio-speed-label');

        // Check for required elements
        if (!btnPlay || !progressTrack || !progressBar) {
            console.warn('Missing required audio controls for:', audioId, {
                btnPlay: !!btnPlay,
                progressTrack: !!progressTrack,
                progressBar: !!progressBar
            });
            return;
        }

        const playIcon = btnPlay.querySelector('.play-icon');
        const pauseIcon = btnPlay.querySelector('.pause-icon');
        const loadingIcon = btnPlay.querySelector('.loading-icon');

        if (!playIcon || !pauseIcon || !loadingIcon) {
            console.warn('Missing play/pause icons for:', audioId);
            return;
        }

        const storedVolume = getStoredVolume();
        audioEl.volume = storedVolume;
        let lastVolume = storedVolume || 1;

        function setPlayState(isPlaying) {
            playIcon.style.display = isPlaying ? 'none' : '';
            pauseIcon.style.display = isPlaying ? '' : 'none';
            loadingIcon.style.display = 'none';
        }

        function setLoadingState() {
            playIcon.style.display = 'none';
            pauseIcon.style.display = 'none';
            loadingIcon.style.display = '';
        }

        function updateProgressUI() {
            if (!progressBar || !timeCurrent) return;
            const pct = (audioEl.currentTime / audioEl.duration) * 100;
            progressBar.style.width = `${pct || 0}%`;
            timeCurrent.textContent = formatTime(audioEl.currentTime);
        }

        function updateBufferedUI() {
            if (!bufferedBar || !audioEl.duration || audioEl.buffered.length === 0) return;
            const bufferedEnd = audioEl.buffered.end(audioEl.buffered.length - 1);
            const pct = (bufferedEnd / audioEl.duration) * 100;
            bufferedBar.style.width = `${Math.min(pct, 100)}%`;
        }

        function updateVolumeUI(vol) {
            if (!volumeBtn) return;

            const volumeHigh = volumeBtn.querySelector('.volume-high-icon');
            const volumeLow = volumeBtn.querySelector('.volume-low-icon');
            const volumeMute = volumeBtn.querySelector('.volume-mute-icon');

            if (volumeHigh) volumeHigh.style.display = vol > 0.5 ? '' : 'none';
            if (volumeLow) volumeLow.style.display = vol > 0 && vol <= 0.5 ? '' : 'none';
            if (volumeMute) volumeMute.style.display = vol === 0 ? '' : 'none';

            if (volumeSlider) {
                volumeSlider.value = Math.round(vol * 100);
            }
        }

        function canSeek() {
            return Number.isFinite(audioEl.duration) && audioEl.duration > 0;
        }

        function seekToClientX(clientX) {
            if (!canSeek()) return;
            const rect = progressTrack.getBoundingClientRect();
            const width = rect.width || 1;
            const pct = Math.min(Math.max((clientX - rect.left) / width, 0), 1);
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
                try {
                    progressTrack.releasePointerCapture(e.pointerId);
                } catch {}
                if (wasPlayingBeforeScrub) {
                    audioEl.play().catch(() => setPlayState(false));
                }
            });
        });

        progressTrack.addEventListener('click', (e) => {
            if (isScrubbing || !canSeek()) return;
            seekToClientX(e.clientX);
        });

        audioEl.addEventListener('seeked', updateProgressUI);

        btnPlay.addEventListener('click', () => {
            // Lazy load audio src if not yet loaded
            if (!audioEl.src && audioEl.dataset.src) {
                setLoadingState();
                audioEl.src = audioEl.dataset.src;
                audioEl.removeAttribute('data-src');
                audioEl.load();
            }

            if (audioEl.paused) {
                // Pause other audio players
                if (activeAudio && activeAudio !== audioEl) {
                    activeAudio.pause();
                }
                // Pause all videos when audio plays
                if (window.pauseAllVideos) {
                    window.pauseAllVideos();
                }
                audioEl.play().catch(() => setPlayState(false));
                activeAudio = audioEl;
            } else {
                audioEl.pause();
            }
        });

        audioEl.addEventListener('play', () => setPlayState(true));
        audioEl.addEventListener('pause', () => {
            if (activeAudio === audioEl && audioEl.paused) activeAudio = null;
            setPlayState(false);
        });
        audioEl.addEventListener('waiting', setLoadingState);
        audioEl.addEventListener('canplay', () => {
            if (timeTotal) {
                timeTotal.textContent = formatTime(audioEl.duration);
            }
            if (!audioEl.paused) setPlayState(true);
        });
        audioEl.addEventListener('loadedmetadata', () => {
            if (timeTotal) {
                timeTotal.textContent = formatTime(audioEl.duration);
            }
            updateProgressUI();
        });
        audioEl.addEventListener('timeupdate', updateProgressUI);
        audioEl.addEventListener('progress', updateBufferedUI);
        audioEl.addEventListener('ended', () => {
            audioEl.currentTime = 0;
            updateProgressUI();
            setPlayState(false);
            if (activeAudio === audioEl) activeAudio = null;
        });

        if (volumeSlider) {
            volumeSlider.addEventListener('input', (e) => {
                const vol = e.target.value / 100;
                audioEl.volume = vol;
                if (vol > 0) lastVolume = vol;
                updateVolumeUI(vol);
                setStoredVolume(audioEl.volume);
            });
        }

        if (volumeBtn) {
            volumeBtn.addEventListener('click', () => {
                if (audioEl.volume === 0) {
                    audioEl.volume = lastVolume || 1;
                } else {
                    lastVolume = audioEl.volume;
                    audioEl.volume = 0;
                }
                updateVolumeUI(audioEl.volume);
                setStoredVolume(audioEl.volume);
            });
        }

        if (speedOptions.length > 0) {
            speedOptions.forEach(option => {
                option.addEventListener('click', () => {
                    const rate = parseFloat(option.dataset.speed ?? '1');
                    audioEl.playbackRate = rate;
                    if (speedLabel) speedLabel.textContent = `${rate}x`;
                    speedOptions.forEach(btn => {
                        btn.classList.remove('active', 'bg-primary/20');
                    });
                    option.classList.add('active', 'bg-primary/20');
                });
            });
        }

        if (speedBtn) {
            speedBtn.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && speedOptions.length) {
                    const next = [...speedOptions].find(btn => btn.classList.contains('active')) || speedOptions[0];
                    if (next) next.click();
                }
            });
        }

        // Initialize UI
        updateVolumeUI(audioEl.volume);
        if (timeCurrent) timeCurrent.textContent = '0:00';
        if (timeTotal) timeTotal.textContent = audioEl.duration ? formatTime(audioEl.duration) : '0:00';

        // Force load metadata if not ready
        if (audioEl.readyState < 1) {
            audioEl.load();
        }

        // console.log('Audio player initialized successfully:', audioId);
    });
}

/**
 * Pause all audio players
 */
function pauseAllAudio() {
    if (activeAudio && !activeAudio.paused) {
        activeAudio.pause();
    }
    // Also pause any audio that might not be tracked
    document.querySelectorAll('audio').forEach((audio) => {
        if (!audio.paused) {
            audio.pause();
        }
    });
}

// Make globally accessible
window.initAudioPlayers = initAudioPlayers;
window.pauseAllAudio = pauseAllAudio;

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    // console.log('DOMContentLoaded - initializing audio players');
    initAudioPlayers();
});

// Reinitialize when new content is loaded
document.addEventListener('bleeps:media:hydrated', () => {
    // console.log('bleeps:media:hydrated - reinitializing audio players');
    setTimeout(() => initAudioPlayers(), 100);
});
