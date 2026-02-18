/**
 * User Preferences Manager
 * Handles auto-saving preferences to the server via API
 */

class PreferencesManager {
    constructor() {
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        this.toast = document.getElementById('pref-toast');
        this.apiUrl = '/api/preferences/update';
        this.debounceTimers = {};

        this.init();
    }

    /**
     * Initialize all preference handlers
     */
    init() {
        this.bindToggleSwitches();
        this.bindSelectElements();
        this.initLucideIcons();

        console.log('[Preferences] Manager initialized');
    }

    /**
     * Show toast notification
     */
    showToast(success = true, message = 'Preference saved!') {
        if (!this.toast) return;

        const alertDiv = this.toast.querySelector('.alert');
        if (!alertDiv) return;

        // Update alert styling
        alertDiv.classList.remove('alert-success', 'alert-error');
        alertDiv.classList.add(success ? 'alert-success' : 'alert-error');

        // Update message
        const span = alertDiv.querySelector('span');
        if (span) span.textContent = message;

        // Show toast
        this.toast.classList.remove('hidden');

        // Auto-hide after 2 seconds
        setTimeout(() => {
            this.toast.classList.add('hidden');
        }, 2000);
    }

    /**
     * Update preference via API
     */
    async updatePreference(key, value) {
        // Show loading state
        const element = document.querySelector(`[data-pref="${key}"]`);
        if (element) {
            element.disabled = true;
        }

        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ key, value }),
            });

            const data = await response.json();

            if (data.success) {
                this.showToast(true, 'Preference saved!');

                // Special handling for preferences that require page reload
                if (key === 'nav_layout') {
                    this.showToast(true, 'Navigation layout updated! Please wait while the page reloads...');
                    setTimeout(() => window.location.reload(), 1500);
                    return;
                }

                // Emit custom event for other components to react
                window.dispatchEvent(new CustomEvent('preference:updated', {
                    detail: { key, value: data.value }
                }));

            } else {
                this.showToast(false, data.error || 'Failed to save preference');
                // Revert the UI change
                this.revertElement(element, key);
            }
        } catch (error) {
            console.error('[Preferences] Error updating preference:', error);
            this.showToast(false, 'Failed to save preference');
            // Revert the UI change
            this.revertElement(element, key);
        } finally {
            // Re-enable the element
            if (element) {
                element.disabled = false;
            }
        }
    }

    /**
     * Revert element to previous state on error
     */
    revertElement(element, key) {
        if (!element) return;

        if (element.type === 'checkbox') {
            element.checked = !element.checked;
        }
        // For selects, we'd need to store the previous value - TODO if needed
    }

    /**
     * Debounce function to prevent rapid API calls
     */
    debounce(key, callback, delay = 300) {
        if (this.debounceTimers[key]) {
            clearTimeout(this.debounceTimers[key]);
        }
        this.debounceTimers[key] = setTimeout(callback, delay);
    }

    /**
     * Bind toggle switch handlers
     */
    bindToggleSwitches() {
        document.querySelectorAll('.pref-toggle').forEach(toggle => {
            toggle.addEventListener('change', (e) => {
                const key = e.target.dataset.pref;
                const value = e.target.checked;

                if (!key) {
                    console.warn('[Preferences] Toggle missing data-pref attribute');
                    return;
                }

                // Immediate update for toggles (no debounce needed)
                this.updatePreference(key, value);
            });
        });
    }

    /**
     * Bind select element handlers
     */
    bindSelectElements() {
        // Handle both .pref-select class and #nav-layout-select
        document.querySelectorAll('.pref-select, #nav-layout-select').forEach(select => {
            select.addEventListener('change', (e) => {
                const key = e.target.dataset.pref;
                const value = e.target.value;

                if (!key) {
                    console.warn('[Preferences] Select missing data-pref attribute');
                    return;
                }

                this.updatePreference(key, value);
            });
        });
    }

    /**
     * Initialize Lucide icons
     */
    initLucideIcons() {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    /**
     * Get all current preferences from the page
     */
    getCurrentPreferences() {
        const preferences = {};

        // Get all toggles
        document.querySelectorAll('.pref-toggle').forEach(toggle => {
            const key = toggle.dataset.pref;
            if (key) {
                preferences[key] = toggle.checked;
            }
        });

        // Get all selects
        document.querySelectorAll('.pref-select, #nav-layout-select').forEach(select => {
            const key = select.dataset.pref;
            if (key) {
                preferences[key] = select.value;
            }
        });

        return preferences;
    }

    /**
     * Batch update multiple preferences at once
     */
    async batchUpdate(preferences) {
        try {
            const response = await fetch('/api/preferences/batch', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ preferences }),
            });

            const data = await response.json();

            if (data.success) {
                this.showToast(true, 'All preferences saved!');
                return data.preferences;
            } else {
                this.showToast(false, 'Failed to save preferences');
                return null;
            }
        } catch (error) {
            console.error('[Preferences] Error batch updating:', error);
            this.showToast(false, 'Failed to save preferences');
            return null;
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Only initialize on preferences page
    if (document.querySelector('.pref-toggle') || document.querySelector('.pref-select')) {
        window.preferencesManager = new PreferencesManager();
    }
});

// Export for module usage
export default PreferencesManager;
