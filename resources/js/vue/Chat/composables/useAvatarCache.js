import { ref, watch } from 'vue';
import { getCachedAvatar, cacheAvatar } from '../store/chatCache';

const DEFAULT_AVATAR = '/images/avatar/default.jpg';
const resolvedCache = new Map(); // in-memory fast lookup per session

/**
 * Resolves an avatar URL, returning a cached blob URL if available.
 * Falls back to the original URL while caching in the background.
 */
export const useAvatarCache = (urlRef) => {
    const src = ref(DEFAULT_AVATAR);

    const resolve = async (url) => {
        if (!url || url === DEFAULT_AVATAR) {
            src.value = DEFAULT_AVATAR;
            return;
        }

        // Fast in-memory hit
        if (resolvedCache.has(url)) {
            src.value = resolvedCache.get(url);
            return;
        }

        // Show original URL immediately (no flash of default)
        src.value = url;

        // Check IndexedDB cache
        const cached = await getCachedAvatar(url);
        if (cached) {
            resolvedCache.set(url, cached);
            src.value = cached;
            return;
        }

        // Cache in background (fetch → blob → IndexedDB)
        cacheAvatar(url).then((blobUrl) => {
            if (blobUrl && blobUrl !== url) {
                resolvedCache.set(url, blobUrl);
                src.value = blobUrl;
            }
        });
    };

    // React to URL changes
    if (urlRef && typeof urlRef === 'object' && 'value' in urlRef) {
        watch(urlRef, (newUrl) => resolve(newUrl), { immediate: true });
    } else {
        resolve(urlRef);
    }

    return src;
};
