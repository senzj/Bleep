document.addEventListener('DOMContentLoaded', () => {
    // Set detected timezone
    const tzField = document.getElementById('timezone');
    if (tzField) {
        tzField.value = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
    }

    // Password toggle functionality
    const passwordInput = document.getElementById('password');
    const passwordConfirmInput = document.getElementById('password_confirmation');
    const togglePassword = document.getElementById('toggle_password');
    const togglePasswordConfirm = document.getElementById('toggle_password_confirmation');

    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', () => {
            const type = passwordInput.type === 'password' ? 'text' : 'password';
            passwordInput.type = type;

            const eyeIcon = document.getElementById('eye_icon');
            const eyeOffIcon = document.getElementById('eye_off_icon');

            if (type === 'text') {
                eyeIcon.classList.add('hidden');
                eyeOffIcon.classList.remove('hidden');
            } else {
                eyeIcon.classList.remove('hidden');
                eyeOffIcon.classList.add('hidden');
            }
        });
    }

    if (togglePasswordConfirm && passwordConfirmInput) {
        togglePasswordConfirm.addEventListener('click', () => {
            const type = passwordConfirmInput.type === 'password' ? 'text' : 'password';
            passwordConfirmInput.type = type;

            const eyeIcon = document.getElementById('eye_icon_confirm');
            const eyeOffIcon = document.getElementById('eye_off_icon_confirm');

            if (type === 'text') {
                eyeIcon.classList.add('hidden');
                eyeOffIcon.classList.remove('hidden');
            } else {
                eyeIcon.classList.remove('hidden');
                eyeOffIcon.classList.add('hidden');
            }
        });
    }

    // -------------------------------
    // DISPLAY NAME VALIDATION
    // -------------------------------
    const displayName = document.getElementById("display_name");
    const displayFeedback = document.getElementById("display_name_feedback");

    // Track if user has manually edited the username
    let userManuallyEditedUsername = false;

    displayName?.addEventListener("input", () => {
        const val = displayName.value.trim();

        if (val.length === 0) {
            setFieldState(displayName, displayFeedback, '', 'neutral');
            updateSubmitButton();
            return;
        }

        if (val.length < 3) {
            setFieldState(displayName, displayFeedback, "Must be at least 3 characters", 'error');
            updateSubmitButton();
            return;
        }

        if (val.length > 255) {
            setFieldState(displayName, displayFeedback, "Maximum 255 characters", 'error');
            updateSubmitButton();
            return;
        }

        setFieldState(displayName, displayFeedback, "Looks good!", 'success');

        // Auto-populate username from display name (if user hasn't manually edited it)
        if (!userManuallyEditedUsername && username) {
            const autoUsername = val
                .toLowerCase()
                .replace(/\s+/g, '_')           // Replace spaces with underscores
                .replace(/[^a-z0-9_.]/g, '')    // Remove invalid characters
                .substring(0, 20);               // Limit to 20 chars

            if (autoUsername.length >= 3) {
                username.value = autoUsername;
                // Trigger username validation
                username.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }

        updateSubmitButton();
    });

    // -------------------------------
    // USERNAME VALIDATION WITH BACKEND CHECK
    // -------------------------------
    const username = document.getElementById("username");
    const usernameFeedback = document.getElementById("username_feedback");
    let usernameTimeout;

    // Mark username as manually edited when user types in it directly
    username?.addEventListener("keydown", () => {
        userManuallyEditedUsername = true;
    });

    username?.addEventListener("input", () => {
        const val = username.value.trim();

        clearTimeout(usernameTimeout);

        if (val.length === 0) {
            setFieldState(username, usernameFeedback, '', 'neutral');
            updateSubmitButton();
            return;
        }

        // Remove spaces automatically
        if (val.includes(' ')) {
            username.value = val.replace(/\s+/g, '_');
        }

        // Format validation (no spaces allowed)
        if (!/^[a-zA-Z0-9_.]{3,20}$/.test(val)) {
            setFieldState(username, usernameFeedback, "3-20 characters: letters, numbers, underscore, or dot only", 'error');
            updateSubmitButton();
            return;
        }

        // Show checking state
        setFieldState(username, usernameFeedback, "Checking availability...", 'checking');

        // Debounce backend check
        usernameTimeout = setTimeout(() => {
            checkUsernameAvailability(val);
        }, 500);
    });

    async function checkUsernameAvailability(usernameValue) {
        try {
            const response = await fetch('/check-username', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({ username: usernameValue })
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const contentType = response.headers.get("content-type");
            if (!contentType || !contentType.includes("application/json")) {
                throw new Error("Response is not JSON");
            }

            const data = await response.json();
            const usernameField = document.getElementById("username");
            const feedback = document.getElementById("username_feedback");

            if (data.available) {
                setFieldState(usernameField, feedback, "✓ Username available!", 'success');
            } else {
                setFieldState(usernameField, feedback, "✗ Username already taken", 'error');
            }
        } catch (error) {
            console.error('Error checking username:', error);
            const usernameField = document.getElementById("username");
            const feedback = document.getElementById("username_feedback");
            setFieldState(usernameField, feedback, "Username format valid ✔", 'success');
        }
        updateSubmitButton();
    }

    // -------------------------------
    // RANDOM USERNAME GENERATOR
    // -------------------------------
    const generateUsernameBtn = document.getElementById("generate_username_btn");

    generateUsernameBtn?.addEventListener("click", async () => {
        // Mark as manually edited since user clicked the button
        userManuallyEditedUsername = true;

        // Show loading state
        generateUsernameBtn.classList.add('loading');
        generateUsernameBtn.disabled = true;
        setFieldState(username, usernameFeedback, "Generating...", 'checking');

        try {
            const response = await fetch('/generate-username', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            });

            if (response.ok) {
                const data = await response.json();
                if (data.username) {
                    username.value = data.username;
                    // Also set display name (formatted nicely)
                    if (displayName) {
                        displayName.value = formatUsernameAsDisplayName(data.username);
                        displayName.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                    // Trigger validation
                    username.dispatchEvent(new Event('input', { bubbles: true }));
                }
            } else {
                // Fallback: generate client-side
                const generatedUsername = generateRandomUsernameClient();
                username.value = generatedUsername;
                if (displayName) {
                    displayName.value = formatUsernameAsDisplayName(generatedUsername);
                    displayName.dispatchEvent(new Event('input', { bubbles: true }));
                }
                username.dispatchEvent(new Event('input', { bubbles: true }));
            }
        } catch (error) {
            console.error('Error generating username:', error);
            // Fallback: generate client-side
            const generatedUsername = generateRandomUsernameClient();
            username.value = generatedUsername;
            if (displayName) {
                displayName.value = formatUsernameAsDisplayName(generatedUsername);
                displayName.dispatchEvent(new Event('input', { bubbles: true }));
            }
            username.dispatchEvent(new Event('input', { bubbles: true }));
        } finally {
            generateUsernameBtn.classList.remove('loading');
            generateUsernameBtn.disabled = false;
        }
    });

    /**
     * Convert username to a nice display name
     * e.g., "sneakyfox42" -> "Sneaky Fox"
     */
    function formatUsernameAsDisplayName(username) {
        // Remove digits at the end or middle
        let name = username.replace(/\d+/g, ' ').trim();

        // Split camelCase or by underscores/dots
        name = name
            .replace(/([a-z])([A-Z])/g, '$1 $2')  // camelCase
            .replace(/[_.]/g, ' ')                  // underscores and dots
            .trim();

        // Capitalize each word
        return name
            .split(/\s+/)
            .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
            .join(' ');
    }

    // -------------------------------
    // EMAIL VALIDATION WITH BACKEND CHECK
    // -------------------------------
    const email = document.getElementById("email");
    const emailFeedback = document.getElementById("email_feedback");
    let emailTimeout;

    email?.addEventListener("input", () => {
        const val = email.value.trim();

        clearTimeout(emailTimeout);

        if (val.length === 0) {
            setFieldState(email, emailFeedback, '', 'neutral');
            updateSubmitButton();
            return;
        }

        // Format validation
        if (!/^\S+@\S+\.\S+$/.test(val)) {
            setFieldState(email, emailFeedback, "Please enter a valid email address", 'error');
            updateSubmitButton();
            return;
        }

        // Show checking state
        setFieldState(email, emailFeedback, "Checking availability...", 'checking');

        // Debounce backend check
        emailTimeout = setTimeout(() => {
            checkEmailAvailability(val);
        }, 500);
    });

    async function checkEmailAvailability(emailValue) {
        try {
            const response = await fetch('/check-email', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({ email: emailValue })
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const contentType = response.headers.get("content-type");
            if (!contentType || !contentType.includes("application/json")) {
                throw new Error("Response is not JSON");
            }

            const data = await response.json();
            const emailField = document.getElementById("email");
            const feedback = document.getElementById("email_feedback");

            if (data.available) {
                setFieldState(emailField, feedback, "✓ Email available!", 'success');
            } else {
                setFieldState(emailField, feedback, "✗ Email already registered", 'error');
            }
        } catch (error) {
            console.error('Error checking email:', error);
            const emailField = document.getElementById("email");
            const feedback = document.getElementById("email_feedback");
            setFieldState(emailField, feedback, "Valid email ✔", 'success');
        }
        updateSubmitButton();
    }

    // -------------------------------
    // PASSWORD REQUIREMENTS & STRENGTH
    // -------------------------------
    if (passwordInput) {
        const strengthBars = [
            document.getElementById('strength_bar_1'),
            document.getElementById('strength_bar_2'),
            document.getElementById('strength_bar_3'),
            document.getElementById('strength_bar_4'),
            document.getElementById('strength_bar_5')
        ];
        const strengthText = document.getElementById('strength_text');

        const requirements = {
            length: document.getElementById('req_length'),
            upper: document.getElementById('req_upper'),
            lower: document.getElementById('req_lower'),
            number: document.getElementById('req_number'),
            special: document.getElementById('req_special')
        };

        // Common weak passwords and patterns
        const weakPasswords = [
            'password', 'password1', 'password123', '12345678', '123456789',
            'qwerty', 'abc123', 'monkey', 'letmein', 'trustno1',
            'dragon', 'baseball', 'iloveyou', 'master', 'sunshine',
            'ashley', 'bailey', 'passw0rd', 'shadow', 'superman',
            'qazwsx', '123qwe', 'welcome', 'admin', 'root', 'pass'
        ];

        function isWeakPattern(password) {
            const lower = password.toLowerCase();

            // Check against common weak passwords
            if (weakPasswords.some(weak => lower.includes(weak))) {
                return true;
            }

            // Check for keyboard patterns
            const keyboardPatterns = [
                'qwerty', 'asdfgh', 'zxcvbn', '1qaz2wsx', 'qwertyuiop',
                'asdfghjkl', 'zxcvbnm', '1234567890', '0987654321'
            ];
            if (keyboardPatterns.some(pattern => lower.includes(pattern))) {
                return true;
            }

            // Check for sequential numbers (123, 234, etc.)
            if (/(\d)\1{2,}/.test(password)) {
                return true;
            }

            // Check for sequential letters (abc, bcd, etc.)
            if (/(?:abc|bcd|cde|def|efg|fgh|ghi|hij|ijk|jkl|klm|lmn|mno|nop|opq|pqr|qrs|rst|stu|tuv|uvw|vwx|wxy|xyz)/i.test(password)) {
                return true;
            }

            // Check for repeating patterns (aa, aaa, 11, 111, etc.)
            if (/(.)\1{2,}/.test(password)) {
                return true;
            }

            return false;
        }

        function calculatePasswordScore(password, checks) {
            let score = 0;

            // Base score from requirements
            const metRequirements = Object.values(checks).filter(Boolean).length;
            score += metRequirements * 2;

            // Bonus for length beyond minimum
            if (password.length >= 12) score += 2;
            if (password.length >= 16) score += 2;

            // Bonus for character variety
            const uniqueChars = new Set(password).size;
            if (uniqueChars >= 10) score += 1;
            if (uniqueChars >= 15) score += 1;

            // Penalty for weak patterns
            if (isWeakPattern(password)) {
                score = Math.max(1, Math.floor(score / 2));
            }

            // Bonus for mixing different character types
            if (checks.upper && checks.lower && checks.number && checks.special) {
                score += 1;
            }

            return score;
        }

        passwordInput.addEventListener('input', () => {
            const password = passwordInput.value;

            // Show requirements panel
            const reqPanel = document.getElementById('password_requirements');
            if (reqPanel) {
                reqPanel.classList.remove('hidden');
            }

            // Check requirements
            const checks = {
                length: password.length >= 8,
                upper: /[A-Z]/.test(password),
                lower: /[a-z]/.test(password),
                number: /\d/.test(password),
                special: /[@$!%*#?&]/.test(password)
            };

            // Update requirement indicators
            Object.keys(checks).forEach(key => {
                const element = requirements[key];
                if (!element) return;

                const spanElement = element.querySelector('span');
                if (!spanElement) return;

                const oldIcon = element.querySelector('i[data-lucide], svg');
                if (oldIcon) {
                    oldIcon.remove();
                }

                const newIcon = document.createElement('i');

                if (checks[key]) {
                    newIcon.setAttribute('data-lucide', 'check-circle-2');
                    newIcon.className = 'w-4 h-4 text-success';
                    spanElement.classList.remove('text-base-content/50');
                    spanElement.classList.add('text-success', 'font-medium');
                    element.classList.add('requirement-met');
                } else {
                    newIcon.setAttribute('data-lucide', 'circle');
                    newIcon.className = 'w-4 h-4 text-base-content/30';
                    spanElement.classList.remove('text-success', 'font-medium');
                    spanElement.classList.add('text-base-content/50');
                    element.classList.remove('requirement-met');
                }

                element.insertBefore(newIcon, spanElement);
            });

            if (window.lucide) {
                window.lucide.createIcons();
            }

            // Calculate strength with pattern detection
            const allRequirementsMet = Object.values(checks).every(Boolean);
            const score = calculatePasswordScore(password, checks);

            let strength = 0;
            let strengthLabel = '';
            let strengthColor = '';

            if (password.length === 0) {
                strength = 0;
                strengthLabel = '';
            } else if (!allRequirementsMet || isWeakPattern(password)) {
                if (isWeakPattern(password) && allRequirementsMet) {
                    strength = 1;
                    strengthLabel = 'Weak (too common)';
                    strengthColor = 'bg-error';
                } else {
                    strength = 1;
                    strengthLabel = 'Weak';
                    strengthColor = 'bg-error';
                }
            } else if (score <= 10) {
                strength = 2;
                strengthLabel = 'Fair';
                strengthColor = 'bg-warning';
            } else if (score <= 12) {
                strength = 3;
                strengthLabel = 'Good';
                strengthColor = 'bg-info';
            } else if (score <= 14) {
                strength = 4;
                strengthLabel = 'Strong';
                strengthColor = 'bg-success';
            } else {
                strength = 5;
                strengthLabel = 'Very Strong';
                strengthColor = 'bg-success';
            }

            // Update strength bars
            strengthBars.forEach((bar, index) => {
                if (index < strength) {
                    bar.classList.remove('bg-base-300', 'bg-error', 'bg-warning', 'bg-info', 'bg-success');
                    bar.classList.add(strengthColor);
                } else {
                    bar.classList.remove('bg-error', 'bg-warning', 'bg-info', 'bg-success');
                    bar.classList.add('bg-base-300');
                }
            });

            // Update strength text
            if (strengthText) {
                strengthText.textContent = strengthLabel;
                strengthText.className = 'text-xs font-semibold';
                if (strengthColor) {
                    const colorClass = strengthColor.replace('bg-', 'text-');
                    strengthText.classList.add(colorClass);
                }
            }

            // Update submit button state
            updateSubmitButton();

            // Check password match
            checkPasswordMatch();
        });
    }

    // -------------------------------
    // PASSWORD MATCH CHECKER
    // -------------------------------
    function checkPasswordMatch() {
        if (!passwordInput || !passwordConfirmInput) return;

        const password = passwordInput.value;
        const confirm = passwordConfirmInput.value;
        const confirmFeedback = document.getElementById('confirm_feedback');

        if (confirm.length === 0) {
            setFieldState(passwordConfirmInput, confirmFeedback, '', 'neutral');
            updateSubmitButton();
            return;
        }

        if (password === confirm) {
            setFieldState(passwordConfirmInput, confirmFeedback, "✓ Passwords match", 'success');
        } else {
            setFieldState(passwordConfirmInput, confirmFeedback, "✗ Passwords do not match", 'error');
        }

        updateSubmitButton();
    }

    if (passwordConfirmInput) {
        passwordConfirmInput.addEventListener('input', checkPasswordMatch);
    }

    // -------------------------------
    // SUBMIT BUTTON STATE MANAGEMENT
    // -------------------------------
    function updateSubmitButton() {
        const submitButton = document.querySelector('button[type="submit"]');
        if (!submitButton) return;

        const isValid = validateAllFields();

        if (isValid) {
            submitButton.disabled = false;
            submitButton.classList.remove('btn-disabled', 'opacity-50', 'cursor-not-allowed');
        } else {
            submitButton.disabled = true;
            submitButton.classList.add('btn-disabled', 'opacity-50', 'cursor-not-allowed');
        }
    }

    // -------------------------------
    // UTILITY FUNCTIONS
    // -------------------------------
    function setFieldState(field, feedback, message, state) {
        if (!field || !feedback) return;

        feedback.textContent = message;

        field.classList.remove('input-error', 'input-success', 'input-warning', 'border-info');
        feedback.classList.remove('text-error', 'text-success', 'text-warning', 'text-info');

        switch(state) {
            case 'success':
                field.classList.add('input-success');
                feedback.classList.add('text-success');
                break;
            case 'error':
                field.classList.add('input-error');
                feedback.classList.add('text-error');
                break;
            case 'checking':
                field.classList.add('border-info');
                feedback.classList.add('text-info');
                break;
            case 'neutral':
                break;
        }
    }

    function validateAllFields() {
        let isValid = true;

        // Check display name
        const displayVal = displayName?.value.trim();
        if (!displayVal || displayVal.length < 3) isValid = false;

        // Check username
        const usernameVal = username?.value.trim();
        if (!usernameVal || !/^[a-zA-Z0-9_.]{3,20}$/.test(usernameVal)) isValid = false;

        // Check email
        const emailVal = email?.value.trim();
        if (!emailVal || !/^\S+@\S+\.\S+$/.test(emailVal)) isValid = false;

        // Check password
        const password = passwordInput?.value;
        if (!password || password.length < 8) isValid = false;
        if (!/[A-Z]/.test(password)) isValid = false;
        if (!/[a-z]/.test(password)) isValid = false;
        if (!/\d/.test(password)) isValid = false;
        if (!/[@$!%*#?&]/.test(password)) isValid = false;

        // Check password confirmation
        const confirm = passwordConfirmInput?.value;
        if (password !== confirm) isValid = false;

        return isValid;
    }

    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} fixed top-4 right-4 w-auto max-w-sm shadow-lg z-50 transition-all duration-300 opacity-0 -translate-y-4`;
        toast.innerHTML = `
            <i data-lucide="${type === 'error' ? 'alert-circle' : 'info'}" class="w-5 h-5"></i>
            <span>${message}</span>
        `;

        document.body.appendChild(toast);

        if (window.lucide) {
            window.lucide.createIcons();
        }

        setTimeout(() => {
            toast.classList.remove('opacity-0', '-translate-y-4');
            toast.classList.add('opacity-100', 'translate-y-0');
        }, 10);

        setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-x-4');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Ensure timezone before submit
    const form = document.getElementById('register-form');
    if (form && tzField) {
        form.addEventListener('submit', (e) => {
            if (!tzField.value) {
                tzField.value = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
            }

            if (!validateAllFields()) {
                e.preventDefault();
                showToast('Please fix all errors before submitting', 'error');
            }
        });
    }

    // Initialize button state on page load
    updateSubmitButton();
});
