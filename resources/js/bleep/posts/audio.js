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

function initAudioPlayers() {
    document.querySelectorAll('[data-bleep-media][data-audio-player]').forEach(wrapper => {
        if (wrapper.dataset.playerReady) return;
        wrapper.dataset.playerReady = 'true';

        const audioEl = wrapper.querySelector('audio.audio-element');
        if (!audioEl) return;

        const audioId = audioEl.id;

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
        const playIcon = btnPlay.querySelector('.play-icon');
        const pauseIcon = btnPlay.querySelector('.pause-icon');
        const loadingIcon = btnPlay.querySelector('.loading-icon');

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
            const pct = (audioEl.currentTime / audioEl.duration) * 100;
            progressBar.style.width = `${pct || 0}%`;
            timeCurrent.textContent = formatTime(audioEl.currentTime);
        }

        function updateBufferedUI() {
            if (!audioEl.duration || audioEl.buffered.length === 0) return;
            const bufferedEnd = audioEl.buffered.end(audioEl.buffered.length - 1);
            const pct = (bufferedEnd / audioEl.duration) * 100;
            bufferedBar.style.width = `${Math.min(pct, 100)}%`;
        }

        function updateVolumeUI(vol) {
            const volumeHigh = volumeBtn.querySelector('.volume-high-icon');
            const volumeLow = volumeBtn.querySelector('.volume-low-icon');
            const volumeMute = volumeBtn.querySelector('.volume-mute-icon');

            volumeHigh.style.display = vol > 0.5 ? '' : 'none';
            volumeLow.style.display = vol > 0 && vol <= 0.5 ? '' : 'none';
            volumeMute.style.display = vol === 0 ? '' : 'none';

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
            if (audioEl.paused) {
                if (activeAudio && activeAudio !== audioEl) {
                    activeAudio.pause();
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
            timeTotal.textContent = formatTime(audioEl.duration);
            if (!audioEl.paused) setPlayState(true);
        });
        audioEl.addEventListener('timeupdate', updateProgressUI);
        audioEl.addEventListener('progress', updateBufferedUI);
        audioEl.addEventListener('ended', () => {
            audioEl.currentTime = 0;
            updateProgressUI();
            setPlayState(false);
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

        speedOptions.forEach(option => {
            option.addEventListener('click', () => {
                const rate = parseFloat(option.dataset.speed ?? '1');
                audioEl.playbackRate = rate;
                speedLabel.textContent = `${rate}x`;
                speedOptions.forEach(btn => btn.classList.toggle('active', btn === option));
                option.classList.toggle('bg-primary/20', true);
            });
        });

        if (speedBtn) {
            speedBtn.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && speedOptions.length) {
                    const next = [...speedOptions].find(btn => btn.classList.contains('active')) || speedOptions[0];
                    next.click();
                }
            });
        }

        updateVolumeUI(audioEl.volume);
        timeCurrent.textContent = '0:00';
        timeTotal.textContent = audioEl.duration ? formatTime(audioEl.duration) : '0:00';
    });
}

// Make globally accessible for comments
window.initAudioPlayers = initAudioPlayers;

document.addEventListener('DOMContentLoaded', initAudioPlayers);
document.addEventListener('bleeps:media:hydrated', initAudioPlayers);
