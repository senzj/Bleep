/**
 * Resumable media upload manager.
 *
 * Files above the configured threshold are uploaded in chunks and tracked in
 * IndexedDB. The file blob itself is stored so a full page reload can resume
 * the transfer (and restore the composer preview) instead of starting over.
 */
(() => {
    const DB_NAME = 'bleep-media-uploads';
    const DB_VERSION = 1;
    const TASK_STORE = 'tasks';
    const BLOB_STORE = 'blobs';

    const metaNumber = (name, fallback) => {
        const value = Number(document.querySelector(`meta[name="${name}"]`)?.content);

        return Number.isFinite(value) && value > 0 ? value : fallback;
    };

    const baseUrl = (document.querySelector('meta[name="base_url"]')?.content || window.location.origin)
        .replace(/\/+$/, '');

    const chunkThreshold = metaNumber('media-chunked-threshold', 8 * 1024 * 1024);
    const fallbackChunkSize = metaNumber('media-chunk-size', 8 * 1024 * 1024);
    const maxFileSize = metaNumber('media-max-file-size', 2 * 1024 * 1024 * 1024);

    // Three requests in flight keeps a single-threaded PHP dev server busy
    // without flooding it; production servers benefit from the parallelism.
    const chunkConcurrency = 3;

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    /** @type {Map<string, object>} */
    const tasks = new Map();
    /** @type {Map<string, string>} */
    const signatureToKey = new Map();

    let dbPromise = null;
    let memoryMode = false;
    const memoryTasks = new Map();
    const memoryBlobs = new Map();
    let emitScheduled = false;

    const newKey = () => (crypto.randomUUID ? crypto.randomUUID() : `u-${Date.now()}-${Math.random().toString(16).slice(2)}`);

    const signature = (file) => `${file.name}|${file.size}|${file.lastModified}`;

    const abortError = () => {
        const error = new Error('Upload cancelled.');
        error.name = 'AbortError';

        return error;
    };

    // ── IndexedDB helpers ────────────────────────────────────────────────────

    const openDb = () => {
        if (dbPromise) return dbPromise;

        dbPromise = new Promise((resolve, reject) => {
            if (!('indexedDB' in window)) {
                memoryMode = true;
                resolve(null);

                return;
            }

            const request = indexedDB.open(DB_NAME, DB_VERSION);

            request.onupgradeneeded = () => {
                const db = request.result;

                if (!db.objectStoreNames.contains(TASK_STORE)) {
                    const store = db.createObjectStore(TASK_STORE, { keyPath: 'key' });
                    store.createIndex('context', 'context');
                }

                if (!db.objectStoreNames.contains(BLOB_STORE)) {
                    db.createObjectStore(BLOB_STORE, { keyPath: 'key' });
                }
            };

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => {
                memoryMode = true;
                resolve(null);
            };
        });

        return dbPromise;
    };

    const withStore = async (storeName, mode, callback) => {
        const db = await openDb();

        if (!db) return null;

        return new Promise((resolve, reject) => {
            const transaction = db.transaction(storeName, mode);
            const store = transaction.objectStore(storeName);
            const request = callback(store);

            transaction.oncomplete = () => resolve(request?.result ?? null);
            transaction.onerror = () => reject(transaction.error);
            transaction.onabort = () => reject(transaction.error);
        });
    };

    const serializeTask = (task) => ({
        key: task.key,
        context: task.context,
        name: task.name,
        size: task.size,
        type: task.type,
        lastModified: task.lastModified,
        uploadId: task.uploadId,
        received: [...task.received],
        totalChunks: task.totalChunks,
        chunkSize: task.chunkSize,
        completedBytes: task.completedBytes,
        status: task.status,
        error: task.error,
        createdAt: task.createdAt,
        updatedAt: Date.now(),
    });

    const saveTask = async (task) => {
        const payload = serializeTask(task);

        if (memoryMode) {
            memoryTasks.set(task.key, payload);

            return;
        }

        try {
            await withStore(TASK_STORE, 'readwrite', (store) => store.put(payload));
        } catch {
            memoryMode = true;
            memoryTasks.set(task.key, payload);
        }
    };

    const saveBlob = async (task) => {
        if (memoryMode) {
            memoryBlobs.set(task.key, task.file);

            return;
        }

        try {
            await withStore(BLOB_STORE, 'readwrite', (store) => store.put({ key: task.key, blob: task.file }));
        } catch {
            // A quota error must not stop the transfer; the file is still in memory.
        }
    };

    const deleteStoredTask = async (key) => {
        if (memoryMode) {
            memoryTasks.delete(key);
            memoryBlobs.delete(key);

            return;
        }

        try {
            await withStore(TASK_STORE, 'readwrite', (store) => store.delete(key));
            await withStore(BLOB_STORE, 'readwrite', (store) => store.delete(key));
        } catch {
            // Ignore storage cleanup failures.
        }
    };

    const readAllStoredTasks = async () => {
        const stored = [];

        if (memoryMode) {
            return [...memoryTasks.values()];
        }

        try {
            const db = await openDb();

            if (!db) return memoryTasks.size ? [...memoryTasks.values()] : [];

            await new Promise((resolve, reject) => {
                const transaction = db.transaction(TASK_STORE, 'readonly');
                const store = transaction.objectStore(TASK_STORE);
                const request = store.getAll();

                request.onsuccess = () => {
                    stored.push(...(request.result || []));
                };
                transaction.oncomplete = () => resolve();
                transaction.onerror = () => reject(transaction.error);
            });
        } catch {
            return [...memoryTasks.values()];
        }

        return stored;
    };

    const readBlob = async (key) => {
        if (memoryMode) return memoryBlobs.get(key) || null;

        try {
            const record = await withStore(BLOB_STORE, 'readonly', (store) => store.get(key));

            return record?.blob || null;
        } catch {
            return null;
        }
    };

    // ── Task state ───────────────────────────────────────────────────────────

    const chunkCountFor = (size, chunkSize) => Math.max(1, Math.ceil(size / chunkSize));

    const completedBytesFor = (task) => task.received.reduce(
        (total, index) => total + Math.min(task.chunkSize, task.size - index * task.chunkSize),
        0,
    );

    const inFlightBytesFor = (task) => {
        if (!task.inFlight?.size) return 0;

        let total = 0;

        task.inFlight.forEach((loaded) => {
            total += loaded;
        });

        return total;
    };

    const doneBytesFor = (task) => Math.min(
        task.size,
        Math.max(completedBytesFor(task), task.completedBytes || 0) + inFlightBytesFor(task),
    );

    const progressOf = (task) => {
        if (task.status === 'complete') return 100;
        if (!task.size) return 0;

        return Math.min(99, Math.round((doneBytesFor(task) / task.size) * 100));
    };

    const summaryOf = (task) => ({
        key: task.key,
        context: task.context,
        name: task.name,
        size: task.size,
        type: task.type,
        status: task.status,
        error: task.error,
        progress: progressOf(task),
        uploadId: task.uploadId,
    });

    const emit = () => {
        if (emitScheduled) return;
        emitScheduled = true;

        const dispatch = () => {
            emitScheduled = false;
            const detail = { tasks: [...tasks.values()].map(summaryOf) };
            window.dispatchEvent(new CustomEvent('media-upload:update', { detail }));
        };

        if (typeof requestAnimationFrame === 'function') {
            requestAnimationFrame(dispatch);
        } else {
            setTimeout(dispatch, 16);
        }
    };

    const createTask = (file, context) => {
        const task = {
            key: newKey(),
            context,
            file,
            name: file.name,
            size: file.size,
            type: file.type || 'application/octet-stream',
            lastModified: file.lastModified,
            uploadId: null,
            received: [],
            totalChunks: 0,
            chunkSize: fallbackChunkSize,
            status: 'queued',
            error: null,
            createdAt: Date.now(),
            currentXhr: null,
            xhrs: new Set(),
            inFlight: new Map(),
            promise: null,
        };

        tasks.set(task.key, task);
        signatureToKey.set(signature(file), task.key);

        return task;
    };

    const parseError = (xhr, fallback) => {
        try {
            const body = JSON.parse(xhr.responseText || '{}');
            const first = Object.values(body.errors || {}).flat()?.[0];

            return body.message || first || fallback;
        } catch {
            return fallback;
        }
    };

    const requestJson = (url, options = {}) => fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
            ...(options.headers || {}),
        },
    }).then(async (response) => {
        const body = await response.json().catch(() => ({}));

        if (!response.ok) {
            const message = body.message || Object.values(body.errors || {}).flat()?.[0] || 'Upload failed.';

            throw new Error(message);
        }

        return body;
    });

    const uploadChunk = (task, index) => new Promise((resolve, reject) => {
        const start = index * task.chunkSize;
        const end = Math.min(task.size, start + task.chunkSize);
        const blob = task.file.slice(start, end);
        const formData = new FormData();
        formData.append('index', String(index));
        formData.append('chunk', blob, `${task.name}.part`);

        const xhr = new XMLHttpRequest();
        task.currentXhr = xhr;
        task.xhrs.add(xhr);

        const cleanup = () => {
            task.xhrs.delete(xhr);
            task.inFlight.delete(index);

            if (task.currentXhr === xhr) {
                task.currentXhr = null;
            }
        };

        xhr.open('POST', `${baseUrl}/uploads/${task.uploadId}/chunk`);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken());

        xhr.upload.addEventListener('progress', (event) => {
            if (!event.lengthComputable) return;

            // Track each in-flight chunk separately so concurrent uploads add
            // up instead of overwriting one shared byte counter.
            task.inFlight.set(index, event.loaded);
            emit();
        });

        xhr.onload = () => {
            cleanup();

            if (xhr.status >= 200 && xhr.status < 300) {
                resolve(JSON.parse(xhr.responseText || '{}'));

                return;
            }

            reject(new Error(parseError(xhr, `Chunk ${index + 1} failed.`)));
        };

        xhr.onerror = () => {
            cleanup();
            reject(new Error('Network error while uploading.'));
        };

        xhr.onabort = () => {
            cleanup();
            reject(abortError());
        };

        xhr.send(formData);
    });

    const mergeReceived = (...lists) => {
        const merged = new Set();

        lists.flat().forEach((index) => {
            const value = Number(index);

            if (Number.isInteger(value)) merged.add(value);
        });

        return [...merged].sort((a, b) => a - b);
    };

    const uploadChunks = async (task) => {
        const pending = [];

        for (let index = 0; index < task.totalChunks; index++) {
            if (!task.received.includes(index)) pending.push(index);
        }

        let cursor = 0;
        let failure = null;

        const worker = async () => {
            while (!failure && cursor < pending.length) {
                if (task.cancelled) throw abortError();

                const index = pending[cursor++];

                // A parallel response may already report this index as stored.
                if (task.received.includes(index)) continue;

                try {
                    const result = await uploadChunk(task, index);

                    if (task.cancelled) throw abortError();

                    task.received = mergeReceived(
                        task.received,
                        Array.isArray(result.received_chunks) ? result.received_chunks : [],
                        [index],
                    );
                    task.completedBytes = completedBytesFor(task);
                    await saveTask(task);
                    emit();
                } catch (error) {
                    failure = error;
                    task.xhrs.forEach((xhr) => xhr.abort());
                    throw error;
                }
            }
        };

        const workers = Array.from(
            { length: Math.min(chunkConcurrency, pending.length) },
            () => worker(),
        );

        const results = await Promise.allSettled(workers);

        if (failure) throw failure;

        const rejected = results.find((result) => result.status === 'rejected');

        if (rejected) throw rejected.reason;
    };

    const runTask = (task) => {
        if (task.promise) return task.promise;

        task.promise = (async () => {
            task.status = 'uploading';
            task.error = null;
            await saveBlob(task);
            await saveTask(task);
            emit();

            try {
                if (!task.uploadId) {
                    const init = await requestJson(`${baseUrl}/uploads/init`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            original_name: task.name,
                            size: task.size,
                            mime_type: task.type,
                            total_chunks: Math.max(1, Math.ceil(task.size / fallbackChunkSize)),
                            context: task.context,
                        }),
                    });

                    if (task.cancelled) throw abortError();

                    task.uploadId = init.upload_id;
                    task.totalChunks = init.total_chunks;
                    task.chunkSize = (init.chunk_size_kb || 8192) * 1024;
                    task.received = Array.isArray(init.received_chunks) ? init.received_chunks : [];
                    await saveTask(task);
                } else {
                    try {
                        const status = await requestJson(`${baseUrl}/uploads/${task.uploadId}`);

                        if (task.cancelled) throw abortError();

                        task.totalChunks = status.total_chunks || task.totalChunks;
                        task.chunkSize = task.chunkSize || fallbackChunkSize;
                        task.received = Array.isArray(status.received_chunks) ? status.received_chunks : task.received;

                        if (status.status === 'complete') {
                            task.status = 'complete';
                            task.received = Array.from({ length: task.totalChunks }, (_, index) => index);
                            await saveTask(task);
                            emit();

                            return task;
                        }
                    } catch (error) {
                        if (task.cancelled) throw abortError();

                        if (!/missing|expired|not found/i.test(error.message)) {
                            throw error;
                        }

                        task.uploadId = null;
                        task.received = [];
                    }
                }

                await uploadChunks(task);

                if (task.cancelled) throw abortError();

                await requestJson(`${baseUrl}/uploads/${task.uploadId}/complete`, { method: 'POST' });

                task.status = 'complete';
                task.received = Array.from({ length: task.totalChunks }, (_, index) => index);
                task.completedBytes = task.size;
                task.error = null;
                await saveTask(task);
                emit();

                return task;
            } catch (error) {
                if (task.cancelled || error.name === 'AbortError') {
                    throw abortError();
                }

                task.status = 'error';
                task.error = error.message || 'Upload failed.';
                await saveTask(task);
                emit();

                throw error;
            } finally {
                task.promise = null;
            }
        })();

        return task.promise;
    };

    // ── Public API ───────────────────────────────────────────────────────────

    const isChunkedFile = (file) => file instanceof File && file.size >= chunkThreshold;

    const trackFiles = (files, context = 'bleep') => {
        const tracked = [];

        files.forEach((file) => {
            if (!isChunkedFile(file)) return;

            const existingKey = signatureToKey.get(signature(file));
            const existing = existingKey ? tasks.get(existingKey) : null;

            if (existing) {
                tracked.push(existing);

                if (!existing.promise && existing.status !== 'complete') {
                    runTask(existing).catch(() => {});
                }

                return;
            }

            const task = createTask(file, context);
            saveTask(task).catch(() => {});
            tracked.push(task);
            emit();
            runTask(task).catch(() => {});
        });

        return tracked;
    };

    const prepareFiles = async (files, { context = 'bleep', onProgress = null } = {}) => {
        const tracked = trackFiles(files, context);
        const totalBytes = tracked.reduce((total, task) => total + task.size, 0);

        const report = () => {
            if (!onProgress) return;
            const done = tracked.reduce((total, task) => {
                if (task.status === 'complete') return total + task.size;

                return total + doneBytesFor(task);
            }, 0);
            onProgress(totalBytes ? Math.min(99, Math.round((done / totalBytes) * 100)) : 99, 'Uploading media...');
        };

        report();
        window.addEventListener('media-upload:update', report);

        try {
            await Promise.all(tracked.map((task) => task.promise || (task.status === 'complete' ? Promise.resolve(task) : runTask(task))));
        } finally {
            window.removeEventListener('media-upload:update', report);
        }

        const ids = tracked.map((task) => task.uploadId).filter(Boolean);
        onProgress?.(99, 'Finishing upload...');

        return ids;
    };

    const cancelTask = async (key) => {
        const task = tasks.get(key);
        if (!task) return;

        task.cancelled = true;
        task.xhrs?.forEach((xhr) => xhr.abort());

        if (task.uploadId) {
            fetch(`${baseUrl}/uploads/${task.uploadId}`, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            }).catch(() => {});
        }

        tasks.delete(key);
        signatureToKey.delete(signature(task.file));
        await deleteStoredTask(key);
        emit();
    };

    const cancelFiles = async (files, context = 'bleep') => {
        for (const file of files) {
            const key = signatureToKey.get(signature(file));
            if (!key) continue;

            const task = tasks.get(key);
            if (!task || task.context !== context) continue;

            await cancelTask(key);
        }
    };

    const retryTask = (key) => {
        const task = tasks.get(key);
        if (!task || task.status === 'uploading') return;

        task.status = 'queued';
        task.error = null;
        runTask(task).catch(() => {});
    };

    const markClaimed = async (uploadIds) => {
        const ids = new Set(uploadIds);

        for (const [key, task] of [...tasks.entries()]) {
            if (!ids.has(task.uploadId)) continue;

            tasks.delete(key);
            signatureToKey.delete(signature(task.file));
            await deleteStoredTask(key);
        }

        emit();
    };

    const getTasks = () => [...tasks.values()].map(summaryOf);

    const restore = async () => {
        const stored = await readAllStoredTasks();
        const restored = [];

        for (const record of stored) {
            if (tasks.has(record.key)) continue;

            const blob = await readBlob(record.key);
            if (!blob) continue;

            const task = {
                ...record,
                file: blob,
                received: Array.isArray(record.received) ? record.received.map(Number) : [],
                currentXhr: null,
                xhrs: new Set(),
                inFlight: new Map(),
                promise: null,
            };

            tasks.set(task.key, task);
            signatureToKey.set(signature(blob), task.key);
            restored.push(task);

            if (task.status !== 'complete' && task.status !== 'error') {
                runTask(task).catch(() => {});
            }
        }

        if (restored.length) emit();

        return restored;
    };

    const notifyRestored = (restored) => {
        const bleepTasks = restored.filter((task) => task.context === 'bleep');
        if (!bleepTasks.length) return;

        const detail = {
            tasks: bleepTasks.map((task) => ({ ...summaryOf(task), file: task.file })),
            files: bleepTasks.map((task) => task.file),
        };

        window.__restoredUploadTasks = detail;
        window.dispatchEvent(new CustomEvent('media-upload:restored', { detail }));
    };

    window.MediaUploadManager = {
        chunkThreshold,
        maxFileSize,
        isChunkedFile,
        trackFiles,
        prepareFiles,
        cancelFiles,
        cancelTask,
        retryTask,
        markClaimed,
        getTasks,
        restore,
    };

    // Load persisted tasks immediately, but wait until the rest of the page has
    // wired its listeners before announcing restored files to the composer.
    restore().then((restored) => {
        if (!restored.length) return;

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => queueMicrotask(() => notifyRestored(restored)), { once: true });
        } else {
            queueMicrotask(() => notifyRestored(restored));
        }
    }).catch(() => {});
})();
