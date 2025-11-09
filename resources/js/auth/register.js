document.addEventListener('DOMContentLoaded', () => {
    // Set detected timezone into hidden field so server receives it
    const tzField = document.getElementById('timezone');
    if (tzField) {
        tzField.value = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
    }

    // Ensure timezone present before submit (defensive)
    const form = document.getElementById('register-form') || document.querySelector('form');
    if (form && tzField) {
        form.addEventListener('submit', () => {
        if (!tzField.value) {
            tzField.value = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
        }
        });
    }
});
