/**
 * Notification bell + dropdown.
 *
 * Realtime is best-effort: the tree listens on the user's existing private
 * channel, and a 60s poll keeps the badge honest when Reverb is unavailable
 * (shared hosting is the normal deployment here). Polling is cheap and only
 * replaces the list when the dropdown has already been opened.
 */
export default function notificationsBell(options = {}) {
    const {
        dataUrl,
        readUrlTemplate,
        readAllUrl,
        unreadCount = 0,
        soundUrl = null,
        userId = null,
    } = options;

    return {
        open: false,
        loading: false,
        loaded: false,
        items: [],
        unreadCount: Number(unreadCount) || 0,
        toast: null,

        init() {
            this.syncBadge();
            this.subscribe();
            this.startPolling();

            this.$watch('unreadCount', () => this.syncBadge());
            this.$watch('items', () => this.refreshIcons());

            this._onVisibility = () => {
                if (document.visibilityState === 'visible') {
                    this.poll();
                }
            };
            document.addEventListener('visibilitychange', this._onVisibility);

            this.refreshIcons();
        },

        destroy() {
            if (this._channel) {
                try {
                    this._channel.stopListening('.notification.created');
                } catch (error) {
                    // Channel already gone; nothing to release.
                }
            }

            clearInterval(this._pollTimer);
            clearTimeout(this._toastTimer);
            document.removeEventListener('visibilitychange', this._onVisibility);
        },

        toggle() {
            this.open = !this.open;

            if (this.open && !this.loaded) {
                this.load();
            }
        },

        async load() {
            this.loading = true;

            try {
                const data = await this.fetchLatest();
                this.items = Array.isArray(data.notifications) ? data.notifications : [];
                this.applyUnreadCount(data.unread_count);
                this.loaded = true;
            } catch (error) {
                console.warn('[Notifications] Unable to load notifications.', error);
            } finally {
                this.loading = false;
            }
        },

        async fetchLatest(limit = 30) {
            const response = await window.axios.get(dataUrl, { params: { limit } });

            return response.data ?? {};
        },

        subscribe() {
            if (!userId || !window.Echo) {
                return;
            }

            try {
                this._channel = window.Echo.private(`App.Models.User.${userId}`);
                this._channel.listen('.notification.created', (payload) => {
                    this.receive(payload?.notification);
                });
            } catch (error) {
                console.warn('[Notifications] Realtime unavailable, using polling.', error);
            }
        },

        startPolling() {
            this._pollTimer = setInterval(() => this.poll(), 60000);
        },

        async poll(force = false) {
            if (!force && document.visibilityState !== 'visible') {
                return;
            }

            // Visibility changes can fire repeatedly; keep the fallback quiet.
            if (!force && Date.now() - (this._lastPollAt ?? 0) < 15000) {
                return;
            }
            this._lastPollAt = Date.now();

            try {
                const data = await this.fetchLatest();

                if (this.loaded) {
                    const incoming = Array.isArray(data.notifications) ? data.notifications : [];
                    this.items = incoming;
                }

                this.applyUnreadCount(data.unread_count);
            } catch (error) {
                // Offline or a transient 500: keep the last known state.
            }
        },

        applyUnreadCount(value) {
            const count = Number(value);

            if (Number.isFinite(count) && count >= 0) {
                this.unreadCount = count;
            }
        },

        receive(notification) {
            if (!notification || notification.id == null) {
                return;
            }

            const index = this.items.findIndex((item) => String(item.id) === String(notification.id));
            const wasUnread = index !== -1 && !this.items[index].read;

            if (index !== -1) {
                this.items.splice(index, 1);
            }

            this.items.unshift(notification);

            if (this.items.length > 30) {
                this.items.length = 30;
            }

            if (!notification.read && !wasUnread) {
                this.unreadCount += 1;
            }

            if (!this.open) {
                this.showToast(notification);
                this.playSound();
            }

            this.refreshIcons();
        },

        onItemClick(item, event) {
            if (!item.url) {
                event.preventDefault();
            }

            if (item.read) {
                return;
            }

            item.read = true;
            this.unreadCount = Math.max(0, this.unreadCount - 1);

            // `keepalive` lets the request finish even when the click navigates
            // away immediately, which is the normal case for a notification.
            try {
                fetch(String(readUrlTemplate).replace('__ID__', encodeURIComponent(item.id)), {
                    method: 'POST',
                    keepalive: true,
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                }).catch(() => {});
            } catch (error) {
                // Navigation is more important than the read receipt.
            }
        },

        async markAllRead() {
            try {
                await window.axios.post(readAllUrl);
            } catch (error) {
                console.warn('[Notifications] Unable to mark all as read.', error);

                return;
            }

            this.items.forEach((item) => {
                item.read = true;
            });
            this.unreadCount = 0;
        },

        showToast(notification) {
            this.toast = notification;
            clearTimeout(this._toastTimer);
            this._toastTimer = setTimeout(() => {
                this.toast = null;
            }, 6000);
        },

        playSound() {
            if (!soundUrl || soundUrl === 'none') {
                return;
            }

            try {
                const audio = new Audio(soundUrl);
                audio.volume = 0.6;

                const attempt = audio.play();

                if (attempt?.catch) {
                    attempt.catch(() => {
                        // Browser blocked autoplay until the user interacts.
                    });
                }
            } catch (error) {
                // Audio is a nicety; never break the bell over it.
            }
        },

        syncBadge() {
            window.dispatchEvent(new CustomEvent('notifications:unread-updated', {
                detail: { count: this.unreadCount },
            }));
        },

        refreshIcons() {
            this.$nextTick(() => {
                const container = this.$refs.list;

                if (container && window.createLucideIcons) {
                    window.createLucideIcons(container);
                } else if (window.lucide?.createIcons) {
                    window.lucide.createIcons();
                }
            });
        },
    };
}
