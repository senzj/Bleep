/**
 * Floating background-upload tray.
 *
 * Shows every chunked upload for the current user, including uploads restored
 * after a page reload. Actions are delegated so the list can re-render cheaply.
 */
(() => {
    const tray = () => document.getElementById('media-upload-tray');

    const formatBytes = (bytes) => {
        if (!Number.isFinite(bytes) || bytes <= 0) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB'];
        const index = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)));

        return `${(bytes / 1024 ** index).toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
    };

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    })[character]);

    const statusLabel = (task) => {
        switch (task.status) {
            case 'complete':
                return 'Uploaded';
            case 'error':
                return task.error || 'Upload failed';
            case 'uploading':
                return 'Uploading…';
            default:
                return 'Queued';
        }
    };

    const iconFor = (task) => {
        if (task.status === 'complete') return 'check';
        if (task.status === 'error') return 'circle-alert';

        return 'upload-cloud';
    };

    const rowMarkup = (task) => {
        const complete = task.status === 'complete';
        const failed = task.status === 'error';
        const progress = Math.max(0, Math.min(100, task.progress || 0));
        const name = escapeHtml(task.name);
        const key = escapeHtml(task.key);
        const status = escapeHtml(statusLabel(task));

        return `
                <div class="rounded-lg border border-base-300 bg-base-100 p-3" data-task-row="${key}">
                    <div class="flex items-start gap-2">
                        <span class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-md ${failed ? 'bg-error/15 text-error' : complete ? 'bg-success/15 text-success' : 'bg-primary/15 text-primary'}">
                            <i data-lucide="${iconFor(task)}" class="h-4 w-4"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-semibold" title="${name}">${name}</p>
                            <p class="truncate text-[11px] ${failed ? 'text-error' : 'text-base-content/60'}">
                                ${status} · ${formatBytes(task.size)}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            ${failed ? `<button type="button" class="btn btn-ghost btn-xs gap-1 text-primary" data-task-action="retry" data-task-key="${key}"><i data-lucide="rotate-cw" class="h-3.5 w-3.5"></i>Retry</button>` : ''}
                            ${complete ? `<button type="button" class="btn btn-ghost btn-xs" data-task-action="dismiss" data-task-key="${key}" aria-label="Dismiss ${name}"><i data-lucide="x" class="h-3.5 w-3.5"></i></button>` : ''}
                            ${!complete ? `<button type="button" class="btn btn-ghost btn-xs text-error" data-task-action="cancel" data-task-key="${key}" aria-label="Cancel ${name}"><i data-lucide="x" class="h-3.5 w-3.5"></i></button>` : ''}
                        </div>
                    </div>
                    <div class="mt-2 flex items-center gap-2">
                        <progress class="progress ${failed ? 'progress-error' : complete ? 'progress-success' : 'progress-primary'} h-1.5 w-full" value="${complete ? 100 : progress}" max="100"></progress>
                        <span class="w-9 text-right text-[10px] tabular-nums text-base-content/60" data-tray-progress>${complete ? '100%' : `${progress}%`}</span>
                    </div>
                </div>
            `;
    };

    /** @type {Map<string, {node: HTMLElement, status: string}>} */
    const renderedRows = new Map();

    /**
     * Update a single row. Progress-only changes patch the existing DOM so we
     * do not rebuild markup (or re-run Lucide) dozens of times per second.
     */
    const syncRow = (task) => {
        const existing = renderedRows.get(task.key);

        if (!existing || existing.status !== task.status) {
            const wrapper = document.createElement('div');
            wrapper.innerHTML = rowMarkup(task);
            const node = wrapper.firstElementChild;

            if (existing) {
                existing.node.replaceWith(node);
            }

            renderedRows.set(task.key, { node, status: task.status });

            return { node, isNew: true };
        }

        const complete = task.status === 'complete';
        const progress = complete ? 100 : Math.max(0, Math.min(100, task.progress || 0));

        const bar = existing.node.querySelector('progress');
        if (bar) bar.value = progress;

        const label = existing.node.querySelector('[data-tray-progress]');
        if (label) label.textContent = `${progress}%`;

        return { node: existing.node, isNew: false };
    };

    const render = (taskList) => {
        const panel = tray();
        if (!panel) return;

        const list = panel.querySelector('[data-tray-list]');
        const count = panel.querySelector('[data-tray-count]');
        const active = taskList;

        if (count) {
            const showCount = active.length > 1;
            count.textContent = showCount ? String(active.length) : '';
            count.classList.toggle('hidden', !showCount);
        }

        panel.classList.toggle('hidden', active.length === 0);

        if (!list) return;

        if (!active.length) {
            renderedRows.clear();
            list.innerHTML = '';

            return;
        }

        const activeKeys = new Set(active.map((task) => task.key));

        for (const [key, entry] of [...renderedRows.entries()]) {
            if (activeKeys.has(key)) continue;

            entry.node.remove();
            renderedRows.delete(key);
        }

        const fragment = document.createDocumentFragment();
        let added = false;

        active.forEach((task) => {
            const { node, isNew } = syncRow(task);

            fragment.appendChild(node);

            if (isNew) added = true;
        });

        list.appendChild(fragment);

        if (added) {
            window.createLucideIcons?.(list);
        }
    };

    // Upload progress fires on every animation frame; rendering that fast would
    // thrash the DOM. Coalesce to a handful of updates per second while always
    // painting the most recent state.
    const RENDER_INTERVAL_MS = 150;

    let renderTimer = null;
    let pendingTasks = [];
    let renderedAt = 0;

    const scheduleRender = (taskList) => {
        pendingTasks = taskList;

        if (renderTimer) return;

        const delay = Math.max(0, RENDER_INTERVAL_MS - (performance.now() - renderedAt));

        renderTimer = window.setTimeout(() => {
            renderTimer = null;
            renderedAt = performance.now();
            render(pendingTasks);
        }, delay);
    };

    const handle = (event) => {
        const button = event.target.closest('[data-task-action]');
        if (!button) return;

        const key = button.dataset.taskKey;
        const action = button.dataset.taskAction;
        const manager = window.MediaUploadManager;
        if (!manager || !key) return;

        if (action === 'retry') manager.retryTask(key);
        if (action === 'cancel') manager.cancelTask(key);
        if (action === 'dismiss') manager.cancelTask(key);
    };

    const init = () => {
        const panel = tray();
        if (!panel) return;

        panel.addEventListener('click', handle);

        window.addEventListener('media-upload:update', (event) => {
            scheduleRender(event.detail?.tasks || []);
        });

        window.addEventListener('media-upload:restored', () => {
            scheduleRender(window.MediaUploadManager?.getTasks() || []);
        });

        scheduleRender(window.MediaUploadManager?.getTasks() || []);

        panel.querySelector('[data-tray-toggle]')?.addEventListener('click', () => {
            const body = panel.querySelector('[data-tray-body]');
            body?.classList.toggle('hidden');
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
