/**
 * Random Username Generator Integration
 * Provides functionality to generate and use random usernames in registration
 */

(function() {
    'use strict';

    // Initialize the random username generator once DOM is ready
    document.addEventListener('DOMContentLoaded', initRandomUsernameGenerator);

    /**
     * Initialize all random username generator functionality
     */
    function initRandomUsernameGenerator() {
        const usernameField = document.getElementById('username');
        const generateBtn = document.getElementById('generate-username-btn');
        const refreshIcon = document.getElementById('generate-username-icon');

        if (!usernameField || !generateBtn) {
            console.warn('Username generator elements not found. Skipping initialization.');
            return;
        }

        // Add generate button click handler
        generateBtn.addEventListener('click', () => {
            generateNewUsername(usernameField);
        });

        // Allow refresh icon to trigger generation on click
        if (refreshIcon) {
            refreshIcon.addEventListener('click', (e) => {
                e.preventDefault();
                generateNewUsername(usernameField);
            });
        }

        // Optional: Add keyboard shortcut (Ctrl/Cmd + G to generate username)
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'g') {
                e.preventDefault();
                generateNewUsername(usernameField);
            }
        });
    }

    /**
     * Generate a new random username via API
     * @param {HTMLElement} usernameField - The username input field
     */
    async function generateNewUsername(usernameField) {
        const generateBtn = document.getElementById('generate-username-btn');
        const usernameIcon = document.getElementById('generate-username-icon');
        const usernameFeedback = document.getElementById('username_feedback');

        try {
            // Show loading state
            if (generateBtn) {
                generateBtn.disabled = true;
            }
            if (usernameIcon) {
                usernameIcon.classList.add('animate-spin');
            }

            // Call the API endpoint
            const response = await fetch('/generate-username', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            });

            if (!response.ok) {
                throw new Error(`API error: ${response.status}`);
            }

            const data = await response.json();

            if (data.username) {
                // Set the generated username
                usernameField.value = data.username;

                // Trigger validation
                usernameField.dispatchEvent(new Event('input', { bubbles: true }));

                // Show success message
                showUsernameGeneratedToast(data.username);
            } else {
                showErrorToast('Failed to generate username');
            }
        } catch (error) {
            console.error('Error generating username:', error);
            showErrorToast('Could not generate username. Please try again.');
        } finally {
            // Remove loading state
            if (generateBtn) {
                generateBtn.disabled = false;
            }
            if (usernameIcon) {
                usernameIcon.classList.remove('animate-spin');
            }
        }
    }

    /**
     * Show a success toast when username is generated
     * @param {string} username - The generated username
     */
    function showUsernameGeneratedToast(username) {
        const toast = document.createElement('div');
        toast.className = `alert alert-success fixed top-4 right-4 w-auto max-w-sm shadow-lg z-50 transition-all duration-300 opacity-0 -translate-y-4`;
        toast.innerHTML = `
            <i data-lucide="check-circle" class="w-5 h-5"></i>
            <span>Username generated: <strong>${escapeHtml(username)}</strong></span>
        `;

        document.body.appendChild(toast);

        // Trigger animations
        if (window.lucide) {
            window.lucide.createIcons();
        }

        setTimeout(() => {
            toast.classList.remove('opacity-0', '-translate-y-4');
            toast.classList.add('opacity-100', 'translate-y-0');
        }, 10);

        setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-y-4');
        }, 3000);

        setTimeout(() => {
            toast.remove();
        }, 3300);
    }

    /**
     * Show an error toast
     * @param {string} message - The error message
     */
    function showErrorToast(message) {
        const toast = document.createElement('div');
        toast.className = `alert alert-error fixed top-4 right-4 w-auto max-w-sm shadow-lg z-50 transition-all duration-300 opacity-0 -translate-y-4`;
        toast.innerHTML = `
            <i data-lucide="alert-circle" class="w-5 h-5"></i>
            <span>${escapeHtml(message)}</span>
        `;

        document.body.appendChild(toast);

        // Trigger animations
        if (window.lucide) {
            window.lucide.createIcons();
        }

        setTimeout(() => {
            toast.classList.remove('opacity-0', '-translate-y-4');
            toast.classList.add('opacity-100', 'translate-y-0');
        }, 10);

        setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-y-4');
        }, 3000);

        setTimeout(() => {
            toast.remove();
        }, 3300);
    }

    /**
     * Escape HTML special characters to prevent XSS
     * @param {string} text - The text to escape
     * @returns {string} The escaped text
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, char => map[char]);
    }

    // Expose function globally for manual use if needed
    window.generateNewUsername = function() {
        const usernameField = document.getElementById('username');
        if (usernameField) {
            generateNewUsername(usernameField);
        }
    };
})();
