import { reactive, watch } from 'vue';

const STORAGE_KEY = 'bleep_chat_prefs_v1';

export const BUBBLE_STYLES = [
    { value: 'default', label: 'Default', mine: 'chat-bubble-primary', theirs: 'chat-bubble-secondary' },
    { value: 'mint', label: 'Mint', mine: 'chat-bubble-success', theirs: 'chat-bubble-neutral' },
    { value: 'grape', label: 'Grape', mine: 'chat-bubble-secondary', theirs: 'chat-bubble-accent' },
    { value: 'mono', label: 'Monochrome', mine: 'chat-bubble-neutral', theirs: 'chat-bubble-base-300' },
    { value: 'ember', label: 'Ember', mine: 'chat-bubble-error', theirs: 'chat-bubble-base-300' },
];

export const CHAT_BACKGROUNDS = [
    { value: 'default', label: 'Plain' },
    { value: 'dots', label: 'Dots' },
    { value: 'grid', label: 'Grid' },
    { value: 'warm', label: 'Warm' },
    { value: 'night', label: 'Night' },
];

const defaults = () => ({
    background: 'default',
    bubbleStyle: 'default',
    showSeen: true,
    nicknames: {},
});

const read = () => {
    try {
        const stored = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
        return { ...defaults(), ...stored, nicknames: { ...(stored.nicknames || {}) } };
    } catch {
        return defaults();
    }
};

const preferences = reactive(read());

watch(
    preferences,
    (value) => {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(value));
        } catch {
            // Storage can be unavailable in private mode; preferences still apply
            // for this tab.
        }
    },
    { deep: true }
);

const bubbleStyle = (name) => BUBBLE_STYLES.find((style) => style.value === name) || BUBBLE_STYLES[0];

export const useChatPreferences = () => {
    const bubbleClass = (mine) => {
        const style = bubbleStyle(preferences.bubbleStyle);
        return mine ? style.mine : style.theirs;
    };

    const nicknameFor = (user) => {
        if (!user) return '';
        const nickname = preferences.nicknames[user.id] ?? preferences.nicknames[String(user.id)];
        return (nickname || '').trim();
    };

    const displayName = (user, fallback = '') => nicknameFor(user) || user?.dname || user?.username || fallback;

    const setNickname = (userId, nickname) => {
        const trimmed = (nickname || '').trim();
        if (trimmed) {
            preferences.nicknames[String(userId)] = trimmed;
        } else {
            delete preferences.nicknames[String(userId)];
        }
    };

    const reset = () => {
        preferences.background = 'default';
        preferences.bubbleStyle = 'default';
        preferences.showSeen = true;
        preferences.nicknames = {};
    };

    return {
        preferences,
        bubbleClass,
        nicknameFor,
        displayName,
        setNickname,
        reset,
    };
};
