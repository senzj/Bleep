document.addEventListener('DOMContentLoaded', function() {
    // Re-initialize event listeners when tab changes
    const tabInputs = document.querySelectorAll('input[name="profile_tabs"]');

    tabInputs.forEach(input => {
        input.addEventListener('change', function() {
            // Small delay to ensure content is visible
            setTimeout(() => {
                // Dispatch custom event to reinitialize listeners
                window.dispatchEvent(new Event('tab-changed'));
            }, 100);
        });
    });
});
