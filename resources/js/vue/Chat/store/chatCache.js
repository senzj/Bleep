/**
 * IndexedDB cache for chat data.
 *
 * Stores: conversations, messages (keyed by conversationId), avatars.
 * All records auto-expire after 24 hours.
 */

const DB_NAME = 'bleep_chat_cache';
const DB_VERSION = 1;
const TTL_MS = 24 * 60 * 60 * 1000; // 24 hours

let dbPromise = null;

const open = () => {
    if (dbPromise) return dbPromise;

    dbPromise = new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains('conversations')) {
                db.createObjectStore('conversations', { keyPath: 'key' });
            }
            if (!db.objectStoreNames.contains('messages')) {
                db.createObjectStore('messages', { keyPath: 'conversationId' });
            }
            if (!db.objectStoreNames.contains('avatars')) {
                db.createObjectStore('avatars', { keyPath: 'url' });
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });

    return dbPromise;
};

const tx = async (storeName, mode = 'readonly') => {
    const db = await open();
    return db.transaction(storeName, mode).objectStore(storeName);
};

const idbGet = (store, key) =>
    new Promise((resolve) => {
        const req = store.get(key);
        req.onsuccess = () => resolve(req.result ?? null);
        req.onerror = () => resolve(null);
    });

const idbPut = (store, value) =>
    new Promise((resolve) => {
        const req = store.put(value);
        req.onsuccess = () => resolve();
        req.onerror = () => resolve();
    });

const idbGetAll = (store) =>
    new Promise((resolve) => {
        const req = store.getAll();
        req.onsuccess = () => resolve(req.result ?? []);
        req.onerror = () => resolve([]);
    });

const idbDelete = (store, key) =>
    new Promise((resolve) => {
        const req = store.delete(key);
        req.onsuccess = () => resolve();
        req.onerror = () => resolve();
    });

// ── Conversations ──

export const getCachedConversations = async () => {
    try {
        const store = await tx('conversations');
        const row = await idbGet(store, 'list');
        if (!row || !row.data) return null;
        if (Date.now() - row.cachedAt > TTL_MS) return null;
        return row.data;
    } catch {
        return null;
    }
};

export const setCachedConversations = async (conversations) => {
    try {
        const store = await tx('conversations', 'readwrite');
        await idbPut(store, { key: 'list', data: conversations, cachedAt: Date.now() });
    } catch { /* ignore */ }
};

// ── Messages (per conversation) ──

export const getCachedMessages = async (conversationId) => {
    try {
        const store = await tx('messages');
        const row = await idbGet(store, Number(conversationId));
        if (!row || !row.data) return null;
        if (Date.now() - row.cachedAt > TTL_MS) return null;
        return row.data;
    } catch {
        return null;
    }
};

export const setCachedMessages = async (conversationId, messages) => {
    try {
        const store = await tx('messages', 'readwrite');
        await idbPut(store, {
            conversationId: Number(conversationId),
            data: messages,
            cachedAt: Date.now(),
        });
    } catch { /* ignore */ }
};

// ── Avatars ──

export const getCachedAvatar = async (url) => {
    try {
        const store = await tx('avatars');
        const row = await idbGet(store, url);
        if (!row || !row.blobUrl) return null;
        if (Date.now() - row.cachedAt > TTL_MS) return null;
        return row.blobUrl;
    } catch {
        return null;
    }
};

export const cacheAvatar = async (url) => {
    if (!url || url.startsWith('blob:') || url.startsWith('data:')) return url;
    try {
        // Check if already cached
        const existing = await getCachedAvatar(url);
        if (existing) return existing;

        const response = await fetch(url);
        if (!response.ok) return url;
        const blob = await response.blob();
        const blobUrl = URL.createObjectURL(blob);

        const store = await tx('avatars', 'readwrite');
        await idbPut(store, { url, blobUrl, cachedAt: Date.now() });
        return blobUrl;
    } catch {
        return url;
    }
};

// ── Cleanup (purge expired entries) ──

export const purgeExpired = async () => {
    try {
        const now = Date.now();

        // Conversations
        const convStore = await tx('conversations', 'readwrite');
        const convRow = await idbGet(convStore, 'list');
        if (convRow && now - convRow.cachedAt > TTL_MS) {
            await idbDelete(convStore, 'list');
        }

        // Messages
        const msgStore = await tx('messages', 'readwrite');
        const allMessages = await idbGetAll(msgStore);
        for (const row of allMessages) {
            if (now - row.cachedAt > TTL_MS) {
                await idbDelete(msgStore, row.conversationId);
            }
        }

        // Avatars
        const avatarStore = await tx('avatars', 'readwrite');
        const allAvatars = await idbGetAll(avatarStore);
        for (const row of allAvatars) {
            if (now - row.cachedAt > TTL_MS) {
                if (row.blobUrl) URL.revokeObjectURL(row.blobUrl);
                await idbDelete(avatarStore, row.url);
            }
        }
    } catch { /* ignore */ }
};
