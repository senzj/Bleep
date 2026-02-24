// ── Read user preferences from meta tags
function getSendSoundPrefs() {
    const enabled  = document.querySelector('meta[name="enable-send"]')?.content;
    const rawValue = document.querySelector('meta[name="send-sound"]')?.content || '/sounds/effects/bloop-1.mp3';
    return {
        enabled:  enabled !== 'false' && enabled !== '0',
        rawValue: rawValue.trim(),
    };
}

// ── Named shortcuts (backwards-compat for old 'default' DB rows)
const NAMED_SHORTCUTS = {
    'default':   '/sounds/effects/bloop-1.mp3',
    'messenger': '/sounds/effects/marimba-bloop-1.mp3',
    'ping':      '/sounds/effects/bloop-1.mp3',
    'none':      null,
};

// ── Shared AudioContext (lazy)
let _ctx = null;
function getAudioContext() {
    if (!_ctx) _ctx = new (window.AudioContext || window.webkitAudioContext)();
    if (_ctx.state === 'suspended') _ctx.resume();
    return _ctx;
}

// ── Pre-load cache — keyed by path, so repeated calls are instant
const _audioCache = {};

/**
 * Play an MP3 at `path`. Falls back to synthFallback() on any error.
 */
function playFile(path, synthFallback) {
    if (!path) return;
    try {
        if (!_audioCache[path]) {
            _audioCache[path] = new Audio(path);
            _audioCache[path].load(); // start buffering immediately
        }
        const audio = _audioCache[path];
        audio.currentTime = 0; // rewind so rapid posts all play
        audio.play().catch(() => synthFallback?.());
    } catch {
        synthFallback?.();
    }
}

// ── Synthesised fallback (fires only when the MP3 is missing or blocked)
function synthDefault() {
    const ctx  = getAudioContext();
    const now  = ctx.currentTime;
    const osc  = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.type = 'sine';
    osc.frequency.setValueAtTime(400, now);
    osc.frequency.exponentialRampToValueAtTime(900, now + 0.12);
    gain.gain.setValueAtTime(0, now);
    gain.gain.linearRampToValueAtTime(0.28, now + 0.02);
    gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.22);
    osc.start(now);
    osc.stop(now + 0.25);
}

// ── Public API
/**
 * Call after any successful post/comment/reply submission.
 * Safe to call even on very old browsers — never throws.
 */
function playSendSound() {
    try {
        const { enabled, rawValue } = getSendSoundPrefs();
        if (!enabled) return;

        // Resolve named shortcut → path, or treat rawValue as the path directly.
        const path = Object.prototype.hasOwnProperty.call(NAMED_SHORTCUTS, rawValue)
            ? NAMED_SHORTCUTS[rawValue]
            : rawValue;

        playFile(path, synthDefault);
    } catch (err) {
        console.warn('[send-sound] Failed to play sound:', err);
    }
}

window.playSendSound = playSendSound;

// ── Auto-hook the Blade post form
function hookBleepForm() {
    document.addEventListener('bleep:posted', () => playSendSound());

    const form = document.getElementById('bleep-form');
    if (form) {
        form.addEventListener('submit', () => setTimeout(playSendSound, 50));
    }
}

document.addEventListener('DOMContentLoaded', hookBleepForm);

export { playSendSound };
