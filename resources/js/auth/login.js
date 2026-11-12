/**
 * Login page interactions: password visibility toggle and remembering the
 * policy consents on this device.
 */
document.addEventListener('DOMContentLoaded', () => {
    const passwordInput = document.getElementById('login_password');
    const togglePassword = document.getElementById('toggle_login_password');

    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', () => {
            const type = passwordInput.type === 'password' ? 'text' : 'password';
            passwordInput.type = type;

            const eyeIcon = document.getElementById('login_eye_icon');
            const eyeOffIcon = document.getElementById('login_eye_off_icon');

            if (type === 'text') {
                eyeIcon?.classList.add('hidden');
                eyeOffIcon?.classList.remove('hidden');
            } else {
                eyeIcon?.classList.remove('hidden');
                eyeOffIcon?.classList.add('hidden');
            }
        });
    }

    // "Remember me" also re-accepts the policy toggles on this device.
    const rememberCheckbox = document.getElementById('login_remember');
    const policyCheckboxes = document.querySelectorAll('form [data-policy-consent]');

    const syncPolicyConsent = () => {
        if (!rememberCheckbox || !rememberCheckbox.checked) return;
        policyCheckboxes.forEach((checkbox) => {
            checkbox.checked = true;
        });
    };

    rememberCheckbox?.addEventListener('change', syncPolicyConsent);
    syncPolicyConsent();
});
