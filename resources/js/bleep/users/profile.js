document.addEventListener('DOMContentLoaded', function() {
    // Detect tab changes via Alpine.js button clicks
    const tabButtons = document.querySelectorAll('[role="tab"]');
    const tabContainer = document.querySelector('[role="tablist"]');

    if (!tabContainer) return;

    // Watch for tab button clicks and dispatch custom event
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Small delay to ensure Alpine has updated the DOM
            setTimeout(() => {
                // Reinitialize Lucide icons and notify observers
                if (window.lucide && typeof window.lucide.createIcons === 'function') {
                    window.lucide.createIcons();
                }
                // Dispatch custom event to notify lazy loaders
                window.dispatchEvent(new Event('tab-changed'));
            }, 50);
        });
    });
});
